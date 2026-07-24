@extends('layouts.app')

@section('title', 'Ukuran Produk')
@section('page-title', 'Ukuran Produk')
@section('page-subtitle', 'Referensi dimensi panjang dan lebar setiap produk')

@section('content')
<div class="space-y-5">

    {{-- Filter --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <form method="GET" action="{{ route('products.ukuran') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-medium text-slate-500 mb-1">Cari Produk</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Nama atau seri..."
                       class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                              bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="min-w-40">
                <label class="block text-xs font-medium text-slate-500 mb-1">Kategori</label>
                <select name="category_id"
                        class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                               bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit"
                        class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    Filter
                </button>
                <a href="{{ route('products.ukuran') }}"
                   class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Summary Stats --}}
    @php
        $withUkuran  = $products->filter(fn($p) => $p->panjang || $p->lebar)->count();
        $totalProduk = $products->count();
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-700">
            <p class="text-xs text-slate-500 dark:text-slate-400">Total Produk</p>
            <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1">{{ $totalProduk }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-700">
            <p class="text-xs text-slate-500 dark:text-slate-400">Punya Data Ukuran</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1">{{ $withUkuran }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-700 col-span-2 sm:col-span-1">
            <p class="text-xs text-slate-500 dark:text-slate-400">Belum Ada Ukuran</p>
            <p class="text-2xl font-bold text-amber-500 mt-1">{{ $totalProduk - $withUkuran }}</p>
        </div>
    </div>

    {{-- Tabel Ukuran --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <h3 class="font-semibold text-slate-800 dark:text-white">Daftar Ukuran Produk</h3>
            @if(auth()->user()->isDeveloper() || auth()->user()->isAdmin())
            <a href="{{ route('products.index') }}"
               class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium">
                Kelola Produk →
            </a>
            @endif
        </div>

        @if($products->isEmpty())
        <div class="py-16 text-center">
            <svg class="w-14 h-14 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6h18M3 12h18M3 18h18"/>
            </svg>
            <p class="text-slate-500 font-medium">Tidak ada produk ditemukan</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 text-left">
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Produk</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kategori</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Seri / KVA</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Panjang</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Lebar</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tipe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach($products as $product)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-slate-800 dark:text-white">{{ $product->name }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium
                                         bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400">
                                {{ $product->category->name ?? '-' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            @if($product->series_with_kva)
                            <span class="font-mono text-xs text-slate-600 dark:text-slate-400">{{ $product->series_with_kva }}</span>
                            @else
                            <span class="text-slate-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            @if($product->panjang)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-sm font-semibold
                                         bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300">
                                {{ $product->panjang }}
                            </span>
                            @else
                            <span class="text-xs text-slate-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            @if($product->lebar)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-sm font-semibold
                                         bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300">
                                {{ $product->lebar }}
                            </span>
                            @else
                            <span class="text-xs text-slate-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                @if($product->isChannel())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                    Channel
                                </span>
                                @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    Regular
                                </span>
                                @endif
                                @if(auth()->user()->isAdmin() && !$product->panjang && !$product->lebar)
                                <a href="{{ route('products.edit', $product) }}"
                                   class="text-xs text-blue-500 hover:underline">Isi ukuran</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>
@endsection
