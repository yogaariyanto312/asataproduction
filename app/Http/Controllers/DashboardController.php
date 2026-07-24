<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Models\Note;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\ProductionTarget;
use App\Models\ActivityLog;
use App\Services\HolidayService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(HolidayService $holidayService)
    {
        $today        = now()->toDateString();
        $currentMonth = now()->month;
        $currentYear  = now()->year;

        $holidays = $holidayService->getHolidays($currentYear);

        // Agenda/event kalender bulan ini — dikelompokkan per tanggal (Y-m-d)
        $calendarEvents = \App\Models\CalendarEvent::where('user_id', auth()->id())
            ->whereYear('event_date', $currentYear)
            ->whereMonth('event_date', $currentMonth)
            ->orderBy('event_date')->orderBy('created_at')
            ->get(['id', 'event_date', 'title', 'description', 'user_id', 'created_by_name'])
            ->groupBy(fn($e) => $e->event_date->toDateString());

        // Statistik utama
        $stats = [
            'today_total'    => ProductionLog::whereDate('production_date', $today)->sum('total_qty'),
            'today_entries'  => ProductionLog::whereDate('production_date', $today)->count(),
            'monthly_total'  => ProductionLog::whereMonth('production_date', $currentMonth)
                                    ->whereYear('production_date', $currentYear)->sum('total_qty'),
            'total_products' => Product::where('is_active', true)->count(),
        ];

        // Data grafik 7 hari terakhir (combo: total unit + entri + rata-rata)
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

        // Produk terbanyak per hari (untuk tooltip: "seri yang paling banyak diinput")
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
            ->map(fn($rows) => $rows->sortByDesc('total')->first());

        $chartData = collect();
        for ($i = 6; $i >= 0; $i--) {
            $d       = now()->subDays($i)->toDateString();
            $row     = $rawChart->get($d);
            $total   = $row ? (int) $row->total   : 0;
            $entries = $row ? (int) $row->entries : 0;
            $topRow  = $topPerDay->get($d);
            $chartData->push([
                'date'    => \Carbon\Carbon::parse($d)->locale('id')->isoFormat('dddd, D/M'),
                'total'   => $total,
                'entries' => $entries,
                'avg'     => $entries > 0 ? round($total / $entries, 1) : 0,
                'top'     => $topRow && $topRow->product ? $topRow->product->name : null,
                'top_qty' => $topRow ? (int) $topRow->total : 0,
            ]);
        }

        // Data grafik produk bulan ini — dikelompokkan per tipe (COVER / CHANNEL / TANGKI)
        $productChart = ProductionLog::select('product_id', DB::raw('SUM(total_qty) as total'))
            ->whereMonth('production_date', $currentMonth)
            ->whereYear('production_date', $currentYear)
            ->groupBy('product_id')
            ->with('product:id,name')
            ->get()
            ->groupBy(fn($item) => strtoupper(explode(' ', trim($item->product->name ?? 'Unknown'))[0]))
            ->map(fn($items, $key) => [
                'name'  => $key,
                'total' => $items->sum('total'),
            ])
            ->sortByDesc('total')
            ->values();

        // Produk spesifik paling banyak diproduksi bulan ini (untuk sorotan "dominan")
        $topProductRow = ProductionLog::select('product_id', DB::raw('SUM(total_qty) as total'))
            ->whereMonth('production_date', $currentMonth)
            ->whereYear('production_date', $currentYear)
            ->groupBy('product_id')
            ->with('product:id,name')
            ->orderByDesc('total')
            ->first();
        $topProduct = $topProductRow ? [
            'name'  => $topProductRow->product->name ?? '—',
            'total' => (int) $topProductRow->total,
        ] : null;

        // Top 5 operator hari ini
        $topOperators = ProductionLog::whereDate('production_date', $today)
            ->whereNotNull('operator_name')->where('operator_name', '!=', '')
            ->select('operator_name', DB::raw('SUM(total_qty) as total'))
            ->groupBy('operator_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Top 5 operator bulan ini
        $topOperatorsMonthly = ProductionLog::whereMonth('production_date', $currentMonth)
            ->whereYear('production_date', $currentYear)
            ->whereNotNull('operator_name')->where('operator_name', '!=', '')
            ->select('operator_name', DB::raw('SUM(total_qty) as total'))
            ->groupBy('operator_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Defect rate per produk bulan ini (hanya yang ada reject)
        $defectRates = ProductionLog::whereMonth('production_date', $currentMonth)
            ->whereYear('production_date', $currentYear)
            ->where('reject_qty', '>', 0)
            ->select('product_id',
                DB::raw('SUM(total_qty) as total_prod'),
                DB::raw('SUM(reject_qty) as total_reject')
            )
            ->groupBy('product_id')
            ->with('product:id,name')
            ->orderByDesc('total_reject')
            ->limit(7)
            ->get()
            ->map(fn($r) => [
                'name'   => $r->product->name ?? '-',
                'rate'   => round(($r->total_reject / max($r->total_prod + $r->total_reject, 1)) * 100, 1),
                'reject' => (int) $r->total_reject,
            ])
            ->values();

        // Input terbaru — hanya data hari ini
        $recentLogs = ProductionLog::with(['product.category', 'user'])
            ->whereDate('production_date', $today)
            ->orderByDesc('created_at')
            ->get();

        // Aktivitas terbaru
        $recentActivities = ActivityLog::with('user')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Catatan untuk widget dashboard
        $userId = auth()->id();
        $recentNotes = Note::with(['user:id,name', 'targetUser:id,name'])
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('target_user_id', $userId);
            })
            ->orderByRaw('is_done ASC, CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC, due_date ASC, created_at DESC')
            ->limit(5)
            ->get();

        // Target AKTIF (tanpa batas waktu) — progres dihitung sejak target dibuat (baseline).
        $cumulativeByProduct = ProductionLog::select('product_id', DB::raw('SUM(total_qty) as total'))
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $activeTargets = ProductionTarget::with('product')->whereNotNull('product_id')->get();

        $monthlyTargetsByProduct = $activeTargets
            ->map(function ($t) use ($cumulativeByProduct) {
                $actual = $t->actualProduced((int) ($cumulativeByProduct[$t->product_id] ?? 0));
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

        // Total target & aktual dari semua target aktif.
        // Aktual di-cap per produk supaya over-produksi tidak menutupi produk lain.
        $monthlyTargetTotal = (int) $monthlyTargetsByProduct->sum('target');
        $monthlyActualTotal = (int) $monthlyTargetsByProduct
            ->sum(fn ($p) => min($p['actual'], $p['target']));
        $monthlyTargetPct   = $monthlyTargetTotal > 0
            ? min(round(($monthlyActualTotal / $monthlyTargetTotal) * 100), 100)
            : null;

        // Alias dipakai live stats (target keseluruhan, bukan harian lagi)
        $todayTargets = $activeTargets;
        $totalTarget  = $monthlyTargetTotal;
        $totalActual  = $monthlyActualTotal;
        $targetPct    = $monthlyTargetPct;

        // Reject stats hari ini
        $rejectStats    = ProductionLog::whereDate('production_date', $today)
            ->selectRaw('SUM(reject_qty) as total_reject, SUM(total_qty) as total_prod')
            ->first();
        $todayReject    = (int) ($rejectStats->total_reject ?? 0);
        $todayRejectPct = ($rejectStats->total_prod + $todayReject) > 0
            ? round(($todayReject / ($rejectStats->total_prod + $todayReject)) * 100, 1)
            : 0;

        // Kategori untuk widget admin
        $categories = Category::withCount('products')->orderBy('name')->get();

        // Departemen untuk widget admin
        $departments = Department::withCount(['operators' => fn($q) => $q->where('role', 'operator')])
            ->orderBy('name')
            ->get();

        return view('dashboard.index', compact(
            'stats', 'chartData', 'productChart', 'topProduct', 'recentLogs', 'recentActivities',
            'categories', 'departments', 'recentNotes',
            'todayTargets', 'totalTarget', 'totalActual', 'targetPct',
            'monthlyTargetTotal', 'monthlyActualTotal', 'monthlyTargetPct', 'monthlyTargetsByProduct',
            'todayReject', 'todayRejectPct',
            'topOperators', 'defectRates', 'topOperatorsMonthly', 'holidays', 'calendarEvents'
        ));
    }

    public function liveStats()
    {
        $today        = now()->toDateString();
        $currentMonth = now()->month;
        $currentYear  = now()->year;

        $todayTotal   = (float) ProductionLog::whereDate('production_date', $today)->sum('total_qty');
        $todayEntries = ProductionLog::whereDate('production_date', $today)->count();
        $monthlyTotal = (float) ProductionLog::whereMonth('production_date', $currentMonth)
                            ->whereYear('production_date', $currentYear)->sum('total_qty');

        $rejectStats    = ProductionLog::whereDate('production_date', $today)
            ->selectRaw('SUM(reject_qty) as total_reject, SUM(total_qty) as total_prod')
            ->first();
        $todayReject    = (int) ($rejectStats->total_reject ?? 0);
        $todayRejectPct = ($rejectStats->total_prod + $todayReject) > 0
            ? round(($todayReject / ($rejectStats->total_prod + $todayReject)) * 100, 1)
            : 0;

        // Target aktif (tanpa batas waktu) — progres sejak baseline, di-cap per produk
        $cumulativeByProduct = ProductionLog::select('product_id', DB::raw('SUM(total_qty) as total'))
            ->groupBy('product_id')
            ->pluck('total', 'product_id');
        $activeTargets = ProductionTarget::whereNotNull('product_id')->get();
        $totalTarget   = (int) $activeTargets->sum('target_qty');
        $totalActual   = (int) $activeTargets->sum(
            fn ($t) => min($t->actualProduced((int) ($cumulativeByProduct[$t->product_id] ?? 0)), (int) $t->target_qty)
        );
        $targetPct     = $totalTarget > 0 ? min(round(($totalActual / $totalTarget) * 100), 100) : null;

        $topOperators = ProductionLog::whereDate('production_date', $today)
            ->whereNotNull('operator_name')->where('operator_name', '!=', '')
            ->select('operator_name', DB::raw('SUM(total_qty) as total'))
            ->groupBy('operator_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn($r) => ['name' => $r->operator_name, 'total' => (float) $r->total]);

        return response()->json([
            'today_total'    => $todayTotal,
            'today_entries'  => $todayEntries,
            'monthly_total'  => $monthlyTotal,
            'today_reject'   => $todayReject,
            'reject_pct'     => $todayRejectPct,
            'target_pct'     => $targetPct,
            'total_target'   => (int) $totalTarget,
            'total_actual'   => $totalActual,
            'top_operators'  => $topOperators,
            'updated_at'     => now()->timezone('Asia/Jakarta')->format('H:i:s'),
        ]);
    }
}
