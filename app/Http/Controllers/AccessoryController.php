<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Http\Request;

class AccessoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Accessory::with(['product', 'user'])
            ->orderByDesc('accessory_date')
            ->orderByDesc('created_at');

        if ($search = trim((string) $request->input('search'))) {
            $query->search($search);
        }

        if ($month = $request->input('month')) {
            $query->whereMonth('accessory_date', (int) $month);
        }

        if ($year = $request->input('year')) {
            $query->whereYear('accessory_date', (int) $year);
        }

        [$departments, $deptFilter] = $this->departmentFilter($request, $query, 'accessories.department');

        $accessories = $query->paginate(20)->withQueryString();

        $products = Product::where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->orderByRaw('CAST(kva AS UNSIGNED)')
            ->orderBy('series')
            ->get();

        $years = Accessory::selectRaw('DISTINCT YEAR(accessory_date) as yr')
            ->orderByDesc('yr')->pluck('yr');

        return view('accessories.index', compact('accessories', 'products', 'years', 'departments', 'deptFilter'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'     => ['nullable', 'exists:products,id'],
            'accessory_date' => ['required', 'date'],
            'name'           => ['required', 'string', 'max:150'],
            'serial_number'  => ['nullable', 'string', 'max:150'],
            'qty'            => ['required', 'integer', 'min:1'],
            'unit'           => ['nullable', 'string', 'max:30'],
            'recipient'      => ['nullable', 'string', 'max:150'],
            'purpose'        => ['nullable', 'string', 'max:255'],
            'keterangan'     => ['nullable', 'string', 'max:1000'],
        ]);

        $data['user_id']       = auth()->id();
        $data['operator_name'] = auth()->user()->name;

        $accessory = Accessory::create($data);

        ActivityLog::record(
            'create',
            "Input aksesoris keluar: {$accessory->name} ({$accessory->qty} {$accessory->unit})",
            $accessory
        );

        return redirect()->route('accessories.index')
            ->with('success', "Data aksesoris berhasil disimpan. ({$accessory->qty} {$accessory->unit})");
    }

    public function destroy(Accessory $accessory)
    {
        abort_unless(
            \App\Support\MenuAccess::can(auth()->user(), 'aksesoris.delete'),
            403
        );

        $info = $accessory->name;
        $accessory->delete();
        ActivityLog::record('delete', "Hapus aksesoris keluar: {$info}");

        return redirect()->route('accessories.index')
            ->with('success', 'Data aksesoris berhasil dihapus.');
    }
}
