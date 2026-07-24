<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\ProductionTarget;
use App\Models\Scopes\DepartmentScope;
use App\Models\SchedulePhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductionTargetController extends Controller
{
    public function index(Request $request)
    {
        // Target tanpa batas waktu: 1 target aktif per produk.
        $rawTargets = ProductionTarget::with(['product'])->get();

        // Produksi kumulatif per produk (untuk hitung progres sejak target dibuat).
        // Target bersifat org-wide → aktual dihitung lintas departemen.
        $cumulative = ProductionLog::withoutGlobalScope(DepartmentScope::class)
            ->selectRaw('product_id, SUM(total_qty) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        // Bangun daftar target + aktual (progres sejak baseline)
        $targets  = collect();
        $actuals  = [];
        foreach ($rawTargets as $t) {
            $actual = $t->actualProduced((int) ($cumulative[$t->product_id] ?? 0));
            $actuals[$t->product_id] = $actual;
            $targets->push((object) [
                'id'         => $t->id,
                'product_id' => $t->product_id,
                'product'    => $t->product,
                'target_qty' => (int) $t->target_qty,
                'notes'      => $t->notes,
                'reached_at' => $t->reached_at,
            ]);
        }
        // Yang belum tercapai tampil dulu, lalu berdasarkan progres
        $targets = $targets->sortBy(fn ($t) => $actuals[$t->product_id] >= $t->target_qty ? 1 : 0)->values();

        $totalTarget = (int) $targets->sum('target_qty');
        // Aktual di-cap per produk supaya over-produksi tidak menutupi produk lain
        $totalActual = (int) $targets->sum(fn ($t) => min($actuals[$t->product_id], $t->target_qty));

        $products = Product::where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->orderByRaw('CAST(kva AS UNSIGNED)')
            ->orderBy('series')
            ->get();

        $date = today()->toDateString();

        // Foto jadwal tetap berlaku per minggu (dibersihkan tiap Senin via jadwal:clear)
        $weekStart     = now()->startOfWeek(\Carbon\Carbon::MONDAY);
        $weekEnd       = $weekStart->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
        $scheduleDate  = $weekStart->toDateString();
        $schedulePhoto = SchedulePhoto::with('uploader')->where('target_date', $scheduleDate)->first();

        return view('production.targets.index', compact(
            'targets', 'actuals', 'weekStart', 'weekEnd', 'date', 'scheduleDate', 'products',
            'totalTarget', 'totalActual', 'schedulePhoto'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id'    => ['required', 'exists:products,id'],
            'target_qty'    => ['required', 'integer', 'min:1', 'max:999999'],
            'notes'         => ['nullable', 'string', 'max:200'],
            'manual_series' => ['nullable', 'string', 'max:100'],
            'manual_kva'    => ['nullable', 'string', 'max:50'],
        ]);

        $productId = $request->product_id;
        $product   = Product::with('category')->find($productId);
        $isManual  = $product && $product->category && $product->category->has_manual_serial;

        if ($isManual && $request->filled('manual_series')) {
            preg_match('/^(\d{2})/', $request->manual_series, $ym);
            $tahun = isset($ym[1]) ? (2000 + (int)$ym[1]) : now()->year;

            $product = Product::firstOrCreate(
                ['category_id' => $product->category_id, 'series' => $request->manual_series, 'kva' => $request->manual_kva ?: null],
                ['name' => $product->name, 'type' => $product->type, 'tahun' => $tahun, 'is_active' => true]
            );
            $productId = $product->id;
        }

        // Baseline = total produksi kumulatif produk saat ini → titik nol progres (org-wide).
        $baseline = (int) ProductionLog::withoutGlobalScope(DepartmentScope::class)
            ->where('product_id', $productId)->sum('total_qty');

        $target = ProductionTarget::updateOrCreate(
            ['product_id' => $productId],
            [
                'target_qty'   => $request->target_qty,
                'baseline_qty' => $baseline,
                'notes'        => $request->notes,
                'target_date'  => today()->toDateString(),
                'reached_at'   => null,
                'created_by'   => auth()->id(),
            ]
        );

        if (!$product) {
            return back()->withErrors(['product_id' => 'Produk tidak ditemukan.']);
        }

        ActivityLog::record('create', "Set target produksi: {$product->name} = {$request->target_qty} unit", $target);

        return back()->with('success', "Target untuk {$product->name} berhasil disimpan.");
    }

    public function liveData(Request $request)
    {
        $targets = ProductionTarget::whereNotNull('product_id')->get();

        $cumulative = ProductionLog::withoutGlobalScope(DepartmentScope::class)
            ->selectRaw('product_id, SUM(total_qty) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $totalTarget = 0;
        $totalActualCapped = 0;
        $map = [];
        foreach ($targets as $t) {
            $actual = $t->actualProduced((int) ($cumulative[$t->product_id] ?? 0));
            $pct    = $t->target_qty > 0 ? min(round(($actual / $t->target_qty) * 100), 100) : 0;
            $map[$t->product_id] = [
                'actual'    => $actual,
                'target'    => (int) $t->target_qty,
                'pct'       => $pct,
                'done'      => $actual >= $t->target_qty,
                'remaining' => max(0, (int) $t->target_qty - $actual),
            ];
            $totalTarget       += (int) $t->target_qty;
            $totalActualCapped += min($actual, (int) $t->target_qty);
        }

        $overallPct = $totalTarget > 0 ? min(round(($totalActualCapped / $totalTarget) * 100), 100) : 0;

        return response()->json([
            'total_actual' => $totalActualCapped,
            'total_target' => (int) $totalTarget,
            'overall_pct'  => $overallPct,
            'actuals'      => $map,
            'updated_at'   => now()->timezone('Asia/Jakarta')->format('H:i:s'),
        ]);
    }

    public function uploadSchedulePhoto(Request $request)
    {
        $request->validate([
            'target_date' => ['required', 'date'],
            'photo'       => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        $date = $request->target_date;
        $file = $request->file('photo');

        // Guard: PHP marks error=0 but tmp_name can still be empty (e.g. antivirus
        // deleted the temp file between upload and store). Catch it here to avoid
        // an unhandled ValueError deep inside FilesystemAdapter::fopen('', 'r').
        if (!$file || !$file->isValid() || !$file->getPathname()) {
            return back()->withErrors(['photo' => 'File upload tidak valid atau sudah terhapus dari temp, coba upload ulang.']);
        }

        $old  = SchedulePhoto::where('target_date', $date)->first();

        try {
            $path = $file->store("schedule-photos/{$date}", 'public');
        } catch (\ValueError $e) {
            return back()->withErrors(['photo' => 'Gagal menyimpan file: path temp kosong. Coba upload ulang.']);
        }

        if ($path === false) {
            return back()->withErrors(['photo' => 'Gagal menyimpan foto. Coba lagi.']);
        }

        if ($old) {
            Storage::disk('public')->delete($old->file_path);
        }

        SchedulePhoto::updateOrCreate(
            ['target_date' => $date],
            ['file_path' => $path, 'uploaded_by' => auth()->id()]
        );

        return back()->with('success', 'Foto jadwal berhasil diupload.');
    }

    public function deleteSchedulePhoto(Request $request)
    {
        $photo = SchedulePhoto::where('target_date', $request->target_date)->first();
        if ($photo) {
            Storage::disk('public')->delete($photo->file_path);
            $photo->delete();
        }
        return back()->with('success', 'Foto jadwal berhasil dihapus.');
    }

    public function actualQty(Request $request)
    {
        $productId = (int) $request->input('product_id');

        if (!$productId) {
            return response()->json(['actual' => 0, 'target_qty' => null]);
        }

        // Total kumulatif semua tanggal = no. urut terakhir yang diproduksi (org-wide)
        $actual = (int) ProductionLog::withoutGlobalScope(DepartmentScope::class)
            ->where('product_id', $productId)->sum('total_qty');

        // Target aktif untuk produk ini (jika ada)
        $target = ProductionTarget::where('product_id', $productId)->value('target_qty');

        return response()->json([
            'actual'     => $actual,
            'target_qty' => $target ? (int) $target : null,
        ]);
    }

    public function destroy(ProductionTarget $productionTarget)
    {
        $info = $productionTarget->product?->name ?? 'produk #' . $productionTarget->product_id;
        $productionTarget->delete();
        ActivityLog::record('delete', "Hapus target produksi: {$info}");
        return back()->with('success', 'Target berhasil dihapus.');
    }
}
