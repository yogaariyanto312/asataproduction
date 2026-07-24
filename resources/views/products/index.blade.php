@extends('layouts.app')

@section('title', 'Master Produk')
@section('page-title', 'Master Produk')
@section('page-subtitle', 'Kelola daftar produk dan seri')

@section('content')
<div class="space-y-5">

    {{-- Filter & Action Bar --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <form method="GET" action="{{ route('products.index') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-44">
                <label class="block text-xs font-medium text-slate-500 mb-1">Cari Produk</label>
                <input type="text" name="search" id="search-realtime"
                       value="{{ request('search') }}"
                       placeholder="Nama atau seri..."
                       class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                              bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="min-w-36">
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
            <div class="min-w-32">
                <label class="block text-xs font-medium text-slate-500 mb-1">Tahun</label>
                <select name="tahun"
                        class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                               bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Tahun</option>
                    @foreach($years as $y)
                    <option value="{{ $y }}" {{ request('tahun') == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit"
                        class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    Filter
                </button>
                <a href="{{ route('products.index') }}"
                   class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                    Reset
                </a>
                @allowedTo('master-produk.create')
                <a href="{{ route('products.create') }}"
                   class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah Produk
                </a>
                @endallowedTo
            </div>
        </form>
    </div>

    {{-- Empty state --}}
    @if($products->isEmpty())
    <div class="py-16 text-center bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700">
        <svg class="w-14 h-14 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
        </svg>
        <p class="text-slate-500 font-medium">Belum ada produk</p>
        @allowedTo('master-produk.create')
        <a href="{{ route('products.create') }}"
           class="mt-4 inline-block px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700">
            Tambah Produk Pertama
        </a>
        @endallowedTo
    </div>

    @else

    {{-- Sections per tahun --}}
    @foreach($yearGroups as $tahun => $items)
    @php $grouped = $items->groupBy('name'); @endphp
    <div class="space-y-3">

        {{-- Year header --}}
        <div class="flex items-center gap-3">
            @if($tahun)
            <div class="flex items-center gap-2 px-4 py-1.5 bg-blue-600 text-white rounded-full shadow-sm shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="text-sm font-bold tracking-wide">{{ $tahun }}</span>
            </div>
            @else
            <div class="flex items-center gap-2 px-4 py-1.5 bg-slate-500 text-white rounded-full shadow-sm shrink-0">
                <span class="text-sm font-bold tracking-wide">Belum ada tahun</span>
            </div>
            @endif
            <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div>
            <span class="text-xs text-slate-400 font-medium shrink-0">{{ $items->count() }} produk</span>
        </div>

        {{-- Cards grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($grouped as $productName => $groupItems)
            @php
                $firstItem    = $groupItems->first();
                $isChannel    = $firstItem->type === 'channel';
                $categoryName = $firstItem->category->name ?? '-';
            @endphp
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden flex flex-col">

                {{-- Card Header --}}
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0
                                {{ $isChannel ? 'bg-blue-100 dark:bg-blue-900/40' : 'bg-slate-100 dark:bg-slate-600' }}">
                        <svg class="w-5 h-5 {{ $isChannel ? 'text-blue-600 dark:text-blue-400' : 'text-slate-500 dark:text-white' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-slate-800 dark:text-white text-base leading-tight">{{ $productName }}</h3>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs text-slate-400">{{ $categoryName }}</span>
                            <span class="text-xs text-slate-300 dark:text-slate-600">·</span>
                            <span class="text-xs text-slate-400">{{ $groupItems->count() }} varian</span>
                        </div>
                    </div>
                    @allowedTo('master-produk.create')
                    <a href="{{ route('products.create') }}?name={{ urlencode($productName) }}&category_id={{ $firstItem->category_id }}&type={{ $firstItem->type }}"
                       title="Tambah varian baru"
                       class="shrink-0 w-8 h-8 flex items-center justify-center rounded-xl
                              bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400
                              hover:bg-green-200 dark:hover:bg-green-800/50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </a>
                    @endallowedTo
                </div>

                {{-- Series Rows --}}
                <div class="divide-y divide-slate-100 dark:divide-slate-700 flex-1">
                    @foreach($groupItems as $product)
                    <div class="px-5 py-3 flex items-center justify-between gap-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                        <div class="min-w-0 flex-1">
                            @if($product->series)
                            <p class="text-sm font-mono font-semibold text-slate-700 dark:text-slate-200 truncate">
                                {{ $product->series }}
                            </p>
                            @if($product->kva)
                            <p class="text-xs text-slate-400 mt-0.5">{{ $product->kva }} KVA</p>
                            @endif
                            @else
                            <p class="text-xs font-medium text-amber-500 dark:text-amber-400 italic">
                                Input Seri & KVA Manual
                            </p>
                            @endif
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            @allowedTo('master-produk.edit')
                            <button type="button"
                                    data-toggle-url="{{ route('products.toggle-active', $product) }}"
                                    data-active="{{ $product->is_active ? '1' : '0' }}"
                                    title="Klik untuk toggle status"
                                    class="toggle-active-btn inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold cursor-pointer transition-colors
                                           {{ $product->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400'
                                                                  : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 hover:bg-green-100 hover:text-green-700 dark:hover:bg-green-900/30 dark:hover:text-green-400' }}">
                                {{ $product->is_active ? 'Aktif' : 'Nonaktif' }}
                            </button>
                            @else
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold
                                         {{ $product->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                                : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $product->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            @endallowedTo
                            @allowedTo('master-produk.edit')
                            <a href="{{ route('products.edit', $product) }}"
                               title="Edit"
                               class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            @endallowedTo
                            @allowedTo('master-produk.delete')
                            <form method="POST" action="{{ route('products.destroy', $product) }}" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        title="Hapus"
                                        data-confirm="Hapus produk '{{ $product->name }} - {{ $product->series }}'?"
                                        class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                            @endallowedTo
                        </div>
                    </div>
                    @endforeach
                </div>

            </div>
            @endforeach
        </div>

    </div>
    @endforeach

    @endif

</div>

<script>
document.querySelectorAll('.toggle-active-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const url  = btn.dataset.toggleUrl;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

        btn.disabled = true;
        try {
            const res  = await fetch(url, { method: 'PATCH', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            const data = await res.json();
            const active = data.is_active;

            btn.dataset.active = active ? '1' : '0';
            btn.textContent    = active ? 'Aktif' : 'Nonaktif';

            const onCls  = 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400';
            const offCls = 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 hover:bg-green-100 hover:text-green-700 dark:hover:bg-green-900/30 dark:hover:text-green-400';
            btn.classList.remove(...onCls.split(' '), ...offCls.split(' '));
            btn.classList.add(...(active ? onCls : offCls).split(' '));
        } finally {
            btn.disabled = false;
        }
    });
});
</script>
@endsection

