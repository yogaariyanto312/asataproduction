<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with('category')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('series', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->tahun, fn($q) => $q->where('tahun', $request->tahun))
            ->when($request->status !== null && $request->status !== '', fn($q) => $q->where('is_active', $request->status))
            ->orderBy('name')
            ->orderByRaw('CAST(kva AS UNSIGNED)')
            ->orderBy('series')
            ->get();

        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $years      = Product::whereNotNull('tahun')->distinct()->orderByDesc('tahun')->pluck('tahun');

        $yearGroups = $products
            ->groupBy(fn($p) => $p->tahun ?? 0)
            ->sortKeysDesc();

        // Bentuk data mengikuti tata letak lama: dikelompokkan per TAHUN, lalu
        // per NAMA produk, dengan tiap varian (seri/kVA) sebagai baris di dalam
        // kartu produk.
        $yearSections = $yearGroups->map(function ($items, $tahun) {
            return [
                'year'     => $tahun ? (string) $tahun : null,
                'count'    => $items->count(),
                'products' => $items->groupBy('name')->map(function ($variants, $name) {
                    $first = $variants->first();

                    return [
                        'name'     => $name,
                        'category' => $first->category->name ?? '-',
                        'type'     => $first->type,
                        'addUrl'   => route('products.create') . '?' . http_build_query([
                            'name'        => $name,
                            'category_id' => $first->category_id,
                            'type'        => $first->type,
                        ]),
                        'variants' => $variants->map(fn ($p) => [
                            'id'        => $p->id,
                            'series'    => $p->series,
                            'kva'       => $p->kva,
                            'is_active' => (bool) $p->is_active,
                            'showUrl'   => route('products.show', $p->id),
                            'editUrl'   => route('products.edit', $p->id),
                            'deleteUrl' => route('products.destroy', $p->id),
                            'toggleUrl' => route('products.toggle-active', $p->id),
                        ])->values(),
                    ];
                })->values(),
            ];
        })->values();

        return Inertia::render('Products/Index', [
            'filters'    => $request->only(['search', 'category_id', 'tahun', 'status']),
            'indexUrl'   => route('products.index'),
            'createUrl'  => route('products.create'),
            'ukuranUrl'  => route('products.ukuran'),
            'categories' => $categories->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->values(),
            'years'      => $years->map(fn ($y) => ['value' => $y, 'label' => (string) $y])->values(),
            'total'      => $products->count(),
            'can'        => [
                'create' => \App\Support\MenuAccess::can(auth()->user(), 'master-produk.create'),
                'edit'   => \App\Support\MenuAccess::can(auth()->user(), 'master-produk.edit'),
                'delete' => \App\Support\MenuAccess::can(auth()->user(), 'master-produk.delete'),
            ],
            'sections'   => $yearSections,
        ]);
    }

    public function create()
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        return Inertia::render('Products/Form', [
            'mode'       => 'create',
            'action'     => route('products.store'),
            'indexUrl'   => route('products.index'),
            'categories' => $categories->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->values(),
            'maxYear'    => now()->year + 5,
        ]);
    }

    public function store(ProductRequest $request)
    {
        $product = Product::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);
        ActivityLog::record('create', "Menambah produk: {$product->name}", $product);
        return redirect()->route('products.index')->with('success', "Produk '{$product->name}' berhasil ditambahkan.");
    }

    public function show(Product $product)
    {
        $product->load('category');
        $monthlyLogs = ProductionLog::where('product_id', $product->id)
            ->whereMonth('production_date', now()->month)
            ->whereYear('production_date', now()->year)
            ->orderByDesc('production_date')
            ->get();

        return Inertia::render('Products/Show', [
            'indexUrl' => route('products.index'),
            'editUrl'  => route('products.edit', $product->id),
            'product'  => [
                'id'          => $product->id,
                'name'        => $product->name,
                'series'      => $product->series,
                'kva'         => $product->kva,
                'tahun'       => $product->tahun,
                'type'        => $product->type,
                'unit'        => $product->unit,
                'panjang'     => $product->panjang,
                'lebar'       => $product->lebar,
                'description' => $product->description,
                'category'    => $product->category->name ?? null,
                'is_active'   => (bool) $product->is_active,
            ],
            'monthLabel'  => now()->locale('id')->isoFormat('MMMM YYYY'),
            'monthlyLogs' => $monthlyLogs->map(fn ($l) => [
                'id'       => $l->id,
                'date'     => $l->production_date?->locale('id')->isoFormat('D MMM YYYY'),
                'operator' => $l->operator_name,
                'up'   => (int) $l->up_qty,
                'bt'   => (int) $l->bt_qty,
                'total'    => (float) $l->total_qty,
                'reject'   => (int) $l->reject_qty,
            ])->values(),
        ]);
    }

    public function edit(Product $product)
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        return Inertia::render('Products/Form', [
            'mode'       => 'edit',
            'action'     => route('products.update', $product->id),
            'indexUrl'   => route('products.index'),
            'categories' => $categories->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->values(),
            'maxYear'    => now()->year + 5,
            'product'    => [
                'id'          => $product->id,
                'category_id' => $product->category_id,
                'type'        => $product->type,
                'name'        => $product->name,
                'series'      => $product->series,
                'kva'         => $product->kva,
                'tahun'       => $product->tahun,
                'panjang'     => $product->panjang,
                'lebar'       => $product->lebar,
                'unit'        => $product->unit,
                'description' => $product->description,
                'is_active'   => (bool) $product->is_active,
            ],
        ]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $product->update($request->validated() + ['is_active' => $request->boolean('is_active', true)]);
        ActivityLog::record('update', "Mengubah produk: {$product->name}", $product);
        return redirect()->route('products.index')->with('success', "Produk '{$product->name}' berhasil diperbarui.");
    }

    public function destroy(Product $product)
    {
        if ($product->productionLogs()->exists()) {
            return back()->with('error', "Produk '{$product->name}' tidak bisa dihapus karena sudah memiliki data produksi.");
        }
        $name = $product->name;
        $product->delete();
        ActivityLog::record('delete', "Menghapus produk: {$name}");
        return redirect()->route('products.index')->with('success', "Produk '{$name}' berhasil dihapus.");
    }

    public function toggleActive(Product $product)
    {
        $product->update(['is_active' => !$product->is_active]);
        $status = $product->is_active ? 'Aktif' : 'Selesai';
        ActivityLog::record('update', "Tandai produk '{$product->series}' sebagai {$status}");
        return response()->json(['is_active' => $product->is_active]);
    }

    public function ukuranIndex(Request $request)
    {
        $products = Product::with('category')
            ->where('is_active', true)
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('series', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->orderBy('name')
            ->get();

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Products/Ukuran', [
            'filters'    => $request->only(['search', 'category_id']),
            'indexUrl'   => route('products.ukuran'),
            'backUrl'    => route('products.index'),
            'categories' => $categories->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->values(),
            'rows'       => $products->map(fn ($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'series'   => $p->series,
                'kva'      => $p->kva,
                'category' => $p->category->name ?? null,
                'panjang'  => $p->panjang,
                'lebar'    => $p->lebar,
                'unit'     => $p->unit,
                'editUrl'  => route('products.edit', $p->id),
            ])->values(),
        ]);
    }

    // API untuk select dropdown (AJAX)
    public function apiList(Request $request)
    {
        $products = Product::where('is_active', true)
            ->when($request->q, fn($q) => $q->where('name', 'like', "%{$request->q}%")
                ->orWhere('series', 'like', "%{$request->q}%"))
            ->select('id', 'name', 'series', 'unit')
            ->limit(20)
            ->get()
            ->map(fn($p) => ['id' => $p->id, 'text' => $p->full_name, 'unit' => $p->unit]);

        return response()->json($products);
    }
}
