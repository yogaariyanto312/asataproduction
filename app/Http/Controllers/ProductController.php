<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use Illuminate\Http\Request;

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

        return view('products.index', compact('products', 'categories', 'years', 'yearGroups'));
    }

    public function create()
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        return view('products.create', compact('categories'));
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

        return view('products.show', compact('product', 'monthlyLogs'));
    }

    public function edit(Product $product)
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        return view('products.edit', compact('product', 'categories'));
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

        return view('products.ukuran', compact('products', 'categories'));
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
