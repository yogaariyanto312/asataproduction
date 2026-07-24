@extends('layouts.app')

@section('title', 'Laporan Produksi')
@section('page-title', 'Laporan Produksi')
@section('page-subtitle', 'Rekap dan export data produksi bulanan')

@section('content')
@php
    $fmtQty    = fn($v) => fmod((float)$v, 1) == 0 ? number_format($v) : number_format($v, 1);
    $monthName = $months[$month] ?? '';

    $catOrder  = ['Channel' => 0, 'Cover' => 1, 'Tangki' => 2];
    $catGroups = $report->groupBy(function($r) {
        $name = strtolower($r->product->category->name ?? '');
        if (str_contains($name, 'channel')) return 'Channel';
        if (str_contains($name, 'cover'))   return 'Cover';
        if (str_contains($name, 'tangki'))  return 'Tangki';
        return $r->product->category->name ?? 'Lainnya';
    })
    ->sortBy(fn($rows, $key) => $catOrder[$key] ?? 99)
    ->map(fn($rows) => $rows->sortBy(fn($r) => (float)($r->product->kva ?? 0)));

    $totalUp    = $report->sum('total_shift1');
    $totalBt    = $report->sum('total_shift2');
    $totalTanki = $report->filter(fn($r) => str_contains(strtolower($r->product->category->name ?? ''), 'tangki'))->sum('grand_total');
    $totalCover = $report->filter(fn($r) => str_contains(strtolower($r->product->category->name ?? ''), 'cover'))->sum('grand_total');
    $grandTotal = $report->sum('grand_total');
@endphp
<div class="space-y-5">

    {{-- Filter & Export Bar --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-700">
        <form method="GET" class="space-y-3">
            {{-- Row 1: Bulan + Tahun + Tampilkan --}}
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-slate-500 mb-1">Bulan</label>
                    <select name="month"
                            class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                                   bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @foreach($months as $num => $name)
                        <option value="{{ $num }}" {{ $month == $num ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Tahun</label>
                    <select name="year"
                            class="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                                   bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @foreach($years as $y)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                @if($departments->isNotEmpty())
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Departemen</label>
                    <select name="department" onchange="this.form.submit()"
                            class="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                                   bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Semua Dept</option>
                        @foreach($departments as $d)
                        <option value="{{ $d }}" {{ $deptFilter === $d ? 'selected' : '' }}>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors whitespace-nowrap">
                    Tampilkan
                </button>
            </div>
            {{-- Row 2: Action buttons --}}
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.daily', array_filter(['department' => $deptFilter])) }}"
                   class="flex-1 sm:flex-none px-3 py-2 bg-slate-600 hover:bg-slate-700 text-white text-sm font-semibold rounded-xl flex items-center justify-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="whitespace-nowrap">Harian</span>
                </a>
                @allowedTo('laporan.export')
                <a href="{{ route('reports.export-excel', array_filter(['month' => $month, 'year' => $year, 'department' => $deptFilter])) }}"
                   class="flex-1 sm:flex-none px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl flex items-center justify-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Excel
                </a>
                <a href="{{ route('reports.export-pdf', array_filter(['month' => $month, 'year' => $year, 'department' => $deptFilter])) }}"
                   class="flex-1 sm:flex-none px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl flex items-center justify-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    PDF
                </a>
                @endallowedTo
            </div>
        </form>
    </div>

    @if($report->count() > 0)

    {{-- Summary Bar --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-4 py-1.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40">
            <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wide">Ringkasan · {{ $monthName }} {{ $year }}</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4">
            {{-- Channel --}}
            <div class="px-3 py-2.5 text-center border-r border-b sm:border-b-0 border-slate-100 dark:border-slate-700">
                <p class="text-[10px] font-medium text-slate-400 mb-1.5">Total Channel</p>
                <div class="flex items-center justify-center gap-2">
                    <div class="text-center">
                        <span class="text-[10px] text-blue-400">UP</span>
                        <p class="text-sm font-bold text-blue-600 dark:text-blue-400 leading-tight">{{ number_format($totalUp) }}</p>
                    </div>
                    <span class="text-slate-200 dark:text-slate-700 font-light">|</span>
                    <div class="text-center">
                        <span class="text-[10px] text-purple-400">BT</span>
                        <p class="text-sm font-bold text-purple-600 dark:text-purple-400 leading-tight">{{ number_format($totalBt) }}</p>
                    </div>
                </div>
            </div>
            {{-- Tanki --}}
            <div class="px-3 py-2.5 text-center border-b sm:border-b-0 sm:border-r border-slate-100 dark:border-slate-700">
                <p class="text-[10px] font-medium text-slate-400 mb-1.5">Total Tangki</p>
                <div class="flex items-baseline justify-center gap-1">
                    <p class="text-sm font-bold text-slate-700 dark:text-white">{{ $fmtQty($totalTanki) }}</p>
                    <span class="text-[10px] text-slate-400">U</span>
                </div>
            </div>
            {{-- Cover --}}
            <div class="px-3 py-2.5 text-center border-r border-b sm:border-b-0 border-slate-100 dark:border-slate-700">
                <p class="text-[10px] font-medium text-slate-400 mb-1.5">Total Cover</p>
                <div class="flex items-baseline justify-center gap-1">
                    <p class="text-sm font-bold text-slate-700 dark:text-white">{{ $fmtQty($totalCover) }}</p>
                    <span class="text-[10px] text-slate-400">U</span>
                </div>
            </div>
            {{-- Total Keseluruhan --}}
            <div class="px-3 py-2.5 text-center border-b sm:border-b-0 border-slate-100 dark:border-slate-700">
                <p class="text-[10px] font-medium text-slate-400 mb-1.5">Total Keseluruhan</p>
                <div class="flex items-baseline justify-center gap-1">
                    <p class="text-sm font-bold text-slate-700 dark:text-white">{{ $fmtQty($grandTotal) }}</p>
                    <span class="text-[10px] text-slate-400">U</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Section header --}}
    <div class="flex items-center gap-3">
        <div class="flex items-center gap-2 px-4 py-1.5 bg-blue-600 text-white rounded-full shadow-sm shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="text-sm font-bold tracking-wide">{{ $monthName }} {{ $year }}</span>
        </div>
        <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div>
        <span class="text-xs text-slate-400 font-medium shrink-0">{{ $report->count() }} produk</span>
    </div>

    {{-- Category Cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($catGroups as $catName => $rows)
        @php
            $catTotal     = $rows->sum('grand_total');
            $catNameLower = strtolower($catName);
            if (str_contains($catNameLower, 'channel')) {
                $cardIcon  = 'M13 10V3L4 14h7v7l9-11h-7z';
                $cardColor = 'text-blue-600 dark:text-blue-400';
                $cardBg    = 'bg-blue-100 dark:bg-blue-900/40';
            } elseif (str_contains($catNameLower, 'cover')) {
                $cardIcon  = 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4';
                $cardColor = 'text-emerald-600 dark:text-emerald-400';
                $cardBg    = 'bg-emerald-100 dark:bg-emerald-900/40';
            } elseif (str_contains($catNameLower, 'tangki')) {
                $cardIcon  = 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4';
                $cardColor = 'text-amber-600 dark:text-amber-400';
                $cardBg    = 'bg-amber-100 dark:bg-amber-900/40';
            } else {
                $cardIcon  = 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2';
                $cardColor = 'text-slate-600 dark:text-slate-400';
                $cardBg    = 'bg-slate-100 dark:bg-slate-700';
            }
        @endphp
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

            {{-- Card header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-lg {{ $cardBg }} flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 {{ $cardColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $cardIcon }}"/>
                        </svg>
                    </div>
                    <span class="text-sm font-bold text-slate-700 dark:text-white">{{ $catName }}</span>
                    <span class="text-xs text-slate-400">· {{ $rows->count() }} produk</span>
                </div>
                <span class="text-sm font-bold {{ $cardColor }}">
                    {{ $fmtQty($catTotal) }} <span class="text-xs font-normal text-slate-400">unit</span>
                </span>
            </div>

            {{-- Rows --}}
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($rows as $row)
                @php
                    $isChannel  = ($row->product->type ?? 'regular') === 'channel';
                    $catRaw     = strtolower($row->product->category->name ?? '');
                    $isSwasta   = str_contains($catRaw, 'swasta');
                    $isType     = str_contains($catRaw, 'type');
                    $isPln      = !$isSwasta && !$isType;
                    $seriesColor = $isSwasta ? 'text-red-500 dark:text-red-400'
                                 : ($isType  ? 'text-blue-500 dark:text-blue-400'
                                 :             'text-slate-200 dark:text-white');
                    $typeBadge   = $isSwasta ? ['l' => 'Swasta',   'c' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300']
                                 : ($isType  ? ['l' => 'Typetest', 'c' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300']
                                 :             ['l' => 'PLN',      'c' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300']);
                @endphp
                <div class="px-4 py-2.5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">

                    {{-- Info kiri --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline gap-2 flex-wrap">
                            <p class="text-sm font-semibold text-slate-800 dark:text-white uppercase">{{ $row->product->name ?? '-' }}</p>
                            @if($row->product->series_with_kva)
                            <span class="text-xs {{ $seriesColor }} font-mono font-semibold">{{ $row->product->series_with_kva }}</span>
                            @endif
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold {{ $typeBadge['c'] }}">{{ $typeBadge['l'] }}</span>
                        </div>
                        @if($row->last_notes)
                        <p class="text-xs text-slate-400 italic mt-0.5">{{ Str::limit($row->last_notes, 40) }}</p>
                        @endif
                    </div>

                    {{-- Channel UP/BT + Total ditumpuk --}}
                    @if($isChannel)
                    <div class="shrink-0 flex flex-col items-end gap-1">
                        <div class="flex items-center gap-1.5 text-xs">
                            <span class="text-slate-400">UP</span>
                            <span class="font-bold text-blue-500">{{ number_format($row->total_shift1) }}</span>
                            <span class="text-slate-600">|</span>
                            <span class="text-slate-400">BT</span>
                            <span class="font-bold text-purple-500">{{ number_format($row->total_shift2) }}</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-sm font-bold
                                     bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                            <span class="text-xs font-normal opacity-70">Total:</span>
                            {{ $fmtQty($row->grand_total) }}
                            <span class="text-xs font-normal opacity-70">Unit</span>
                        </span>
                    </div>
                    @else
                    <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-sm font-bold
                                 bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                        <span class="text-xs font-normal opacity-70">Total:</span>
                        {{ $fmtQty($row->grand_total) }}
                        <span class="text-xs font-normal opacity-70">Unit</span>
                    </span>
                    @endif

                </div>
                @endforeach
            </div>

        </div>
        @endforeach
    </div>

    @else
    <div class="bg-white dark:bg-slate-800 rounded-2xl py-16 text-center border border-slate-100 dark:border-slate-700">
        <svg class="w-14 h-14 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-slate-500 dark:text-slate-400 font-medium">Tidak ada data produksi</p>
        <p class="text-slate-400 text-sm mt-1">Pilih bulan dan tahun yang berbeda</p>
    </div>
    @endif

</div>
@endsection
