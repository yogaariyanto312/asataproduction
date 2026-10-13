<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductionTargetController;
use App\Models\ProductionTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API Target Produksi untuk aplikasi Android (logika dari controller web). */
class TargetController extends Controller
{
    public function __construct(private ProductionTargetController $web)
    {
    }

    public function index(): JsonResponse
    {
        $d = $this->web->dataTarget();

        return response()->json([
            'canEdit'       => $d['canEdit'],
            'canDelete'     => $d['canDelete'],
            'weekLabel'     => $d['weekLabel'],
            'totals'        => $d['totals'],
            'targets'       => $d['targets'],
            'productGroups' => $d['productGroups'],
            'schedulePhoto' => $d['schedulePhoto'],
        ]);
    }

    public function simpan(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $this->web->simpanTarget($request)]);
    }

    public function hapus(ProductionTarget $productionTarget): JsonResponse
    {
        $this->web->hapusTarget($productionTarget);

        return response()->json(['success' => true, 'message' => 'Target berhasil dihapus.']);
    }
}
