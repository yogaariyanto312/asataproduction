<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Models\Note;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\ProductionTarget;
use App\Services\HolidayService;
use App\Support\FaseBulan;
use App\Support\HtmlCatatan;
use App\Support\MenuAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Dashboard — mengikuti Production-QC-Logging-System: kartu statistik + Aksi
 * Cepat, Target Aktif | Reject Hari Ini, Trend Produksi | Produk per Tipe,
 * Top Operator hari ini | bulan ini, Catatan | Kalender, Input Produksi
 * Terbaru (dikelompokkan per kategori), Log Aktivitas bergaya konsol.
 * Halamannya React (Inertia); potongan yang dimuat ulang (kalender per bulan,
 * log aktivitas) dikirim sebagai JSON.
 */
class DashboardController extends Controller
{
    /** Jumlah baris log yang dikirim; yang tampil 12, sisanya digulir. */
    private const BATAS_LOG = 100;

    public function index(HolidayService $holidayService)
    {
        [$awalBulan, $bulanDepan] = $this->rentangBulan();
        $user = auth()->user();

        $ringkas = $this->ringkasanHariIni();

        // Data grafik 7 hari terakhir (bar total unit + garis rata-rata/input)
        $weekStart = now()->subDays(6)->toDateString();
        $rawChart = ProductionLog::select(
                DB::raw('DATE(production_date) as date'),
                DB::raw('SUM(total_qty) as total'),
                DB::raw('COUNT(*) as entries')
            )
            ->where('production_date', '>=', $weekStart)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Produk terbanyak per hari (tooltip: "seri yang paling banyak diinput")
        $topPerDay = ProductionLog::select(
                DB::raw('DATE(production_date) as date'),
                'product_id',
                DB::raw('SUM(total_qty) as total')
            )
            ->where('production_date', '>=', $weekStart)
            ->groupBy('date', 'product_id')
            ->with('product:id,name')
            ->get()
            ->groupBy('date')
            ->map(fn ($rows) => $rows->sortByDesc('total')->first());

        $chartData = collect();
        for ($i = 6; $i >= 0; $i--) {
            $d       = now()->subDays($i)->toDateString();
            $row     = $rawChart->get($d);
            $total   = $row ? (int) $row->total : 0;
            $entries = $row ? (int) $row->entries : 0;
            $topRow  = $topPerDay->get($d);
            $chartData->push([
                'date'    => Carbon::parse($d)->locale('id')->isoFormat('dddd, D/M'),
                'total'   => $total,
                'entries' => $entries,
                'avg'     => $entries > 0 ? round($total / $entries, 1) : 0,
                'top'     => $topRow && $topRow->product ? $topRow->product->name : null,
                'top_qty' => $topRow ? (int) $topRow->total : 0,
            ]);
        }

        // Produk bulan ini dikelompokkan per tipe (kata pertama nama produk)
        $productChart = ProductionLog::select('product_id', DB::raw('SUM(total_qty) as total'))
            ->where('production_date', '>=', $awalBulan)
            ->where('production_date', '<', $bulanDepan)
            ->groupBy('product_id')
            ->with('product:id,name')
            ->get()
            ->groupBy(fn ($item) => strtoupper(explode(' ', trim($item->product->name ?? 'Unknown'))[0]))
            ->map(fn ($items, $key) => ['name' => $key, 'total' => (int) $items->sum('total')])
            ->sortByDesc('total')
            ->values();

        $topOperatorsMonthly = ProductionLog::where('production_date', '>=', $awalBulan)
            ->where('production_date', '<', $bulanDepan)
            ->whereNotNull('operator_name')->where('operator_name', '!=', '')
            ->select('operator_name', DB::raw('SUM(total_qty) as total'))
            ->groupBy('operator_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $progres = $this->progresTarget();

        return Inertia::render('Dashboard', [
            'stats' => [
                'today_total'    => $ringkas['today_total'],
                'today_entries'  => $ringkas['today_entries'],
                'monthly_total'  => $ringkas['monthly_total'],
                'total_products' => Product::where('is_active', true)->count(),
            ],
            'monthLabel'   => now()->locale('id')->isoFormat('MMMM YYYY'),
            'chartData'    => $chartData->values(),
            'productChart' => $productChart,

            'target' => [
                'total'    => $progres['total'],
                'actual'   => $progres['actual'],
                'pct'      => $progres['pct'],
                'products' => $progres['products'],
            ],
            'reject' => ['today' => $ringkas['today_reject'], 'pct' => $ringkas['reject_pct']],

            'topOperators'        => $this->bentukOperator($ringkas['top_operators']),
            'topOperatorsMonthly' => $this->bentukOperator($topOperatorsMonthly),

            'recentLogs' => $this->inputTerbaru(),
            'notes'      => $this->catatanTerbaru(),
            'calendar'   => $this->kalender(now()->startOfMonth(), $holidayService),

            // null = tidak berhak melihat log aktivitas (izin dashboard.aktivitas).
            'activities' => MenuAccess::can($user, 'dashboard.aktivitas') ? $this->logAktivitas() : null,

            'can' => [
                'privileged' => $user->isPrivileged(),
                'quick'      => ! $user->isVisitor(),
                'input'      => ! $user->isVisitor() && ! $user->isSupervisor(),
            ],
            'urls' => [
                'live'       => route('api.dashboard.live'),
                'calendar'   => route('api.dashboard.calendar'),
                'aktivitas'  => route('api.dashboard.aktivitas'),
                'eventStore' => route('calendar.events.store'),
                'eventBase'  => url('/calendar-events'),
                'notes'      => route('notes.index'),
                'production' => route('production.index'),
                'create'     => route('production.create'),
                'daily'      => route('reports.daily'),
                'targets'    => route('production.targets.index'),
            ],
        ]);
    }

    public function liveStats()
    {
        $ringkas = $this->ringkasanHariIni();
        $progres = $this->progresTarget();

        return response()->json([
            'today_total'   => $ringkas['today_total'],
            'today_entries' => $ringkas['today_entries'],
            'monthly_total' => $ringkas['monthly_total'],
            'today_reject'  => $ringkas['today_reject'],
            'reject_pct'    => $ringkas['reject_pct'],
            'target_pct'    => $progres['pct'],
            'total_target'  => $progres['total'],
            'total_actual'  => $progres['actual'],
            'top_operators' => $this->bentukOperator($ringkas['top_operators']),
            'updated_at'    => now()->timezone('Asia/Jakarta')->format('H:i:s'),
        ]);
    }

    /**
     * Isi kartu Log Aktivitas saja, untuk penyegaran berkala tanpa memuat ulang
     * dashboard. Izinnya dijaga lewat Hak Akses (dashboard.aktivitas).
     */
    public function aktivitas()
    {
        return response()->json($this->logAktivitas());
    }

    /**
     * Satu bulan kalender, dipakai tombol bulan sebelumnya/berikutnya. Hanya
     * kalendernya yang dikirim — memuat ulang dashboard cuma untuk pindah bulan
     * berarti menghitung semua statistik lagi tanpa alasan.
     */
    public function calendar(Request $request, HolidayService $holidayService)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        // '!' mengosongkan bagian yang tidak disebut formatnya. Tanpa itu harinya
        // diisi tanggal HARI INI: dibuka tanggal 30, "2028-02" menjadi 30 Feb →
        // meluber ke Maret, dan kalender menampilkan bulan yang salah.
        $bulan = Carbon::createFromFormat('!Y-m', $data['month'])->startOfMonth();

        // Rentang wajar: di luar ini tidak ada data, dan tiap tahun baru memicu
        // panggilan Google Calendar API (kuota).
        $batasBawah = now()->copy()->subYears(5)->startOfMonth();
        $batasAtas  = now()->copy()->addYears(5)->startOfMonth();
        abort_if($bulan->lt($batasBawah) || $bulan->gt($batasAtas), 422, 'Bulan di luar jangkauan.');

        return response()->json($this->kalender($bulan, $holidayService));
    }

    // ── Pembentuk data ──────────────────────────────────────────────────────

    /**
     * Kalender satu bulan: tanggal + hari libur + fase bulan + agenda milik
     * user yang login. Posisi tooltip (rata kiri/kanan di kolom pinggir) ikut
     * dihitung di sini supaya tampilannya tidak perlu menebak.
     */
    private function kalender(Carbon $bulan, HolidayService $holidayService): array
    {
        $bulan     = $bulan->copy()->startOfMonth();
        $hariIni   = now();
        $bulanIni  = $bulan->isSameMonth($hariIni);
        $libur     = $holidayService->getHolidays((int) $bulan->year);
        $fase      = FaseBulan::untukBulan((int) $bulan->year, (int) $bulan->month);
        $user      = auth()->user();
        $boleh     = MenuAccess::can($user, 'dashboard.agenda');
        $agenda    = $boleh ? $this->agendaBulan($bulan) : collect();

        $hari = [];
        for ($d = 1; $d <= $bulan->daysInMonth; $d++) {
            $tgl = $bulan->copy()->day($d);
            $key = $tgl->toDateString();
            $dow = ($tgl->dayOfWeek + 6) % 7; // Senin = 0

            $hari[] = [
                'date'    => $key,
                'day'     => $d,
                'dow'     => $dow,
                'weekend' => $dow >= 5,
                'today'   => $bulanIni && $d === (int) $hariIni->day,
                'label'   => $tgl->locale('id')->isoFormat('DD MMMM YYYY'),
                'holiday' => $libur->get($key)['name'] ?? null,
                'phase'   => $fase[$key] ?? null,
                'tip'     => $dow <= 1 ? 'kiri' : ($dow >= 5 ? 'kanan' : 'tengah'),
                'events'  => ($agenda->get($key) ?? collect())->map(fn ($e) => [
                    'id'          => $e->id,
                    'title'       => $e->title,
                    'description' => $e->description,
                    'by'          => $e->created_by_name,
                    'can_delete'  => $user->id === $e->user_id || $user->isPrivileged(),
                ])->values(),
            ];
        }

        return [
            'month'     => $bulan->format('Y-m'),
            'label'     => $bulan->locale('id')->isoFormat('MMMM YYYY'),
            'sub'       => $bulanIni
                ? $hariIni->locale('id')->isoFormat('dddd, DD MMMM YYYY')
                : 'Bulan lain · ' . $bulan->locale('id')->isoFormat('MMMM YYYY'),
            'isCurrent' => $bulanIni,
            'current'   => $hariIni->format('Y-m'),
            'prev'      => $bulan->copy()->subMonth()->format('Y-m'),
            'next'      => $bulan->copy()->addMonth()->format('Y-m'),
            'offset'    => ($bulan->dayOfWeek + 6) % 7,
            'canManage' => $boleh,
            'days'      => $hari,
        ];
    }

    /** Agenda milik user yang sedang login pada satu bulan, per tanggal. */
    private function agendaBulan(Carbon $bulan)
    {
        return CalendarEvent::where('user_id', auth()->id())
            ->where('event_date', '>=', $bulan->copy()->startOfMonth()->toDateString())
            ->where('event_date', '<', $bulan->copy()->startOfMonth()->addMonth()->toDateString())
            ->orderBy('event_date')->orderBy('created_at')
            ->get(['id', 'event_date', 'title', 'description', 'user_id', 'created_by_name'])
            ->groupBy(fn ($e) => $e->event_date->toDateString());
    }

    /**
     * Log aktivitas terbaru. orderByDesc id, bukan created_at: beberapa aksi
     * bisa jatuh di detik yang sama dan urutannya harus urutan kejadian.
     * Teks dikirim mentah — React yang meng-escape saat menampilkan.
     */
    private function logAktivitas(): array
    {
        $hariIni = now()->toDateString();
        $kemarin = now()->subDay()->toDateString();

        $items = ActivityLog::with('user:id,name,role')
            ->orderByDesc('id')
            ->limit(self::BATAS_LOG)
            ->get()
            ->map(function ($log) use ($hariIni, $kemarin) {
                $waktu = $log->created_at?->timezone(config('app.timezone'));
                $tgl   = $waktu?->toDateString();

                return [
                    'id'     => $log->id,
                    'action' => $log->action,
                    'user'   => $log->user?->name ?? 'Sistem',
                    'role'   => $log->user?->role,
                    'desc'   => $log->description,
                    'time'   => $waktu?->format('H:i:s'),
                    'iso'    => $waktu?->toIso8601String(),
                    'date'   => $tgl,
                    'day'    => $tgl === $hariIni ? 'Hari ini'
                        : ($tgl === $kemarin ? 'Kemarin' : $waktu?->locale('id')->isoFormat('dddd, DD MMMM YYYY')),
                ];
            })
            ->values();

        return ['terbaru' => $items->first()['id'] ?? 0, 'items' => $items];
    }

    /** Catatan untuk kartu dashboard + data lengkap untuk modal "Lihat detail". */
    private function catatanTerbaru()
    {
        $userId = auth()->id();

        return Note::with(['user:id,name', 'targetUser:id,name'])
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('target_user_id', $userId);
            })
            ->orderByRaw('is_done ASC, CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC, due_date ASC, created_at DESC')
            ->limit(5)
            ->get()
            ->map(fn ($n) => [
                'id'       => $n->id,
                'title'    => $n->title,
                'content'  => HtmlCatatan::teks($n->content),
                'snippet'  => Str::limit(HtmlCatatan::teks($n->content), 80),
                // Sudah disaring HtmlCatatan (tag & gaya diseleksi) → aman dirender.
                'html'     => HtmlCatatan::tampil($n->content),
                'color'    => $n->color ?: 'slate',
                'done'     => (bool) $n->is_done,
                'due'      => $n->due_date?->locale('id')->isoFormat('DD MMM YYYY'),
                'dueShort' => $n->due_date?->locale('id')->isoFormat('DD MMM'),
                'late'     => $n->due_date ? ($n->due_date->isPast() && ! $n->is_done) : false,
                'by'       => $n->user->name ?? null,
                'to'       => $n->target_user_id && $n->target_user_id !== $userId ? ($n->targetUser->name ?? '-') : null,
                'made'     => $n->created_at->locale('id')->isoFormat('DD MMM YYYY'),
                'madeFull' => $n->created_at->locale('id')->isoFormat('DD MMM YYYY HH:mm'),
                'photo'    => $n->photo_url,
            ])
            ->values();
    }

    /**
     * Input produksi hari ini, dikelompokkan per kategori (Channel → Cover →
     * Tangki → lainnya), tiap kategori urut KVA lalu seri.
     */
    private function inputTerbaru(): ?array
    {
        $logs = $this->hariIni(ProductionLog::with(['product.category', 'user']))
            ->orderByDesc('created_at')
            ->get();

        if ($logs->isEmpty()) {
            return null;
        }

        $urutKategori = ['Channel' => 0, 'Cover' => 1, 'Tangki' => 2];

        $kelompok = $logs
            ->groupBy(function ($l) {
                $n = strtolower($l->product->category->name ?? '');
                if (str_contains($n, 'channel')) return 'Channel';
                if (str_contains($n, 'cover'))   return 'Cover';
                if (str_contains($n, 'tangki'))  return 'Tangki';

                return $l->product->category->name ?? 'Lainnya';
            })
            ->sortBy(fn ($isi, $nama) => $urutKategori[$nama] ?? 99)
            ->map(function ($isi, $nama) {
                $low  = strtolower($nama);
                $nada = str_contains($low, 'channel') ? 'blue'
                    : (str_contains($low, 'cover') ? 'emerald' : (str_contains($low, 'tangki') ? 'amber' : 'slate'));

                return [
                    'name'  => $nama,
                    'tone'  => $nada,
                    'total' => (float) $isi->sum('total_qty'),
                    'count' => $isi->count(),
                    'logs'  => $isi->sortBy([
                        fn ($a, $b) => (float) ($a->product->kva ?? 0) <=> (float) ($b->product->kva ?? 0),
                        fn ($a, $b) => strcmp((string) ($a->product->series ?? ''), (string) ($b->product->series ?? '')),
                    ])->map(function ($log) {
                        $kat = strtolower($log->product->category->name ?? '');

                        return [
                            'id'        => $log->id,
                            'name'      => preg_replace('/\s+(typetest|swasta|pln)\b/i', '', $log->product->name ?? '-'),
                            'seriesKva' => $log->product?->series_with_kva ?: null,
                            'badge'     => str_contains($kat, 'swasta') ? 'swasta' : (str_contains($kat, 'type') ? 'typetest' : null),
                            'notes'     => $log->notes ? Str::limit($log->notes, 35) : null,
                            'user'      => $log->user->name ?? '-',
                            'channel'   => ($log->product->type ?? 'regular') === 'channel',
                            'up'        => (int) $log->up_qty,
                            'bt'        => (int) $log->bt_qty,
                            'total'     => (float) $log->total_qty,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return [
            'dayName' => now()->locale('id')->isoFormat('dddd'),
            'date'    => now()->locale('id')->isoFormat('DD MMMM YYYY'),
            'total'   => (float) $logs->sum('total_qty'),
            'count'   => $logs->count(),
            'groups'  => $kelompok,
        ];
    }

    private function bentukOperator($rows)
    {
        return $rows->map(fn ($r) => ['name' => $r->operator_name, 'total' => (float) $r->total])->values();
    }

    // ── Pembantu bersama index() & liveStats() ──────────────────────────────

    /**
     * [awal hari ini, awal besok). Rentang, bukan whereDate(): fungsi DATE()
     * membuat index production_date tidak terpakai, dan di SQLite tanggal bisa
     * tersimpan berikut jam sehingga perbandingan "=" meleset.
     */
    private function rentangHari(): array
    {
        return [now()->startOfDay()->toDateString(), now()->startOfDay()->addDay()->toDateString()];
    }

    /** [awal bulan ini, awal bulan depan) — pengganti whereMonth()/whereYear(). */
    private function rentangBulan(): array
    {
        return [now()->startOfMonth()->toDateString(), now()->startOfMonth()->addMonth()->toDateString()];
    }

    private function hariIni($query)
    {
        [$awal, $besok] = $this->rentangHari();

        return $query->where('production_date', '>=', $awal)->where('production_date', '<', $besok);
    }

    /** Angka hari ini & bulan ini yang dipakai kartu statistik dan polling. */
    private function ringkasanHariIni(): array
    {
        [$awalBulan, $bulanDepan] = $this->rentangBulan();

        // Total, jumlah entri, dan reject hari ini dalam SATU query.
        $hari = $this->hariIni(ProductionLog::query())
            ->selectRaw('COALESCE(SUM(total_qty),0) as total, COUNT(*) as entri, COALESCE(SUM(reject_qty),0) as reject')
            ->first();

        $total  = (float) $hari->total;
        $reject = (int) $hari->reject;

        $topOperators = $this->hariIni(ProductionLog::query())
            ->whereNotNull('operator_name')->where('operator_name', '!=', '')
            ->select('operator_name', DB::raw('SUM(total_qty) as total'))
            ->groupBy('operator_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return [
            'today_total'   => $total,
            'today_entries' => (int) $hari->entri,
            'monthly_total' => (float) ProductionLog::where('production_date', '>=', $awalBulan)
                ->where('production_date', '<', $bulanDepan)->sum('total_qty'),
            'today_reject'  => $reject,
            'reject_pct'    => ($total + $reject) > 0 ? round(($reject / ($total + $reject)) * 100, 1) : 0,
            'top_operators' => $topOperators,
        ];
    }

    /**
     * Progres target aktif. Target berlaku untuk seluruh pabrik, jadi produksi
     * kumulatif dihitung LINTAS departemen — sama seperti halaman Target
     * Produksi dan baseline_qty yang juga diambil lintas departemen. Dulu
     * dashboard memakai jumlah departemen user sendiri dikurangi baseline
     * lintas departemen, sehingga progres user non-developer bisa jauh lebih
     * kecil (bahkan 0) dari angka di halaman Target. Hanya produk yang punya
     * target yang dijumlahkan, bukan seluruh riwayat semua produk.
     */
    private function progresTarget(): array
    {
        $targets = ProductionTarget::with('product')->whereNotNull('product_id')->get();

        $kumulatif = $targets->isEmpty() ? collect() : ProductionLog::withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)
            ->whereIn('product_id', $targets->pluck('product_id')->unique())
            ->select('product_id', DB::raw('SUM(total_qty) as total'))
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $products = $targets
            ->map(function ($t) use ($kumulatif) {
                $actual = $t->actualProduced((int) ($kumulatif[$t->product_id] ?? 0));

                return [
                    'name'       => $t->product->name ?? '-',
                    'series_kva' => $t->product?->series_with_kva ?: null,
                    'target'     => (int) $t->target_qty,
                    'actual'     => $actual,
                    'done'       => $actual >= $t->target_qty,
                ];
            })
            ->sortBy('done')
            ->values();

        // Aktual di-cap per produk supaya over-produksi tidak menutupi produk lain.
        $total  = (int) $products->sum('target');
        $actual = (int) $products->sum(fn ($p) => min($p['actual'], $p['target']));

        return [
            'products' => $products,
            'total'    => $total,
            'actual'   => $actual,
            'pct'      => $total > 0 ? min(round(($actual / $total) * 100), 100) : null,
        ];
    }
}
