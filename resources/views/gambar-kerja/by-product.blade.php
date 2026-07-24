@extends('layouts.app')

@section('title', 'Gambar Kerja — ' . $product->name)
@section('page-title', $product->name)
@section('page-subtitle', 'Gambar Kerja · ' . $gambarKerja->count() . ' gambar')

@section('content')
<div class="space-y-5">

    {{-- Header produk --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    @if($product->category)
                    <span class="px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400">
                        {{ $product->category->name }}
                    </span>
                    @endif
                    @if($product->isChannel())
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">Channel</span>
                    @else
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">Regular</span>
                    @endif
                </div>
                <h2 class="text-lg font-bold text-slate-800 dark:text-white">{{ $product->name }}</h2>
                @if($product->series_with_kva)
                <p class="text-sm font-mono text-slate-400 mt-0.5">{{ $product->series_with_kva }}</p>
                @endif
                @if($product->panjang || $product->lebar)
                <p class="text-xs text-slate-400 mt-1">
                    @if($product->panjang)<span class="text-indigo-600 font-medium">P: {{ $product->panjang }}</span>@endif
                    @if($product->lebar)<span class="text-purple-600 font-medium ml-3">L: {{ $product->lebar }}</span>@endif
                </p>
                @endif
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                @if(auth()->user()->isAdmin())
                <a href="{{ route('gambar-kerja.create', ['product_id' => $product->id]) }}"
                   class="flex items-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah Gambar
                </a>
                @endif
                <a href="{{ route('gambar-kerja.index') }}"
                   class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                    ← Kembali
                </a>
            </div>
        </div>
    </div>

    {{-- Daftar gambar berurutan --}}
    @if($gambarKerja->isEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-2xl py-16 text-center border border-slate-100 dark:border-slate-700">
        <svg class="w-14 h-14 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-slate-500 font-medium">Belum ada gambar kerja untuk produk ini</p>
        @if(auth()->user()->isAdmin())
        <a href="{{ route('gambar-kerja.create', ['product_id' => $product->id]) }}"
           class="mt-4 inline-block px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700">
            Upload Gambar Kerja
        </a>
        @endif
    </div>
    @else
    <div class="space-y-4">
        @foreach($gambarKerja as $item)
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

            {{-- Header gambar --}}
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-blue-600 text-white text-sm font-bold flex items-center justify-center shrink-0">
                        {{ $item->urutan }}
                    </span>
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-white text-sm">{{ $item->judul }}</p>
                        @if($item->keterangan)
                        <p class="text-xs text-slate-400">{{ $item->keterangan }}</p>
                        @endif
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold
                        {{ $item->isImage() ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700' }}">
                        {{ $item->isImage() ? 'Gambar' : 'PDF' }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-400 hidden sm:block">{{ $item->created_at->format('d M Y') }}</span>

                    {{-- Download — visitor tidak bisa --}}
                    @unless(auth()->user()->isVisitor())
                    <a href="{{ route('storage.file', ['path' => $item->file_path]) }}" target="_blank" download
                       class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium
                              text-green-700 bg-green-50 hover:bg-green-100 dark:bg-green-900/20 dark:text-green-400 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span class="hidden sm:inline">Download</span>
                    </a>
                    @endunless

                    {{-- Hapus (admin only) --}}
                    @if(auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('gambar-kerja.destroy', $item) }}">
                        @csrf @method('DELETE')
                        <button type="submit"
                                data-confirm="Hapus {{ $item->judul }}? Nomor urut gambar lainnya akan diperbarui otomatis."
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium
                                       text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Hapus
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            {{-- Preview --}}
            @if($item->isImage())
            <div class="bg-slate-50 dark:bg-slate-900/50 p-4 flex justify-center" x-data="{ zoom: false }">
                <img src="{{ route('storage.file', ['path' => $item->file_path]) }}"
                     alt="{{ $item->judul }}"
                     @click="zoom = !zoom"
                     :class="zoom ? 'w-full cursor-zoom-out' : 'max-h-[500px] cursor-zoom-in'"
                     class="rounded-xl shadow object-contain transition-all duration-300">
            </div>
            @else
            <iframe src="{{ route('storage.file', ['path' => $item->file_path]) }}"
                    class="w-full border-0"
                    style="height: 70vh;"
                    title="{{ $item->judul }}">
            </iframe>
            @endif

        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection
