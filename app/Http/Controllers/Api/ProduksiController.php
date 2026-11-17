<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductionLogController;
use App\Http\Requests\ProductionLogRequest;
use App\Models\Department;
use App\Models\Product;
use App\Models\ProductionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API produksi untuk aplikasi Android. Semua aturan bisnis (gabung entri,
 * seri manual, departemen, nomor urut, reject) memakai logika controller web
 * yang sama — di sini hanya jembatan JSON-nya.
 */
class ProduksiController extends Controller
{
    public function __construct(private ProductionLogController $web)
    {
    }

    public function dashboard(): JsonResponse
    {
        $dash = app(DashboardController::class);
        $ringkas = $dash->liveStats()->getData(true);

        return response()->json($ringkas + [
            'total_products' => Product::where('is_active', true)->count(),
            'month_label'    => now()->locale('id')->isoFormat('MMMM YYYY'),
            'today_label'    => now()->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'grafik'         => $dash->grafikMingguan(),
        ]);
    }

    /** Bahan form Input Produksi: produk, kategori reject, departemen (khusus developer). */
    public function opsi(Request $request): JsonResponse
    {
        $dev = \App\Models\BotSetting::departemenUntuk($request->user());

        return response()->json([
            'today'            => today()->toDateString(),
            'products'         => $this->web->opsiProduk(),
            'rejectCategories' => collect(ProductionLog::$rejectCategories)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'departments'      => $dev ? Department::where('is_active', true)->orderBy('name')->pluck('name') : [],
        ]);
    }

    public function lastSerial(Request $request): JsonResponse
    {
        return $this->web->lastSerial($request);
    }

    public function riwayat(Request $request): JsonResponse
    {
        return response()->json($this->web->riwayatData($request));
    }

    public function detail(ProductionLog $productionLog): JsonResponse
    {
        $productionLog->load('product.category');
        $p = $productionLog->product;

        return response()->json([
            'id'              => $productionLog->id,
            'product_id'      => $productionLog->product_id,
            'product'         => $p?->name,
            'series_kva'      => $p?->series_with_kva ?: null,
            'is_channel'      => (bool) $p?->isChannel(),
            'production_date' => $productionLog->production_date?->toDateString(),
            'operator_name'   => $productionLog->operator_name,
            'department'      => $productionLog->department,
            'up_qty'          => (int) $productionLog->up_qty,
            'bt_qty'          => (int) $productionLog->bt_qty,
            'total_qty'       => (float) $productionLog->total_qty,
            'notes'           => $productionLog->notes,
            'keterangan'      => $productionLog->keterangan,
            'reject_qty'      => (int) $productionLog->reject_qty,
            'reject_category' => $productionLog->reject_category,
            'reject_notes'    => $productionLog->reject_notes,
            'created_at'      => $productionLog->created_at?->toIso8601String(),
        ]);
    }

    public function simpan(ProductionLogRequest $request)
    {
        // store() membalas JSON untuk request yang meminta JSON.
        return $this->web->store($request);
    }

    public function perbarui(ProductionLogRequest $request, ProductionLog $productionLog): JsonResponse
    {
        $this->web->perbaruiLog($request, $productionLog);

        return response()->json(['success' => true, 'message' => 'Data produksi berhasil diperbarui.']);
    }

    public function hapus(Request $request, ProductionLog $productionLog)
    {
        return $this->web->destroy($request, $productionLog);
    }

    public function reject(Request $request, ProductionLog $productionLog): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $this->web->prosesReject($request, $productionLog)]);
    }
}
