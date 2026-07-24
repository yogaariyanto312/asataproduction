@extends('layouts.app')

@section('title', 'Tentang Aplikasi')
@section('page-title', 'Tentang Aplikasi')
@section('page-subtitle', 'Informasi sistem & pengembang')

@section('content')
<div class="max-w-2xl lg:max-w-5xl mx-auto space-y-6">

    {{-- Hero Card --}}
    <div class="relative bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-8 overflow-hidden shadow-xl">
        {{-- decorative circles --}}
        <div class="absolute -top-8 -right-8 w-40 h-40 bg-white/10 rounded-full"></div>
        <div class="absolute -bottom-10 -left-10 w-56 h-56 bg-white/5 rounded-full"></div>

        <div class="relative flex items-center gap-5">
            <div class="w-16 h-16 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center shrink-0 shadow-lg">
                <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-white leading-tight">Asata Production System</h1>
                <p class="text-blue-200 text-sm mt-1">Sistem Pencatatan & Monitoring Produksi</p>
                <span class="inline-block mt-2 px-2.5 py-0.5 bg-white/20 text-white text-xs font-semibold rounded-full">
                    Versi 1.0
                </span>
            </div>
        </div>
    </div>

    {{-- Deskripsi --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700">
        <h2 class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3">Tentang Sistem</h2>
        <p class="text-slate-700 dark:text-slate-300 text-sm leading-relaxed">
            Aplikasi ini dirancang khusus untuk membantu tim QC dalam mencatat, memantau, dan menganalisis
            data produksi harian secara efisien. Mulai dari input produksi per shift, manajemen produk,
            hingga laporan rekap bulanan — semua tersedia dalam satu platform.
        </p>
    </div>

    {{-- Fitur Utama --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700">
        <h2 class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-4">Fitur Utama</h2>
        <div class="grid grid-cols-2 gap-3">
            @foreach([
                ['icon' => 'M12 4v16m8-8H4',                                                                                                                                                                              'label' => 'Input Produksi',    'color' => 'blue'],
                ['icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'label' => 'Laporan & Ekspor', 'color' => 'purple'],
                ['icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',          'label' => 'Gambar Kerja',     'color' => 'amber'],
                ['icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z',                    'label' => 'Chat Internal',    'color' => 'teal'],
                ['icon' => 'M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',                                      'label' => 'Dashboard & Grafik','color' => 'green'],
                ['icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',               'label' => 'Manajemen User',   'color' => 'rose'],
            ] as $f)
            @php
                $colorMap = [
                    'blue'   => 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400',
                    'purple' => 'bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400',
                    'amber'  => 'bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400',
                    'teal'   => 'bg-teal-50 dark:bg-teal-900/20 text-teal-600 dark:text-teal-400',
                    'green'  => 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400',
                    'rose'   => 'bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400',
                ];
            @endphp
            <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $colorMap[$f['color']] }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/>
                    </svg>
                </div>
                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $f['label'] }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Developer Card --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden shadow-sm border border-slate-100 dark:border-slate-700">
      <div class="lg:flex">

        {{-- Banner / kolom foto --}}
        <div class="relative px-6 py-5 lg:w-64 lg:shrink-0 lg:flex lg:items-center lg:justify-center"
             style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #0284c7 100%);">
            {{-- dot pattern --}}
            <div class="absolute inset-0"
                 style="background-image: radial-gradient(rgba(255,255,255,0.15) 1.5px, transparent 1.5px); background-size: 20px 20px;"></div>
            {{-- lingkaran dekoratif --}}
            <div class="absolute rounded-full pointer-events-none"
                 style="width:160px;height:160px;top:-50px;right:-40px;background:rgba(255,255,255,0.07);"></div>
            <div class="absolute rounded-full pointer-events-none"
                 style="width:90px;height:90px;bottom:-20px;left:45%;background:rgba(255,255,255,0.05);"></div>

            {{-- Isi banner --}}
            <div class="relative flex lg:flex-col items-center justify-between lg:justify-center lg:gap-4 w-full">
                {{-- Avatar --}}
                <div class="w-36 h-36 lg:w-44 lg:h-44 rounded-2xl shadow-xl shrink-0 overflow-hidden"
                     style="background: linear-gradient(145deg, #93c5fd, #1d4ed8);">
                    @php
                        $aboutPhoto = $developer?->about_avatar ?? $developer?->avatar ?? null;
                        if ($aboutPhoto && !str_starts_with($aboutPhoto, 'http')) {
                            $aboutPhoto = route('storage.file', ['path' => $aboutPhoto]);
                        }
                    @endphp
                    @if($aboutPhoto)
                        <img src="{{ $aboutPhoto }}"
                             alt="{{ $developer->name }}"
                             class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            <span class="text-3xl font-black text-white select-none">
                                {{ strtoupper(substr($developer->name ?? 'Y', 0, 1)) }}
                            </span>
                        </div>
                    @endif
                </div>

                {{-- Badge + active --}}
                <div class="flex flex-col items-end lg:items-center gap-2">
                    <span class="px-3 py-1 text-xs font-bold text-white rounded-full"
                          style="background:rgba(255,255,255,0.18); border:1px solid rgba(255,255,255,0.3);">
                        ✦ Developer
                    </span>
                    <span class="flex items-center gap-1.5 px-2.5 py-0.5 rounded-full"
                          style="background:rgba(255,255,255,0.15);">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
                        <span class="text-xs font-semibold text-white">Active</span>
                    </span>
                </div>
            </div>
        </div>

        {{-- Konten / kolom teks --}}
        <div class="px-6 py-5 lg:flex-1 lg:border-l border-slate-100 dark:border-slate-700">

            {{-- Handle --}}
            @if($developer && $developer->handle)
            <div class="mb-6">
                <p class="text-sm font-semibold text-blue-500 dark:text-blue-400">
                    {{ '@' . ltrim($developer->handle, '@') }}
                </p>
            </div>
            @endif

            {{-- Bio --}}
            @php
                $bioText = $developer->bio ?? 'Quality Control Engineer sekaligus developer internal sistem ini. Bertanggung jawab atas desain, pengembangan, dan pemeliharaan aplikasi Asata Production System.';
                $bioParagraphs = array_values(array_filter(explode("\n\n", $bioText), 'strlen'));
            @endphp
            @foreach($bioParagraphs as $para)
            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed whitespace-pre-line{{ $loop->first ? '' : ' mt-1' }}">{{ trim($para) }}</p>
            @endforeach

            {{-- Divider --}}
            <div class="border-t border-slate-100 dark:border-slate-700" style="margin-top: 28px; margin-bottom: 24px;"></div>

            {{-- Stats --}}
            <div class="grid grid-cols-3 gap-3 mb-6">

                {{-- Repo GitHub (dinamis) --}}
                <div class="relative text-center bg-slate-50 dark:bg-slate-700/50 rounded-2xl overflow-hidden" style="padding: 20px 12px 16px;">
                    <div class="absolute top-0 left-0 right-0" style="height:3px; background: linear-gradient(90deg,#3b82f6,#06b6d4);"></div>
                    <p id="github-repos" class="text-2xl font-extrabold text-slate-800 dark:text-white leading-none transition-all">
                        <span class="text-slate-300 dark:text-slate-600 text-base animate-pulse">···</span>
                    </p>
                    <p class="text-xs text-slate-400 font-medium" style="margin-top: 8px;">Repo GitHub</p>
                </div>

                {{-- Versi --}}
                <div class="relative text-center bg-slate-50 dark:bg-slate-700/50 rounded-2xl overflow-hidden" style="padding: 20px 12px 16px;">
                    <div class="absolute top-0 left-0 right-0" style="height:3px; background: linear-gradient(90deg,#8b5cf6,#ec4899);"></div>
                    <p class="text-2xl font-extrabold text-slate-800 dark:text-white leading-none">v1.0</p>
                    <p class="text-xs text-slate-400 font-medium" style="margin-top: 8px;">Versi App</p>
                </div>

                {{-- Tahun --}}
                <div class="relative text-center bg-slate-50 dark:bg-slate-700/50 rounded-2xl overflow-hidden" style="padding: 20px 12px 16px;">
                    <div class="absolute top-0 left-0 right-0" style="height:3px; background: linear-gradient(90deg,#f59e0b,#ef4444);"></div>
                    <p class="text-2xl font-extrabold text-slate-800 dark:text-white leading-none">{{ now()->year }}</p>
                    <p class="text-xs text-slate-400 font-medium" style="margin-top: 8px;">Tahun</p>
                </div>

            </div>

            {{-- Social Buttons — dinamis dari DB --}}
            @if($developer && ($developer->link_instagram || $developer->link_github || $developer->link_portfolio || $developer->link_email))
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Hubungi / Temukan</p>
            <div class="grid grid-cols-2 gap-2">

                {{-- Instagram --}}
                @if($developer->link_instagram)
                <a href="{{ $developer->link_instagram }}" target="_blank"
                   class="flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white transition-all hover:scale-[1.02] hover:brightness-110 shadow-md"
                   style="background: linear-gradient(135deg, #c2185b, #e91e8c, #f77737);">
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                    </svg>
                    Instagram
                </a>
                @endif

                {{-- GitHub --}}
                @if($developer->link_github)
                <a href="{{ $developer->link_github }}" target="_blank"
                   class="flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white transition-all hover:scale-[1.02] hover:brightness-110 shadow-md"
                   style="background: linear-gradient(135deg, #374151, #1f2937);">
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                    </svg>
                    GitHub
                </a>
                @endif

                {{-- Portfolio --}}
                @if($developer->link_portfolio)
                <a href="{{ $developer->link_portfolio }}" target="_blank"
                   class="flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white transition-all hover:scale-[1.02] hover:brightness-110 shadow-md"
                   style="background: linear-gradient(135deg, #7c3aed, #4f46e5);">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Portfolio
                </a>
                @endif

                {{-- Email --}}
                @if($developer->link_email)
                <a href="mailto:{{ $developer->link_email }}"
                   class="flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white transition-all hover:scale-[1.02] hover:brightness-110 shadow-md"
                   style="background: linear-gradient(135deg, #f59e0b, #ef4444);">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Email
                </a>
                @endif

            </div>
            @endif
        </div>
      </div>{{-- /lg:flex --}}
    </div>

    {{-- Portfolio iframe --}}
    @if($developer && $developer->link_portfolio)
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center gap-2 px-5 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40">
            <div class="w-6 h-6 rounded-lg bg-purple-100 dark:bg-purple-900/40 flex items-center justify-center shrink-0">
                <svg class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <span class="text-sm font-bold text-slate-700 dark:text-white flex-1">Portfolio</span>
            <a href="{{ $developer->link_portfolio }}" target="_blank"
               class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg
                      bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300
                      hover:bg-purple-50 dark:hover:bg-purple-900/30 hover:text-purple-600 dark:hover:text-purple-400 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                Buka
            </a>
        </div>
        {{-- iframe --}}
        <div class="relative w-full" style="padding-bottom: 56.25%;">
            <iframe src="{{ $developer->link_portfolio }}"
                    class="absolute inset-0 w-full h-full"
                    frameborder="0"
                    loading="lazy"
                    allowfullscreen>
            </iframe>
        </div>
    </div>
    @endif

    {{-- Tech Stack --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700">
        <h2 class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-4">Teknologi</h2>
        <div class="flex flex-wrap gap-2">
            @foreach(['Laravel 12', 'PHP 8', 'Tailwind CSS', 'Alpine.js', 'Chart.js', 'MySQL'] as $tech)
            <span class="px-3 py-1.5 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg">
                {{ $tech }}
            </span>
            @endforeach
        </div>
    </div>

    {{-- Changelog --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <h2 class="text-sm font-bold text-slate-700 dark:text-white">Riwayat Update</h2>
                @if($changelogs->count())
                <span class="px-2 py-0.5 bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400 text-xs font-bold rounded-full">
                    {{ $changelogs->count() }}
                </span>
                @endif
            </div>

            {{-- Tombol tambah — developer only --}}
            @if(auth()->user()->isDeveloper())
            <button onclick="document.getElementById('changelog-form').classList.toggle('hidden')"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah
            </button>
            @endif
        </div>

        {{-- Form tambah entry — developer only --}}
        @if(auth()->user()->isDeveloper())
        <div id="changelog-form" class="hidden border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 px-6 py-5">
            <form method="POST" action="{{ route('about.changelog.store') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Tipe</label>
                        <select name="type" required
                                class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500">
                            <option value="feature">Feature</option>
                            <option value="fix">Fix</option>
                            <option value="improvement">Improvement</option>
                            <option value="security">Security</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Versi <span class="font-normal text-slate-400">(opsional)</span></label>
                        <input type="text" name="version" placeholder="v1.2"
                               class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 font-mono">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Judul <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required placeholder="Contoh: Tambah fitur changelog di halaman About"
                           class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Deskripsi <span class="font-normal text-slate-400">(opsional)</span></label>
                    <textarea name="description" rows="4" placeholder="Penjelasan lebih detail tentang perubahan ini..."
                              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('changelog-form').classList.add('hidden')"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-xs font-semibold text-white bg-violet-600 hover:bg-violet-700 rounded-xl transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
        @endif

        {{-- Daftar changelog --}}
        @if($changelogs->isEmpty())
        <div class="px-6 py-10 text-center">
            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <p class="text-sm text-slate-400">Belum ada riwayat update.</p>
        </div>
        @else
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach($changelogs as $log)
            @php
                $badgeConfig = match($log->type) {
                    'fix'         => ['label' => 'Fix',         'class' => 'bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400'],
                    'improvement' => ['label' => 'Improvement', 'class' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400'],
                    'security'    => ['label' => 'Security',    'class' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400'],
                    default       => ['label' => 'Feature',     'class' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400'],
                };
            @endphp
            <div class="flex items-start gap-4 px-6 py-4 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors group">

                {{-- Left: badge + tanggal --}}
                <div class="flex flex-col items-center gap-1.5 shrink-0 pt-0.5" style="min-width:80px">
                    <span class="inline-block px-2 py-0.5 text-[10px] font-bold rounded-full uppercase tracking-wide {{ $badgeConfig['class'] }}">
                        {{ $badgeConfig['label'] }}
                    </span>
                    <span class="text-[10px] text-slate-400 whitespace-nowrap">
                        {{ $log->created_at->timezone('Asia/Jakarta')->translatedFormat('d M Y') }}
                    </span>
                </div>

                {{-- Right: konten --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-sm font-semibold text-slate-800 dark:text-white leading-snug">
                            {{ $log->title }}
                        </p>
                        @if($log->version)
                        <span class="px-1.5 py-0.5 bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-[10px] font-mono font-bold rounded">
                            {{ $log->version }}
                        </span>
                        @endif
                    </div>
                    @if($log->description)
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ $log->description }}
                    </p>
                    @endif
                </div>

                {{-- Hapus — developer only --}}
                @if(auth()->user()->isDeveloper())
                <form method="POST" action="{{ route('about.changelog.destroy', $log) }}"
                      class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                    @csrf @method('DELETE')
                    <button type="submit"
                            data-confirm="Hapus entry changelog ini?"
                            data-confirm-title="Hapus Changelog"
                            class="p-1.5 text-slate-300 hover:text-rose-500 dark:text-slate-600 dark:hover:text-rose-400 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </form>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
@php
    // Ambil username dari link_github (https://github.com/username → username)
    $ghUsername = null;
    if ($developer?->link_github) {
        $parts = explode('/', rtrim($developer->link_github, '/'));
        $ghUsername = end($parts);
    }
@endphp
@if($ghUsername)
// Fetch jumlah public repo dari GitHub API
fetch('https://api.github.com/users/{{ $ghUsername }}')
    .then(r => r.json())
    .then(data => {
        const el = document.getElementById('github-repos');
        if (el && data.public_repos !== undefined) {
            el.innerHTML = `<span class="text-2xl font-extrabold text-slate-800 dark:text-white">${data.public_repos}</span>`;
        }
    })
    .catch(() => {
        const el = document.getElementById('github-repos');
        if (el) el.innerHTML = `<span class="text-2xl font-extrabold text-slate-800 dark:text-white">—</span>`;
    });
@else
document.getElementById('github-repos').innerHTML =
    '<span class="text-2xl font-extrabold text-slate-800 dark:text-white">—</span>';
@endif
</script>
@endpush
