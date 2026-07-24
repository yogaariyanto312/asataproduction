<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::withCount('products')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('code', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(CategoryRequest $request)
    {
        $category = Category::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);
        ActivityLog::record('create', "Menambah kategori: {$category->name}", $category);
        return redirect()->route('categories.index')->with('success', "Kategori '{$category->name}' berhasil ditambahkan.");
    }

    public function edit(Category $category)
    {
        return view('categories.edit', compact('category'));
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category->update($request->validated() + ['is_active' => $request->boolean('is_active', true)]);
        ActivityLog::record('update', "Mengubah kategori: {$category->name}", $category);
        return redirect()->route('categories.index')->with('success', "Kategori '{$category->name}' berhasil diperbarui.");
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->with('error', "Kategori '{$category->name}' tidak bisa dihapus karena masih memiliki produk.");
        }
        $name = $category->name;
        $category->delete();
        ActivityLog::record('delete', "Menghapus kategori: {$name}");
        return redirect()->route('categories.index')->with('success', "Kategori '{$name}' berhasil dihapus.");
    }
}
