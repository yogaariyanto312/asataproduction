<?php

namespace App\Http\Controllers;

use App\Models\ProductionLog;
use App\Support\MenuAccess;
use App\Support\NomorUrut;
use App\Support\UrutanProduksi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Laporan produksi — mengikuti Production-QC-Logging-System: layar, PDF, dan
 * Excel memakai SATU sumber data (rekapBulanan) dengan urutan Riwayat
 * Produksi (kategori → KVA → seri), dan nomor urut merangkum sebulan penuh.
 * Tambahan asata: filter departemen untuk developer.
 */
class ReportController extends Controller
{
    /**
     * Bulan & tahun yang diminta, sudah dijinakkan. Nilainya dari URL; tanpa
     * dibatasi, "?month=99" membuat Carbon melompat ke tahun berikutnya.
     */
    private function periode(Request $request): array
    {
        $month = (int) ($request->input('month') ?: now()->month);
        $year  = (int) ($request->input('year')  ?: now()->year);

        return [max(1, min(12, $month)), max(2000, min(2100, $year))];
    }

    /**
     * Rekap produksi per produk untuk satu bulan — dipakai bersama layar, PDF,
     * dan Excel supaya ketiganya tidak bisa berselisih.
     */
    public static function rekapBulanan(int $month, int $year, ?string $deptFilter = null)
    {
        // Rentang [awal, awal bulan depan): MONTH()/YEAR() hanya ada di MySQL,
        // dan "<=" tanggal terakhir diam-diam membuang catatan hari itu bila
        // tanggal tersimpan berikut jam.
        $awal    = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $sesudah = Carbon::create($year, $month, 1)->startOfMonth()->addMonth()->toDateString();

        $dalamPeriode = fn ($q) => $q->where('production_date', '>=', $awal)
            ->where('production_date', '<', $sesudah)
            ->when($deptFilter, fn ($q) => $q->where('production_logs.department', $deptFilter));

        $baris = ProductionLog::select(
                'product_id',
                DB::raw('SUM(up_qty) as total_up'),
                DB::raw('SUM(bt_qty) as total_bt'),
                DB::raw('SUM(total_qty) as grand_total')
            )
            ->with('product.category')
            ->tap($dalamPeriode)
            ->groupBy('product_id')
            ->get();

        // Nomor urut sebulan penuh: dari catatan paling awal sampai terakhir.
        $nomor = ProductionLog::query()
            ->tap($dalamPeriode)
            ->whereNotNull('notes')
            ->orderBy('production_date')
            ->orderBy('created_at')
            ->get(['product_id', 'notes'])
            ->groupBy('product_id')
            ->map(fn ($isi) => NomorUrut::rentang($isi->pluck('notes')));

        $baris->each(function ($b) use ($nomor) {
            $b->nomor_urut = $nomor[$b->product_id] ?? '';
        });

        return UrutanProduksi::urutkan($baris);
    }

    public function index(Request $request)
    {
        [$month, $year] = $this->periode($request);
        $deptFilter = $this->developerDeptFilter($request);

        $report = self::rekapBulanan($month, $year, $deptFilter);

        $kategori = fn ($r, $kata) => str_contains(strtolower($r->product->category->name ?? ''), $kata);
        $query    = array_filter(['month' => $month, 'year' => $year, 'department' => $deptFilter]);

        return Inertia::render('Reports/Index', [
            'month'      => $month,
            'year'       => $year,
            'monthLabel' => Carbon::create(null, $month)->locale('id')->isoFormat('MMMM'),
            'months'     => collect(range(1, 12))->map(fn ($m) => [
                'value' => (string) $m,
                'label' => Carbon::create(null, $m)->locale('id')->isoFormat('MMMM'),
            ])->values(),
            'years'      => collect(range(now()->year - 2, now()->year))
                ->map(fn ($y) => ['value' => (string) $y, 'label' => (string) $y])->values(),
            'departments' => $this->departmentOptions()->map(fn ($d) => ['value' => $d, 'label' => $d])->values(),
            'deptFilter' => $deptFilter,
            'canExport'  => MenuAccess::can(auth()->user(), 'laporan.export'),
            'indexUrl'   => route('reports.index'),
            'dailyUrl'   => route('reports.daily'),
            'excelUrl'   => route('reports.export-excel', $query),
            'pdfUrl'     => route('reports.export-pdf', $query),
            'jumlahProduk' => $report->count(),
            'summary'    => [
                'up'    => (int) $report->sum('total_up'),
                'bt'    => (int) $report->sum('total_bt'),
                'tanki' => (float) $report->filter(fn ($r) => $kategori($r, 'tangki'))->sum('grand_total'),
                'cover' => (float) $report->filter(fn ($r) => $kategori($r, 'cover'))->sum('grand_total'),
                'total' => (float) $report->sum('grand_total'),
            ],
            'categories' => UrutanProduksi::kelompokkan($report)->map(fn ($rows, $nama) => [
                'name'  => (string) $nama,
                'total' => (float) $rows->sum('grand_total'),
                'rows'  => $rows->map(function ($r) {
                    $cat = strtolower($r->product->category->name ?? '');

                    return [
                        'id'        => $r->product_id,
                        'name'      => $r->product->name ?? '-',
                        'seriesKva' => $r->product->series_with_kva ?: null,
                        'badge'     => str_contains($cat, 'swasta') ? 'Swasta' : (str_contains($cat, 'type') ? 'Typetest' : 'PLN'),
                        'isChannel' => ($r->product->type ?? 'regular') === 'channel',
                        'up'        => (int) $r->total_up,
                        'bt'        => (int) $r->total_bt,
                        'total'     => (float) $r->grand_total,
                        'nomorUrut' => $r->nomor_urut ?: null,
                    ];
                })->values(),
            ])->values(),
        ]);
    }

    // Export PDF menggunakan DomPDF
    public function exportPdf(Request $request)
    {
        [$month, $year] = $this->periode($request);
        $deptFilter = $this->developerDeptFilter($request);

        $report    = self::rekapBulanan($month, $year, $deptFilter);
        $monthName = Carbon::create(null, $month)->translatedFormat('F');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', compact('report', 'month', 'year', 'monthName'));
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download("laporan-produksi-{$monthName}-{$year}.pdf");
    }

    // Export Excel
    public function exportExcel(Request $request)
    {
        [$month, $year] = $this->periode($request);
        $monthName = Carbon::create(null, $month)->translatedFormat('F');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ProductionReportExport($month, $year, $this->developerDeptFilter($request)),
            "laporan-produksi-{$monthName}-{$year}.xlsx"
        );
    }

    /** Catatan produksi satu hari, urut seperti di Riwayat Produksi. */
    private function logHarian(string $date, ?string $deptFilter)
    {
        $awal = Carbon::parse($date)->toDateString();

        return UrutanProduksi::urutkan(
            ProductionLog::with(['product.category', 'user'])
                ->where('production_date', '>=', $awal)
                ->where('production_date', '<', Carbon::parse($awal)->addDay()->toDateString())
                ->when($deptFilter, fn ($q) => $q->where('production_logs.department', $deptFilter))
                ->get()
        );
    }

    private function tanggalHarian(Request $request): string
    {
        try {
            return Carbon::parse($request->input('date') ?: now()->toDateString())->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    // Laporan harian (layar + cetak)
    public function daily(Request $request)
    {
        $date       = $this->tanggalHarian($request);
        $deptFilter = $this->developerDeptFilter($request);
        $logs       = $this->logHarian($date, $deptFilter);

        return Inertia::render('Reports/Daily', [
            'date'        => $date,
            'today'       => today()->toDateString(),
            'dateLabel'   => Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'printedAt'   => now()->locale('id')->isoFormat('D MMMM YYYY HH:mm'),
            'printedBy'   => auth()->user()->name,
            'indexUrl'    => route('reports.daily'),
            'pdfUrl'      => route('reports.daily-pdf', array_filter(['date' => $date, 'department' => $deptFilter])),
            'canExport'   => MenuAccess::can(auth()->user(), 'laporan.export'),
            'departments' => $this->departmentOptions()->map(fn ($d) => ['value' => $d, 'label' => $d])->values(),
            'deptFilter'  => $deptFilter,
            'totals'      => [
                'up'    => (int) $logs->sum('up_qty'),
                'bt'    => (int) $logs->sum('bt_qty'),
                'total' => (float) $logs->sum('total_qty'),
            ],
            'logs' => $logs->values()->map(fn ($l) => [
                'id'        => $l->id,
                'product'   => $l->product->name ?? '-',
                'seriesKva' => $l->product->series_with_kva ?: '-',
                'category'  => $l->product->category->name ?? '-',
                'up'        => (int) $l->up_qty,
                'bt'        => (int) $l->bt_qty,
                'total'     => (float) $l->total_qty,
                'notes'     => $l->notes ?: '-',
                'operator'  => $l->user->name ?? '-',
            ]),
        ]);
    }

    // Export PDF harian
    public function exportDailyPdf(Request $request)
    {
        $date       = $this->tanggalHarian($request);
        $logs       = $this->logHarian($date, $this->developerDeptFilter($request));
        $totalUp    = $logs->sum('up_qty');
        $totalBt    = $logs->sum('bt_qty');
        $grandTotal = $logs->sum('total_qty');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.daily-pdf',
            compact('logs', 'date', 'totalUp', 'totalBt', 'grandTotal'));

        return $pdf->download("laporan-harian-{$date}.pdf");
    }
}
