@extends('layouts.app')

@section('title', $gambarKerja->judul)
@section('page-title', 'Gambar Kerja')
@section('page-subtitle', $gambarKerja->product->name ?? '-')

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

    {{-- Header info --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold
                        {{ $gambarKerja->isImage() ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700' }}">
                        {{ $gambarKerja->isImage() ? 'Gambar' : 'PDF' }}
                    </span>
                    <span class="text-xs text-slate-400">{{ $gambarKerja->product->category->name ?? '' }}</span>
                </div>
                <h2 class="text-xl font-bold text-slate-800 dark:text-white">{{ $gambarKerja->judul }}</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Produk: <span class="font-medium text-slate-700 dark:text-slate-300">{{ $gambarKerja->product->name ?? '-' }}</span>
                    @if($gambarKerja->product->series_with_kva)
                    <span class="font-mono text-xs ml-1">— {{ $gambarKerja->product->series_with_kva }}</span>
                    @endif
                </p>
                @if($gambarKerja->product->panjang || $gambarKerja->product->lebar)
                <p class="text-xs text-slate-400 mt-1">
                    Ukuran:
                    @if($gambarKerja->product->panjang)
                    <span class="text-indigo-600 font-medium">P: {{ $gambarKerja->product->panjang }}</span>
                    @endif
                    @if($gambarKerja->product->lebar)
                    <span class="text-purple-600 font-medium ml-2">L: {{ $gambarKerja->product->lebar }}</span>
                    @endif
                </p>
                @endif
                @if($gambarKerja->keterangan)
                <p class="text-sm text-slate-500 mt-2">{{ $gambarKerja->keterangan }}</p>
                @endif
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
                @unless(auth()->user()->isVisitor())
                <a href="{{ $gambarKerja->url }}" target="_blank" download
                   class="flex items-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download
                </a>
                @endunless
                @if(auth()->user()->isAdmin())
                <form method="POST" action="{{ route('gambar-kerja.destroy', $gambarKerja) }}">
                    @csrf @method('DELETE')
                    <button type="submit"
                            data-confirm="Hapus gambar kerja '{{ $gambarKerja->judul }}'?"
                            class="flex items-center gap-2 px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-900/20 dark:text-red-400 text-sm font-semibold rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Hapus
                    </button>
                </form>
                @endif
                <a href="{{ route('gambar-kerja.index') }}"
                   class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                    Kembali
                </a>
            </div>
        </div>

        <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center gap-4 text-xs text-slate-400">
            <span>Diupload oleh <span class="font-medium text-slate-600 dark:text-slate-300">{{ $gambarKerja->uploader->name ?? '-' }}</span></span>
            <span>{{ $gambarKerja->created_at->format('d M Y, H:i') }}</span>
        </div>
    </div>

    {{-- Preview --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
            <h3 class="font-semibold text-slate-700 dark:text-slate-300 text-sm">Preview</h3>
        </div>

        @if($gambarKerja->isImage())
        <div class="p-4 flex justify-center bg-slate-50 dark:bg-slate-900/50">
            <img src="{{ $gambarKerja->url }}"
                 alt="{{ $gambarKerja->judul }}"
                 class="max-w-full rounded-xl shadow-md cursor-zoom-in"
                 onclick="this.classList.toggle('max-w-full'); this.classList.toggle('w-full')">
        </div>
        @else
        <div class="p-0">
            <iframe src="{{ $gambarKerja->url }}"
                    class="w-full border-0"
                    style="height: 75vh;"
                    title="{{ $gambarKerja->judul }}">
                <div class="p-8 text-center">
                    <p class="text-slate-500 mb-4">Browser tidak mendukung preview PDF.</p>
                    <a href="{{ $gambarKerja->url }}" target="_blank"
                       class="px-5 py-2 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700">
                        Buka PDF
                    </a>
                </div>
            </iframe>
        </div>
        @endif
    </div>

</div>
@endsection
