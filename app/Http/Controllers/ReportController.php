<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Rekap produksi per produk untuk satu bulan.
     * Dipakai bersama oleh index() dan exportPdf() agar tidak duplikasi query.
     */
    private function buildMonthlyReport(int $month, int $year, ?string $deptFilter = null)
    {
        // Filter departemen di subquery no. urut terakhir (developer saja).
        $deptSub  = $deptFilter ? ' AND p2.department = ?' : '';
        $bindings = $deptFilter ? [$month, $year, $deptFilter] : [$month, $year];

        return ProductionLog::select(
                'product_id',
                DB::raw('SUM(shift1_qty) as total_shift1'),
                DB::raw('SUM(shift2_qty) as total_shift2'),
                DB::raw('SUM(shift3_qty) as total_shift3'),
                DB::raw('SUM(total_qty) as grand_total')
            )
            // Subquery no. urut terakhir — pakai binding parameter (aman dari injeksi)
            ->selectRaw(
                "(SELECT notes FROM production_logs p2
                   WHERE p2.product_id = production_logs.product_id
                     AND MONTH(p2.production_date) = ?
                     AND YEAR(p2.production_date)  = ?{$deptSub}
                   ORDER BY p2.production_date DESC, p2.created_at DESC
                   LIMIT 1) as last_notes",
                $bindings
            )
            ->with('product.category')
            ->whereMonth('production_date', $month)
            ->whereYear('production_date', $year)
            ->when($deptFilter, fn($q) => $q->where('production_logs.department', $deptFilter))
            ->groupBy('product_id')
            ->orderByDesc('grand_total')
            ->get();
    }

    public function index(Request $request)
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year  ?? now()->year);

        $deptFilter  = $this->developerDeptFilter($request);
        $departments = $this->departmentOptions();

        // Rekap per produk per bulan
        $report = $this->buildMonthlyReport($month, $year, $deptFilter);

        // Rekap harian bulan ini
        $dailyReport = ProductionLog::select(
                DB::raw('DATE(production_date) as date'),
                DB::raw('SUM(total_qty) as total')
            )
            ->whereMonth('production_date', $month)
            ->whereYear('production_date', $year)
            ->when($deptFilter, fn($q) => $q->where('production_logs.department', $deptFilter))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $products = Product::where('is_active', true)->orderBy('name')->get();
        $months   = collect(range(1, 12))->mapWithKeys(fn($m) => [$m => \Carbon\Carbon::create(null, $m)->translatedFormat('F')]);
        $years    = range(now()->year - 2, now()->year);

        return view('reports.index', compact('report', 'dailyReport', 'products', 'months', 'years', 'month', 'year', 'departments', 'deptFilter'));
    }

    // Export PDF menggunakan DomPDF
    public function exportPdf(Request $request)
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year  ?? now()->year);

        $report = $this->buildMonthlyReport($month, $year, $this->developerDeptFilter($request));

        $monthName = \Carbon\Carbon::create(null, $month)->translatedFormat('F');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', compact('report', 'month', 'year', 'monthName'));
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download("laporan-produksi-{$monthName}-{$year}.pdf");
    }

    // Export Excel
    public function exportExcel(Request $request)
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year  ?? now()->year);
        $monthName = \Carbon\Carbon::create(null, $month)->translatedFormat('F');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ProductionReportExport($month, $year, $this->developerDeptFilter($request)),
            "laporan-produksi-{$monthName}-{$year}.xlsx"
        );
    }

    // Laporan harian (print)
    public function daily(Request $request)
    {
        $date = $request->date ?? now()->toDateString();
        $deptFilter = $this->developerDeptFilter($request);

        $logs = ProductionLog::with(['product.category', 'user'])
            ->whereDate('production_date', $date)
            ->when($deptFilter, fn($q) => $q->where('production_logs.department', $deptFilter))
            ->orderBy('product_id')
            ->get();

        $totalShift1 = $logs->sum('shift1_qty');
        $totalShift2 = $logs->sum('shift2_qty');
        $totalShift3 = $logs->sum('shift3_qty');
        $grandTotal  = $logs->sum('total_qty');

        return view('reports.daily', compact('logs', 'date', 'totalShift1', 'totalShift2', 'totalShift3', 'grandTotal', 'deptFilter'));
    }

    // Export PDF harian
    public function exportDailyPdf(Request $request)
    {
        $date = $request->date ?? now()->toDateString();
        $deptFilter = $this->developerDeptFilter($request);

        $logs = ProductionLog::with(['product.category', 'user'])
            ->whereDate('production_date', $date)
            ->when($deptFilter, fn($q) => $q->where('production_logs.department', $deptFilter))
            ->orderBy('product_id')
            ->get();

        $totalShift1 = $logs->sum('shift1_qty');
        $totalShift2 = $logs->sum('shift2_qty');
        $totalShift3 = $logs->sum('shift3_qty');
        $grandTotal  = $logs->sum('total_qty');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.daily-pdf',
            compact('logs', 'date', 'totalShift1', 'totalShift2', 'totalShift3', 'grandTotal'));

        return $pdf->download("laporan-harian-{$date}.pdf");
    }
}
