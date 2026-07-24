@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Ringkasan produksi hari ini')

@section('content')
<div class="space-y-4">

    {{-- ====== STAT CARDS ====== --}}
    <div class="{{ auth()->user()->isVisitor() ? 'grid grid-cols-1 sm:grid-cols-3 gap-3' : 'grid grid-cols-2 lg:grid-cols-4 gap-3' }}">

        {{-- Today --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-slate-100 dark:border-slate-700">
            <div class="flex items-center justify-between mb-3">
                <div class="w-9 h-9 bg-blue-100 dark:bg-blue-900/40 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                    </svg>
                </div>
                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-green-600 bg-green-50 dark:bg-green-900/30 dark:text-green-400 px-1.5 py-0.5 rounded-full">
                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full pulse-dot"></span>Live
                </span>
            </div>
            <p id="stat-today-total" class="text-2xl font-extrabold text-slate-800 dark:text-white leading-none">{{ number_format($stats['today_total']) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-snug">Total Unit Hari Ini</p>
            <p class="text-[10px] text-slate-400 mt-0.5"><span id="stat-today-entries">{{ $stats['today_entries'] }}</span> entri</p>
        </div>

        {{-- Monthly --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-slate-100 dark:border-slate-700">
            <div class="flex items-center justify-between mb-3">
                <div class="w-9 h-9 bg-purple-100 dark:bg-purple-900/40 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
            </div>
            <p id="stat-monthly-total" class="text-2xl font-extrabold text-slate-800 dark:text-white leading-none">{{ number_format($stats['monthly_total']) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-snug">Total Bulan Ini</p>
            <p class="text-[10px] text-slate-400 mt-0.5">{{ now()->translatedFormat('F Y') }}</p>
        </div>

        {{-- Products --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-slate-100 dark:border-slate-700">
            <div class="flex items-center justify-between mb-3">
                <div class="w-9 h-9 bg-amber-100 dark:bg-amber-900/40 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-slate-800 dark:text-white leading-none">{{ $stats['total_products'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-snug">Produk Aktif</p>
            <p class="text-[10px] text-slate-400 mt-0.5">Terdaftar di sistem</p>
        </div>

        {{-- Quick Action --}}
        @unless(auth()->user()->isVisitor())
        <div class="rounded-xl p-4 shadow-sm flex flex-col"
             style="background: linear-gradient(to bottom right, #2563eb, #1d4ed8)">
            {{-- Icon + label atas --}}
            <div class="flex items-center gap-2 mb-3">
                <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="text-sm font-bold text-white">Aksi Cepat</span>
            </div>
            {{-- Tombol-tombol --}}
            <div class="flex flex-col gap-2 mt-auto">
                @unless(auth()->user()->isSupervisor())
                <a href="{{ route('production.create') }}"
                   class="flex items-center gap-2 py-2 px-3 bg-white/20 hover:bg-white/30 text-white rounded-xl text-xs font-semibold transition-colors">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Input Produksi
                </a>
                @endunless
                @if(auth()->user()->isPrivileged())
                <a href="{{ route('reports.daily') }}"
                   class="flex items-center gap-2 py-2 px-3 bg-white/20 hover:bg-white/30 text-white rounded-xl text-xs font-semibold transition-colors">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Laporan Harian
                </a>
                @endif
            </div>
        </div>
        @endunless
    </div>

    {{-- ====== TARGET vs AKTUAL + REJECT ====== --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Target Aktif --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl p-3 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-white">Target Aktif</h3>
                    <p class="text-[10px] text-slate-400">Progres sejak target dibuat</p>
                </div>
                @if(auth()->user()->isPrivileged())
                <a href="{{ route('production.targets.index') }}"
                   class="text-xs text-blue-500 hover:text-blue-700 font-medium">Kelola →</a>
                @endif
            </div>
            @if($monthlyTargetTotal > 0)
            {{-- Summary row --}}
            <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-baseline gap-1">
                    <p class="text-xl font-black text-slate-800 dark:text-white">{{ number_format($monthlyActualTotal) }}</p>
                    <span class="text-[10px] text-slate-400">/ {{ number_format($monthlyTargetTotal) }}</span>
                </div>
                <p class="text-xl font-black {{ $monthlyTargetPct >= 100 ? 'text-green-500' : ($monthlyTargetPct >= 70 ? 'text-amber-500' : 'text-blue-500') }}">
                    {{ $monthlyTargetPct }}%
                </p>
            </div>
            <div class="h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden mb-2">
                <div class="h-full rounded-full transition-all {{ $monthlyTargetPct >= 100 ? 'bg-green-500' : ($monthlyTargetPct >= 70 ? 'bg-amber-400' : 'bg-blue-500') }}"
                     style="width: {{ $monthlyTargetPct }}%"></div>
            </div>
            {{-- List per produk --}}
            <div class="overflow-y-auto no-scrollbar space-y-1.5" style="max-height: 130px;">
                @foreach($monthlyTargetsByProduct as $mt)
                @php
                    $pct = $mt['target'] > 0 ? min(round(($mt['actual'] / $mt['target']) * 100), 100) : 0;
                    $barColor = $mt['done'] ? 'bg-green-500' : ($pct >= 70 ? 'bg-amber-400' : 'bg-blue-500');
                    $textColor = $mt['done'] ? 'text-green-500 dark:text-green-400' : 'text-slate-300';
                @endphp
                <div class="rounded-lg bg-slate-50 dark:bg-slate-700/50 px-2.5 py-2">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-200 truncate block">{{ $mt['name'] }}</span>
                            @if($mt['series_kva'])
                            <span class="text-[10px] text-slate-400 font-mono leading-tight">{{ $mt['series_kva'] }}</span>
                            @endif
                        </div>
                        <span class="text-xs font-bold shrink-0 {{ $textColor }}">
                            {{ number_format($mt['actual']) }}<span class="font-normal text-slate-400">/{{ number_format($mt['target']) }}</span>
                        </span>
                    </div>
                    <div class="h-1 bg-slate-200 dark:bg-slate-600 rounded-full overflow-hidden">
                        <div class="h-full {{ $barColor }} rounded-full transition-all" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="flex flex-col items-center justify-center py-4 text-center flex-1">
                <p class="text-slate-400 text-xs">Belum ada target aktif</p>
                @if(auth()->user()->isPrivileged())
                <a href="{{ route('production.targets.index') }}"
                   class="mt-1 text-xs text-blue-500 hover:underline">Set target sekarang →</a>
                @endif
            </div>
            @endif
        </div>

        {{-- Reject Stats --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl p-3 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-white">Reject Hari Ini</h3>
                    <p class="text-[10px] text-slate-400">Defect / produk reject</p>
                </div>
                @if($todayReject > 0)
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-50 dark:bg-red-900/20 text-red-500">
                    ⚠ High Alert
                </span>
                @else
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400">
                    ✓ All Good
                </span>
                @endif
            </div>

            @if($todayReject > 0)
            {{-- Ada reject --}}
            <div class="flex items-center gap-4 mb-3">
                <div class="flex-1 bg-red-50 dark:bg-red-900/10 rounded-lg p-2.5 text-center">
                    <p id="stat-reject-count" class="text-2xl font-black text-red-500">{{ number_format($todayReject) }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">unit reject</p>
                </div>
                <div class="flex-1 bg-red-50 dark:bg-red-900/10 rounded-lg p-2.5 text-center">
                    <p id="stat-reject-pct" class="text-2xl font-black text-red-500">{{ $todayRejectPct }}%</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">reject rate</p>
                </div>
            </div>
            <div class="mt-auto">
                <div class="flex justify-between text-[10px] text-slate-400 mb-1">
                    <span>Defect rate</span>
                    <span>{{ $todayRejectPct }}%</span>
                </div>
                <div class="h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full bg-red-500 rounded-full transition-all" style="width: {{ min($todayRejectPct, 100) }}%"></div>
                </div>
            </div>
            @else
            {{-- Tidak ada reject --}}
            <div class="flex flex-col items-center justify-center flex-1 py-3">
                <div class="w-11 h-11 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center mb-2">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p id="stat-reject-count" class="hidden">0</p>
                <p id="stat-reject-pct" class="hidden">0</p>
                <p class="text-sm font-semibold text-green-600 dark:text-green-400">Tidak ada reject</p>
                <p class="text-[10px] text-slate-400 mt-0.5">Produksi berjalan lancar hari ini</p>
            </div>
            @endif
        </div>
    </div>

    {{-- ====== CHARTS ====== --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-slate-100 dark:border-slate-700">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-white">Trend Produksi</h3>
                    <p class="text-xs text-slate-400">Total unit yang diproduksi &middot; 7 hari terakhir</p>
                </div>
                <div class="flex items-center gap-3 text-xs text-slate-400">
                    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-sm bg-blue-500"></span>Total Unit</span>
                    <span class="flex items-center gap-1"><span class="inline-block w-4 h-0.5 bg-yellow-400"></span>Rata-rata / input</span>
                </div>
            </div>
            <canvas id="lineChart" height="95"></canvas>
            @php $todayTop = $chartData->last(); @endphp
            @if(!empty($todayTop['top']))
            <div class="mt-3 flex items-center gap-2 rounded-lg bg-blue-50 dark:bg-blue-500/10 px-3 py-2">
                <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                <div class="min-w-0 flex-1">
                    <span class="text-[10px] uppercase tracking-wide text-slate-400">Seri terbanyak hari ini</span>
                    <p class="text-xs font-semibold text-slate-800 dark:text-white truncate">{{ $todayTop['top'] }}</p>
                </div>
                <span class="text-xs font-bold text-blue-600 dark:text-blue-400 whitespace-nowrap">{{ number_format($todayTop['top_qty']) }} unit</span>
            </div>
            @endif
            <p class="text-[10px] text-slate-400 mt-2 leading-relaxed">
                <span class="font-medium text-slate-500 dark:text-slate-300">Total Unit</span> = jumlah unit selesai per hari.
                Arahkan kursor ke tiap bar untuk lihat jumlah input &amp; seri yang paling banyak masuk hari itu.
            </p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col">
            <div class="mb-3">
                <h3 class="text-sm font-semibold text-slate-800 dark:text-white">Produk per Tipe</h3>
                <p class="text-xs text-slate-400">{{ now()->translatedFormat('F Y') }}</p>
            </div>
            {{-- Canvas --}}
            <div class="relative mx-auto w-full" style="max-width:300px; aspect-ratio:1/1">
                <canvas id="barChart" style="position:absolute;inset:0;width:100%!important;height:100%!important"></canvas>
            </div>
            {{-- Legend HTML --}}
            <div id="doughnutLegend" class="mt-3 space-y-1.5"></div>
        </div>
    </div>

    {{-- ====== TOP OPERATOR + DEFECT RATE ====== --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

        {{-- Top Operator Hari Ini --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl p-3 shadow-sm border border-slate-100 dark:border-slate-700">
            <div class="flex items-center justify-between mb-2.5">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-white">Top Operator</h3>
                    <p class="text-[10px] text-slate-400">Hari ini · unit terproduksi</p>
                </div>
            </div>
            @php $opMax = $topOperators->max('total') ?: 1; @endphp
            <div id="operatorList" class="space-y-2">
                @forelse($topOperators as $i => $op)
                <div class="flex items-center gap-2.5">
                    <span class="w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center shrink-0
                                 {{ $i === 0 ? 'bg-amber-400 text-white' : ($i === 1 ? 'bg-slate-300 dark:bg-slate-600 text-slate-600 dark:text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400') }}">
                        {{ $i + 1 }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-200 truncate">{{ $op->operator_name }}</span>
                            <span class="text-xs font-bold text-slate-800 dark:text-white ml-2 shrink-0">
                                {{ number_format($op->total) }} <span class="font-normal text-slate-400 text-[10px]">unit</span>
                            </span>
                        </div>
                        <div class="mt-0.5 h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-blue-500 transition-all"
                                 style="width: {{ round(($op->total / $opMax) * 100) }}%"></div>
                        </div>
                    </div>
                </div>
                @empty
                <p class="text-xs text-slate-400 py-4 text-center">Belum ada data hari ini</p>
                @endforelse
            </div>
        </div>

        {{-- Top Operator Bulanan --}}
        <div class="bg-white dark:bg-slate-800 rounded-xl p-3 shadow-sm border border-slate-100 dark:border-slate-700">
            <div class="flex items-center justify-between mb-2.5">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-white">Top Operator</h3>
                    <p class="text-[10px] text-slate-400">{{ now()->translatedFormat('F Y') }} · unit terproduksi</p>
                </div>
            </div>
            <div class="space-y-2">
                @php $maxMonthly = $topOperatorsMonthly->max('total') ?: 1; @endphp
                @forelse($topOperatorsMonthly as $i => $op)
                <div class="flex items-center gap-2.5">
                    <span class="text-[11px] font-black w-5 text-center shrink-0
                                 {{ $i === 0 ? 'text-amber-500' : ($i === 1 ? 'text-slate-400' : ($i === 2 ? 'text-orange-400' : 'text-slate-500 dark:text-slate-400')) }}">
                        {{ $i + 1 }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-200 truncate">
                                {{ $op->operator_name }}@if($i === 0) 👑@endif
                            </span>
                            <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 ml-2 shrink-0">{{ number_format($op->total) }} unit</span>
                        </div>
                        <div class="mt-0.5 h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all
                                        {{ $i === 0 ? 'bg-amber-400' : 'bg-blue-500' }}"
                                 style="width: {{ round(($op->total / $maxMonthly) * 100) }}%"></div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="flex items-center justify-center py-4">
                    <span class="text-xs text-slate-400">Belum ada data produksi bulan ini</span>
                </div>
                @endforelse
            </div>
        </div>

    </div>

    {{-- ====== CATATAN + KALENDER ====== --}}
    @php $isPrivileged = auth()->user()->isPrivileged(); @endphp
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Catatan --}}
        @php
            $noteColors = [
                'blue'   => ['bg' => 'bg-blue-500',   'light' => 'bg-blue-50 dark:bg-blue-900/20',   'text' => 'text-blue-600 dark:text-blue-400'],
                'green'  => ['bg' => 'bg-green-500',  'light' => 'bg-green-50 dark:bg-green-900/20', 'text' => 'text-green-600 dark:text-green-400'],
                'yellow' => ['bg' => 'bg-yellow-400', 'light' => 'bg-yellow-50 dark:bg-yellow-900/20','text' => 'text-yellow-600 dark:text-yellow-400'],
                'amber'  => ['bg' => 'bg-amber-500',  'light' => 'bg-amber-50 dark:bg-amber-900/20', 'text' => 'text-amber-600 dark:text-amber-400'],
                'red'    => ['bg' => 'bg-red-500',    'light' => 'bg-red-50 dark:bg-red-900/20',     'text' => 'text-red-600 dark:text-red-400'],
                'purple' => ['bg' => 'bg-purple-500', 'light' => 'bg-purple-50 dark:bg-purple-900/20','text' => 'text-purple-600 dark:text-purple-400'],
                'teal'   => ['bg' => 'bg-teal-500',   'light' => 'bg-teal-50 dark:bg-teal-900/20',   'text' => 'text-teal-600 dark:text-teal-400'],
                'slate'  => ['bg' => 'bg-slate-500',  'light' => 'bg-slate-100 dark:bg-slate-700',   'text' => 'text-slate-600 dark:text-slate-400'],
            ];
        @endphp
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden flex flex-col">
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 bg-amber-100 dark:bg-amber-900/40 rounded-lg flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-white">Catatan</h3>
                        <p class="text-xs text-slate-400">{{ $recentNotes->count() }} catatan terbaru</p>
                    </div>
                </div>
                @if($isPrivileged)
                <a href="{{ route('notes.index') }}"
                   class="flex items-center gap-1 px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-lg transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah
                </a>
                @endif
            </div>

            {{-- List catatan --}}
            <div class="divide-y divide-slate-100 dark:divide-slate-700 flex-1 overflow-y-auto max-h-64">
                @forelse($recentNotes as $note)
                @php $nc = $noteColors[$note->color ?? 'slate']; @endphp
                <div class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
                    {{-- Strip warna --}}
                    <span class="w-1 h-full min-h-9 rounded-full {{ $nc['bg'] }} shrink-0 mt-0.5"></span>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate
                                  {{ $note->is_done ? 'line-through text-slate-400' : 'text-slate-800 dark:text-white' }}">
                            {{ $note->title }}
                        </p>
                        <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                            @if($note->content)
                            <p class="text-xs text-slate-400 truncate max-w-40">{{ $note->content }}</p>
                            @endif
                            @if($note->due_date)
                            <span class="text-xs {{ $note->due_date->isPast() && !$note->is_done ? 'text-red-500' : 'text-slate-400' }} shrink-0">
                                · {{ $note->due_date->translatedFormat('d M') }}
                            </span>
                            @endif
                            @if($note->target_user_id && $note->target_user_id !== auth()->id())
                            <span class="text-xs text-slate-400 shrink-0">· → {{ $note->targetUser->name ?? '-' }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Status badge --}}
                    @if($note->is_done)
                    <span class="shrink-0 text-xs px-1.5 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded font-medium">
                        ✓ Selesai
                    </span>
                    @else
                    <span class="shrink-0 w-2 h-2 rounded-full bg-amber-400 mt-1.5"></span>
                    @endif
                </div>
                @empty
                <div class="flex flex-col items-center justify-center py-10 text-slate-400">
                    <svg class="w-8 h-8 mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <p class="text-sm">Belum ada catatan</p>
                </div>
                @endforelse
            </div>

            {{-- Footer --}}
            <div class="px-4 py-2 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/30">
                <a href="{{ route('notes.index') }}" class="text-xs text-amber-600 hover:text-amber-700 dark:text-amber-400 font-medium">
                    Lihat semua catatan →
                </a>
            </div>
        </div>

    {{-- Kalender --}}
        @php
            $calNow      = \Carbon\Carbon::now();
            $daysInMonth = $calNow->daysInMonth;
            $startOffset = ($calNow->copy()->startOfMonth()->dayOfWeek + 6) % 7;
            $today       = (int) $calNow->day;
            $calYear     = (int) $calNow->year;
            $calMonth    = (int) $calNow->month;
            // $holidays sudah di-pass dari DashboardController via HolidayService
        @endphp
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
            {{-- Header bulan --}}
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center gap-2">
                <div class="w-7 h-7 bg-blue-100 dark:bg-blue-900/40 rounded-lg flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-white">{{ $calNow->translatedFormat('F Y') }}</h3>
                    <p class="text-xs text-slate-400">{{ $calNow->translatedFormat('l, d F Y') }}</p>
                </div>
            </div>

            <div class="p-4">
                {{-- Nama hari --}}
                <div class="mb-1" style="display:grid;grid-template-columns:repeat(7,1fr)">
                    @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $d)
                    <div class="text-center py-1 text-xs font-semibold
                                {{ in_array($d, ['Sab','Min']) ? 'text-rose-400' : 'text-slate-400 dark:text-slate-500' }}">
                        {{ $d }}
                    </div>
                    @endforeach
                </div>

                {{-- Grid tanggal --}}
                <div style="display:grid;grid-template-columns:repeat(7,1fr);row-gap:2px">
                    @for($i = 0; $i < $startOffset; $i++)<div></div>@endfor

                    @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $dateStr   = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $day);
                        $dow       = ($calNow->copy()->day($day)->dayOfWeek + 6) % 7;
                        $isWeekend = $dow >= 5;
                        $isToday   = $day === $today;
                        $holiday   = $holidays->get($dateStr);
                        $isHoliday = (bool) $holiday;
                        $dayEvents = $calendarEvents->get($dateStr);
                        $hasEvent  = $dayEvents && $dayEvents->count() > 0;
                    @endphp
                    <button type="button" data-cal-day="{{ $dateStr }}"
                            class="js-cal-day relative group flex flex-col items-center py-0.5 cursor-pointer focus:outline-none">
                        {{-- Tanggal --}}
                        <div class="flex items-center justify-center rounded-full w-7 h-7 text-xs font-medium transition-colors
                            {{ $isToday                              ? 'bg-blue-600 text-white font-bold shadow-md' : '' }}
                            {{ !$isToday && ($isWeekend||$isHoliday) ? 'text-rose-400' : '' }}
                            {{ !$isToday && !$isWeekend && !$isHoliday ? 'text-slate-600 dark:text-slate-300 group-hover:bg-slate-100 dark:group-hover:bg-slate-700' : '' }}
                            {{ !$isToday && $hasEvent ? 'ring-2 ring-amber-400/70' : '' }}">
                            {{ $day }}
                        </div>
                        {{-- Dot penanda (libur = merah, event = amber) --}}
                        <div class="flex items-center gap-0.5 mt-0.5 h-1">
                            @if($isHoliday)<span class="block w-1 h-1 rounded-full bg-rose-400"></span>@endif
                            @if($hasEvent)<span class="block w-1 h-1 rounded-full bg-amber-400"></span>@endif
                        </div>

                        {{-- Tooltip hari libur / event --}}
                        @if($isHoliday || $hasEvent)
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-30
                                    hidden group-hover:block w-max max-w-52 bg-slate-900
                                    text-white rounded-xl px-3 py-2 shadow-2xl pointer-events-none text-center"
                             style="font-size:11px">
                            <p class="font-bold {{ $isHoliday ? 'text-rose-300' : 'text-amber-300' }}">{{ \Carbon\Carbon::parse($dateStr)->translatedFormat('d F Y') }}</p>
                            @if($isHoliday)<p class="text-slate-200 mt-0.5 leading-snug">{{ $holiday['name'] }}</p>@endif
                            @if($hasEvent)
                                @foreach($dayEvents as $ev)
                                <p class="text-amber-100 mt-0.5 leading-snug">• {{ $ev->title }}</p>
                                @endforeach
                            @endif
                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-900"></div>
                        </div>
                        @endif
                    </button>
                    @endfor
                </div>

                {{-- Legend --}}
                <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-slate-400">
                    <span class="flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded-full bg-blue-600 shrink-0"></span>Hari ini
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400 shrink-0"></span>Hari libur nasional
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>Ada agenda
                    </span>
                    <span class="ml-auto text-slate-400 dark:text-slate-500 italic">Klik tanggal untuk tambah agenda</span>
                </div>
            </div>
        </div>

    </div>

    {{-- ====== MODAL AGENDA / EVENT KALENDER ====== --}}
    <div id="calEventModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden"
             style="max-height:90vh; display:flex; flex-direction:column">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-white font-bold text-base">Agenda</h3>
                    <p id="calEventDate" class="text-blue-100 text-xs mt-0.5"></p>
                </div>
                <button type="button" id="calEventClose" class="text-white/80 hover:text-white p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Daftar agenda --}}
            <div id="calEventList" class="px-5 py-4 space-y-2 overflow-y-auto" style="min-height:60px"></div>

            {{-- Form tambah --}}
            <div class="px-5 pb-5 pt-3 border-t border-slate-100 dark:border-slate-700">
                <input type="hidden" id="calEventInputDate">
                <input type="text" id="calEventTitle" maxlength="150" placeholder="Judul agenda (mis. Meeting, Kirim barang...)"
                       class="w-full px-3 py-2.5 mb-2 text-sm rounded-xl bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-white
                              border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <textarea id="calEventDesc" rows="2" maxlength="1000" placeholder="Keterangan (opsional)..."
                          class="w-full px-3 py-2.5 mb-2 text-sm rounded-xl bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-white
                                 border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                <button type="button" id="calEventSave"
                        class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold text-sm transition-colors">
                    + Tambah Agenda
                </button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const modal   = document.getElementById('calEventModal');
        if (!modal) return;
        const dateLbl = document.getElementById('calEventDate');
        const listEl  = document.getElementById('calEventList');
        const inputDt = document.getElementById('calEventInputDate');
        const titleEl = document.getElementById('calEventTitle');
        const descEl  = document.getElementById('calEventDesc');
        const saveBtn = document.getElementById('calEventSave');
        const csrf    = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const STORE_URL = '{{ route('calendar.events.store') }}';
        const DEL_BASE  = '{{ url('calendar-events') }}';

        // Data awal event per tanggal (Y-m-d → [ {id,title,description,by,can_delete} ])
        @php
            $calEventMap = $calendarEvents->map(fn($items) => $items->map(fn($e) => [
                'id'          => $e->id,
                'title'       => $e->title,
                'description' => $e->description,
                'by'          => $e->created_by_name,
                'can_delete'  => auth()->id() === $e->user_id || auth()->user()->isPrivileged(),
            ])->values());
        @endphp
        const eventMap = {!! json_encode($calEventMap) !!};

        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const fmtDate = (ds) => {
            const [y,m,d] = ds.split('-').map(Number);
            const bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            return `${d} ${bulan[m-1]} ${y}`;
        };

        function renderList(ds) {
            const items = eventMap[ds] || [];
            if (!items.length) {
                listEl.innerHTML = '<p class="text-sm text-slate-400 text-center py-3">Belum ada agenda. Tambahkan di bawah.</p>';
                return;
            }
            listEl.innerHTML = items.map(ev => `
                <div class="flex items-start gap-2 bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mt-1.5 shrink-0"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 break-words">${esc(ev.title)}</p>
                        ${ev.description ? `<p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5 break-words whitespace-pre-line">${esc(ev.description)}</p>` : ''}
                        ${ev.by ? `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">oleh ${esc(ev.by)}</p>` : ''}
                    </div>
                    ${ev.can_delete ? `<button type="button" data-del="${ev.id}" class="text-slate-400 hover:text-red-500 p-1 shrink-0" title="Hapus">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>` : ''}
                </div>`).join('');
        }

        // Perbarui penanda (dot + ring) pada sel tanggal di kalender
        function updateCell(ds) {
            const btn = document.querySelector(`.js-cal-day[data-cal-day="${ds}"]`);
            if (!btn) return;
            const has = (eventMap[ds] || []).length > 0;
            const circle = btn.querySelector('div.rounded-full');
            const dotRow = btn.querySelector('div.flex.items-center.gap-0\\.5');
            const isToday = circle?.classList.contains('bg-blue-600');
            if (circle && !isToday) circle.classList.toggle('ring-2', has);
            if (circle && !isToday) circle.classList.toggle('ring-amber-400/70', has);
            if (dotRow) {
                let amber = dotRow.querySelector('.cal-event-dot');
                if (has && !amber) {
                    amber = document.createElement('span');
                    amber.className = 'cal-event-dot block w-1 h-1 rounded-full bg-amber-400';
                    dotRow.appendChild(amber);
                } else if (!has && amber) {
                    amber.remove();
                }
            }
        }

        function openModal(ds) {
            inputDt.value = ds;
            dateLbl.textContent = fmtDate(ds);
            titleEl.value = ''; descEl.value = '';
            renderList(ds);
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => titleEl.focus(), 50);
        }
        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Klik tanggal
        document.querySelectorAll('.js-cal-day').forEach(btn => {
            btn.addEventListener('click', () => openModal(btn.dataset.calDay));
        });
        document.getElementById('calEventClose')?.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

        // Simpan agenda
        saveBtn.addEventListener('click', async () => {
            const ds = inputDt.value;
            const title = titleEl.value.trim();
            if (!title) { titleEl.focus(); return; }
            saveBtn.disabled = true; saveBtn.textContent = 'Menyimpan...';
            try {
                const res = await fetch(STORE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ event_date: ds, title, description: descEl.value.trim() || null }),
                });
                if (res.ok) {
                    const data = await res.json();
                    if (!eventMap[ds]) eventMap[ds] = [];
                    eventMap[ds].push(data.event);
                    titleEl.value = ''; descEl.value = '';
                    renderList(ds);
                    updateCell(ds);
                } else {
                    notify('Gagal menyimpan agenda.');
                }
            } catch (_) { notify('Koneksi bermasalah.'); }
            saveBtn.disabled = false; saveBtn.textContent = '+ Tambah Agenda';
        });

        // Modal konfirmasi/alert kustom (fallback ke bawaan bila belum tersedia)
        const askConfirm = (msg, opts) =>
            (window.appConfirmAsync ? window.appConfirmAsync(msg, opts) : Promise.resolve(confirm(msg)));
        const notify = (msg, opts) =>
            (window.appAlert ? window.appAlert(msg, opts) : alert(msg));

        // Hapus agenda (event delegation)
        listEl.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-del]');
            if (!btn) return;
            const id = btn.dataset.del;
            const ds = inputDt.value;
            const ok = await askConfirm('Agenda ini akan dihapus permanen.', {
                title: 'Hapus Agenda?', okText: 'Ya, Hapus'
            });
            if (!ok) return;
            try {
                const res = await fetch(`${DEL_BASE}/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (res.ok) {
                    eventMap[ds] = (eventMap[ds] || []).filter(ev => String(ev.id) !== String(id));
                    renderList(ds);
                    updateCell(ds);
                } else {
                    notify('Gagal menghapus agenda.');
                }
            } catch (_) { notify('Koneksi bermasalah.'); }
        });

        // Enter di judul → simpan
        titleEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); saveBtn.click(); } });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('flex')) closeModal(); });
    }());
    </script>

    @if($isPrivileged)
    {{-- ====== MODAL EDIT CEPAT KATEGORI ====== --}}
    <div id="editCategoryModal"
         class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 bg-gradient-to-r from-amber-500 to-orange-500">
                <h3 class="text-sm font-bold text-white">Edit Cepat Kategori</h3>
                <button onclick="closeEditModal()" class="p-1 rounded-lg text-amber-100 hover:text-white hover:bg-white/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="editCategoryForm" method="POST" class="p-5 space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Nama Kategori <span class="text-red-500">*</span></label>
                    <input type="text" id="modal_name" name="name"
                           class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Kode</label>
                        <input type="text" id="modal_code" name="code"
                               class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm font-mono uppercase" placeholder="Opsional">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 cursor-pointer pb-1">
                            <input type="checkbox" id="modal_is_active" name="is_active" value="1"
                                   class="w-4 h-4 rounded border-slate-300 text-amber-500 focus:ring-amber-500">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Aktif</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Keterangan</label>
                    <textarea id="modal_description" name="description" rows="2"
                              class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm resize-none"
                              placeholder="Deskripsi..."></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeEditModal()"
                            class="flex-1 py-2 text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl text-sm font-semibold transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="flex-1 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-semibold transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    @endif

    {{-- ====== RECENT LOGS ====== --}}
    {{-- Input Produksi Terbaru — card layout --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-white">Input Produksi Terbaru</h3>
            <a href="{{ route('production.index') }}" class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 font-medium">
                Lihat semua →
            </a>
        </div>

        <div class="p-4 space-y-5">
            @php
                $fmtQty    = fn($n) => number_format($n, 0, ',', '.');
                $dateGroups = $recentLogs->groupBy(fn($l) => $l->production_date->format('Y-m-d'));
            @endphp

            @forelse($dateGroups as $dateStr => $dayLogs)
            @php
                $carbon    = \Illuminate\Support\Carbon::parse($dateStr)->locale('id');
                $dayName   = $carbon->translatedFormat('l');
                $dateFmt   = $carbon->translatedFormat('d F Y');
                $catOrder  = ['Channel' => 0, 'Cover' => 1, 'Tangki' => 2];
                $catGroups = $dayLogs->groupBy(function($l) {
                    $n = strtolower($l->product->category->name ?? '');
                    if (str_contains($n, 'channel')) return 'Channel';
                    if (str_contains($n, 'cover'))   return 'Cover';
                    if (str_contains($n, 'tangki'))  return 'Tangki';
                    return $l->product->category->name ?? 'Lainnya';
                })->sortBy(fn($logs, $key) => $catOrder[$key] ?? 99);
                $dayTotal  = $dayLogs->sum('total_qty');
            @endphp
            <div class="space-y-3">

                {{-- Date header --}}
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 px-3 py-1 bg-slate-700 dark:bg-slate-600 text-white rounded-full shrink-0">
                        <svg class="w-3 h-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-[11px] font-semibold text-slate-300">{{ $dayName }}</span>
                        <span class="text-xs font-bold">{{ $dateFmt }}</span>
                    </div>
                    <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div>
                    <span class="text-xs text-slate-400 font-medium shrink-0">
                        {{ $fmtQty($dayTotal) }} unit · {{ $dayLogs->count() }} entri
                    </span>
                </div>

                {{-- Category cards per hari --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-3">
                    @foreach($catGroups as $catName => $catLogs)
                    @php
                        $catTotal    = $catLogs->sum('total_qty');
                        $catNameLow  = strtolower($catName);
                        if (str_contains($catNameLow, 'channel')) {
                            $dotColor  = 'bg-blue-500';
                            $textColor = 'text-blue-600 dark:text-blue-400';
                        } elseif (str_contains($catNameLow, 'cover')) {
                            $dotColor  = 'bg-emerald-500';
                            $textColor = 'text-emerald-600 dark:text-emerald-400';
                        } elseif (str_contains($catNameLow, 'tangki')) {
                            $dotColor  = 'bg-amber-500';
                            $textColor = 'text-amber-600 dark:text-amber-400';
                        } else {
                            $dotColor  = 'bg-slate-400';
                            $textColor = 'text-slate-600 dark:text-slate-300';
                        }
                    @endphp
                    <div class="bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-100 dark:border-slate-700 overflow-hidden">

                        {{-- Card header --}}
                        <div class="flex items-center justify-between px-3 py-2.5 border-b border-slate-100 dark:border-slate-700 bg-white/50 dark:bg-slate-800/50">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ $dotColor }} shrink-0"></span>
                                <span class="text-xs font-bold text-slate-700 dark:text-white">{{ $catName }}</span>
                                <span class="text-[10px] text-slate-400">· {{ $catLogs->count() }} entri</span>
                            </div>
                            <span class="text-xs font-bold {{ $textColor }}">
                                {{ $fmtQty($catTotal) }} <span class="text-[10px] font-normal text-slate-400">unit</span>
                            </span>
                        </div>

                        {{-- Entry rows --}}
                        <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            @foreach($catLogs as $log)
                            @php
                                $isChannel = ($log->product->type ?? 'regular') === 'channel';
                                $catRaw    = strtolower($log->product->category->name ?? '');
                                $isSwasta  = str_contains($catRaw, 'swasta');
                                $isType    = str_contains($catRaw, 'type');
                                $typeBadge = $isSwasta
                                    ? ['l' => 'Swasta',   'c' => 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400']
                                    : ($isType
                                    ? ['l' => 'Typetest', 'c' => 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400']
                                    : null);
                            @endphp
                            <div class="px-3 py-2 flex items-center gap-2 hover:bg-white dark:hover:bg-slate-800/60 transition-colors">

                                {{-- Info kiri --}}
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <p class="text-xs font-semibold text-slate-800 dark:text-white">{{ preg_replace('/\s+(typetest|swasta|pln)\b/i', '', $log->product->name ?? '-') }}</p>
                                        @if($log->product->series_with_kva)
                                        <span class="text-[10px] text-slate-400 font-mono">{{ $log->product->series_with_kva }}</span>
                                        @endif
                                        @if($typeBadge)
                                        <span class="text-[9px] px-1 py-0.5 rounded-full font-semibold {{ $typeBadge['c'] }}">{{ $typeBadge['l'] }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                        @if($log->notes)
                                        <span class="text-[10px] text-slate-400 italic">{{ Str::limit($log->notes, 35) }}</span>
                                        <span class="text-slate-300 dark:text-slate-600 text-[10px]">·</span>
                                        @endif
                                        <span class="text-[10px] text-slate-400">{{ $log->user->name ?? '-' }}</span>
                                    </div>
                                </div>

                                {{-- Channel: UP/BT + Total --}}
                                @if($isChannel)
                                <div class="shrink-0 flex flex-col items-end gap-0.5">
                                    <div class="flex items-center gap-1 text-[10px]">
                                        <span class="text-slate-400">UP</span>
                                        <span class="font-bold text-blue-500">{{ number_format($log->shift1_qty) }}</span>
                                        <span class="text-slate-500">|</span>
                                        <span class="text-slate-400">BT</span>
                                        <span class="font-bold text-purple-500">{{ number_format($log->shift2_qty) }}</span>
                                    </div>
                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-bold
                                                 bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                        <span class="text-[10px] font-normal opacity-70">Total:</span>
                                        {{ $fmtQty($log->total_qty) }}
                                        <span class="text-[10px] font-normal opacity-70">Unit</span>
                                    </span>
                                </div>
                                @else
                                {{-- Regular: Total saja --}}
                                <span class="shrink-0 inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-bold
                                             bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                    <span class="text-[10px] font-normal opacity-70">Total:</span>
                                    {{ $fmtQty($log->total_qty) }}
                                    <span class="text-[10px] font-normal opacity-70">Unit</span>
                                </span>
                                @endif

                            </div>
                            @endforeach
                        </div>

                    </div>
                    @endforeach
                </div>

            </div>
            @empty
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <div class="w-12 h-12 bg-slate-100 dark:bg-slate-700 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <p class="text-sm text-slate-400">Belum ada data produksi</p>
                @if(!auth()->user()->isVisitor() && !auth()->user()->isSupervisor())
                <a href="{{ route('production.create') }}" class="mt-1 text-blue-600 text-xs hover:underline">Input sekarang →</a>
                @endif
            </div>
            @endforelse
        </div>
    </div>

</div>

{{-- ====== AUTO REFRESH TOGGLE (menempel di akhir konten, rata kanan) ====== --}}
<div class="mt-4 flex justify-end">
    <div id="autoRefreshBtn"
         class="hidden sm:inline-flex items-center gap-2 px-3 py-2 rounded-full shadow-sm cursor-pointer select-none
                bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:shadow-md transition-all">
        <span id="refreshDot" class="w-2 h-2 rounded-full bg-green-500 pulse-dot shrink-0"></span>
        <span id="refreshLabel" class="text-xs font-semibold text-slate-700 dark:text-slate-200">Live</span>
        <span id="refreshCountdown" class="text-xs text-slate-400">30s</span>
        <span id="refreshUpdatedAt" class="hidden text-[10px] text-slate-400 sm:inline"></span>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
const modal     = document.getElementById('editCategoryModal');
const modalForm = document.getElementById('editCategoryForm');
function openEditModal(id, name, code, description, isActive) {
    modalForm.action = '/categories/' + id;
    document.getElementById('modal_name').value        = name;
    document.getElementById('modal_code').value        = code;
    document.getElementById('modal_description').value = description;
    document.getElementById('modal_is_active').checked = isActive;
    modal.classList.remove('hidden'); modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('modal_name').focus(), 50);
}
function closeEditModal() {
    modal.classList.add('hidden'); modal.classList.remove('flex');
    document.body.style.overflow = '';
}
modal?.addEventListener('click', e => { if (e.target === modal) closeEditModal(); });
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modal?.classList.contains('hidden')) closeEditModal();
});
</script>
<script>
(function () {
const isDark    = document.documentElement.classList.contains('dark');
const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
const textColor = isDark ? '#94a3b8' : '#64748b';

const trendEntries = {!! json_encode($chartData->pluck('entries')) !!};
const trendAvg     = {!! json_encode($chartData->pluck('avg')) !!};
const trendTop     = {!! json_encode($chartData->pluck('top')) !!};
const trendTopQty  = {!! json_encode($chartData->pluck('top_qty')) !!};

// Hancurkan chart lama di canvas yang sama agar tidak ada sisa/hantu
if (window.Chart) Chart.getChart(document.getElementById('lineChart'))?.destroy();
new Chart(document.getElementById('lineChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($chartData->pluck('date')) !!},
        datasets: [
            {
                type: 'bar',
                label: 'Total Unit',
                data: {!! json_encode($chartData->pluck('total')) !!},
                backgroundColor: 'rgba(59,130,246,0.85)',
                hoverBackgroundColor: 'rgba(59,130,246,1)',
                borderRadius: 6,
                borderSkipped: false,
                order: 2,
            },
            {
                type: 'line',
                label: 'Rata-rata / input',
                data: trendAvg,
                borderColor: '#facc15',
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [4, 3],
                pointBackgroundColor: '#facc15',
                pointRadius: 3,
                pointHoverRadius: 5,
                tension: 0.4,
                order: 1,
            },
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        layout: { padding: { top: 18 } },
        plugins: {
            legend: { display: false },
            // Angka di atas tiap bar agar nilai langsung terbaca tanpa hover
            datalabels: {
                display: ctx => ctx.dataset.type === 'bar' && ctx.dataset.data[ctx.dataIndex] > 0,
                anchor: 'end', align: 'end', offset: 2,
                color: textColor, font: { size: 10, weight: '600' },
                formatter: v => v.toLocaleString('id-ID'),
            },
            tooltip: {
                callbacks: {
                    title: items => items[0].label,
                    label: ctx => {
                        if (ctx.dataset.label === 'Total Unit') {
                            const i    = ctx.dataIndex;
                            const e    = trendEntries[i] ?? 0;
                            const rows = [
                                `  Total: ${ctx.parsed.y.toLocaleString('id-ID')} unit`,
                                `  Jumlah input: ${e}x`,
                            ];
                            if (trendTop[i]) {
                                rows.push(`  Terbanyak: ${trendTop[i]} (${(trendTopQty[i] ?? 0).toLocaleString('id-ID')} unit)`);
                            }
                            return rows;
                        }
                        return `  Rata-rata: ${ctx.parsed.y} unit / input`;
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { color: textColor, font: { size: 10 } }
            },
            y: {
                grid: { color: gridColor },
                ticks: { color: textColor, font: { size: 10 }, callback: v => v.toLocaleString('id-ID') },
                beginAtZero: true,
            }
        }
    }
});

const doughnutLabels = {!! json_encode($productChart->pluck('name')) !!};
const doughnutData   = {!! json_encode($productChart->pluck('total')) !!};
// Palet ungu monokrom premium (gelap → terang)
const doughnutColors = ['#5b52e8','#7c73f0','#a5a0f7','#4338ca','#c7c3fb','#8b83f3','#6d63ec'];
const doughnutTotal  = doughnutData.reduce((a, b) => a + b, 0);

// Campur warna hex dengan putih (amt 0..1) → hasilkan tint lebih terang
function lighten(hex, amt) {
    const n = parseInt(hex.slice(1), 16);
    const r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
    const mix = (c) => Math.round(c + (255 - c) * amt);
    return `rgb(${mix(r)},${mix(g)},${mix(b)})`;
}

// Gradasi vertikal per segmen (terang di atas → warna dasar di bawah)
function arcGradient(chart, i) {
    const base = doughnutColors[i % doughnutColors.length];
    const area = chart.chartArea;
    if (!area) return base;
    const g = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
    g.addColorStop(0, lighten(base, 0.28));
    g.addColorStop(1, base);
    return g;
}

// Plugin: bayangan lembut di belakang segmen untuk efek kedalaman
const arcShadowPlugin = {
    id: 'arcShadow',
    beforeDatasetsDraw(chart) {
        if (chart.config.type !== 'doughnut') return;
        const c = chart.ctx;
        c.save();
        c.shadowColor   = isDark ? 'rgba(0,0,0,0.45)' : 'rgba(99,102,241,0.25)';
        c.shadowBlur    = 14;
        c.shadowOffsetY = 5;
    },
    afterDatasetsDraw(chart) {
        if (chart.config.type !== 'doughnut') return;
        chart.ctx.restore();
    }
};

// Plugin: total di tengah donut (halus)
const centerTextPlugin = {
    id: 'centerText',
    afterDraw(chart) {
        if (chart.config.type !== 'doughnut') return;
        const { ctx, chartArea: { left, right, top, bottom } } = chart;
        const cx = (left + right) / 2;
        const cy = (top + bottom) / 2;
        const main = isDark ? '#f1f5f9' : '#1e293b';
        const sub  = isDark ? 'rgba(241,245,249,0.55)' : 'rgba(30,41,59,0.5)';
        ctx.save();
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.font = `700 24px Inter,sans-serif`;
        ctx.fillStyle = main;
        ctx.fillText(doughnutTotal.toLocaleString('id-ID'), cx, cy - 2);
        ctx.font = `500 10px Inter,sans-serif`;
        ctx.fillStyle = sub;
        ctx.fillText('total unit', cx, cy + 18);
        ctx.restore();
    }
};

// Plugin: label persen + nama tipe di luar tiap segmen (tanpa perlu hover)
const outerLabelPlugin = {
    id: 'outerLabel',
    afterDraw(chart) {
        if (chart.config.type !== 'doughnut' || !doughnutTotal) return;
        const { ctx } = chart;
        const meta = chart.getDatasetMeta(0);
        const pctColor  = isDark ? '#e2e8f0' : '#334155';
        const nameColor = isDark ? '#94a3b8' : '#64748b';
        ctx.save();
        ctx.textAlign = 'center';
        meta.data.forEach((arc, i) => {
            const val = doughnutData[i];
            if (!val) return;
            const pct = (val / doughnutTotal * 100);
            const pctText = (pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(1)) + '%';
            const name = String(doughnutLabels[i] ?? '');
            const ang = (arc.startAngle + arc.endAngle) / 2;
            const r   = arc.outerRadius + 28;
            const x   = arc.x + Math.cos(ang) * r;
            const y   = arc.y + Math.sin(ang) * r;
            // Persen (atas)
            ctx.textBaseline = 'bottom';
            ctx.font = `700 12px Inter,sans-serif`;
            ctx.fillStyle = pctColor;
            ctx.fillText(pctText, x, y);
            // Nama tipe (bawah)
            ctx.textBaseline = 'top';
            ctx.font = `600 9px Inter,sans-serif`;
            ctx.fillStyle = nameColor;
            ctx.fillText(name.toUpperCase(), x, y + 1);
        });
        ctx.restore();
    }
};

if (window.Chart) Chart.getChart(document.getElementById('barChart'))?.destroy();
new Chart(document.getElementById('barChart').getContext('2d'), {
    type: 'doughnut',
    plugins: [arcShadowPlugin, centerTextPlugin, outerLabelPlugin],
    data: {
        labels: doughnutLabels,
        datasets: [{
            data: doughnutData,
            backgroundColor: (ctx) => arcGradient(ctx.chart, ctx.dataIndex),
            borderWidth: 0,
            spacing: 3,
            borderRadius: 30,
            hoverOffset: 10,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        layout: { padding: 62 },
        plugins: {
            legend: { display: false },
            datalabels: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const pct = doughnutTotal > 0 ? Math.round(ctx.parsed / doughnutTotal * 100) : 0;
                        return `  ${ctx.parsed.toLocaleString('id-ID')} unit (${pct}%)`;
                    }
                }
            }
        }
    }
});

// Render legend HTML
const legendEl = document.getElementById('doughnutLegend');
if (legendEl && doughnutData.length) {
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    legendEl.innerHTML = doughnutLabels.map((label, i) => {
        const val = doughnutData[i];
        const pct = doughnutTotal > 0 ? Math.round(val / doughnutTotal * 100) : 0;
        const w   = doughnutTotal > 0 ? Math.round(val / doughnutTotal * 100) : 0;
        return `
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:${doughnutColors[i % doughnutColors.length]}"></span>
            <span class="text-xs text-slate-600 dark:text-slate-300 flex-1 truncate">${esc(label)}</span>
            <span class="text-xs font-semibold text-slate-800 dark:text-white">${val.toLocaleString('id-ID')} <span class="font-normal text-slate-400">Unit</span></span>
            <div class="w-16 bg-slate-100 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                <div class="h-full rounded-full" style="width:${w}%;background:${doughnutColors[i % doughnutColors.length]}"></div>
            </div>
            <span class="text-xs text-slate-400 w-7 text-right">${pct}%</span>
        </div>`;
    }).join('');
}
})();
</script>

<script>
let operatorChartInstance = null; // kept for compat with fetchLiveStats

// ====== AUTO REFRESH ======
const AR_INTERVAL = 60;
let arEnabled  = localStorage.getItem('dashAutoRefresh') !== 'false';
let cdVal      = AR_INTERVAL;

// Timers stored on window so SPA re-navigation can clear them across eval contexts
clearInterval(window._dashArTimer);
clearInterval(window._dashCdTimer);
let arTimer = null;
let cdTimer = null;

async function fetchLiveStats() {
    try {
        const r = await fetch('{{ route("api.dashboard.live") }}');
        if (!r.ok) return;
        const d = await r.json();
        const fmt  = n => Math.round(n).toLocaleString('id-ID');
        const set  = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };

        set('stat-today-total',   fmt(d.today_total));
        set('stat-today-entries', d.today_entries);
        set('stat-monthly-total', fmt(d.monthly_total));
        set('stat-reject-count',  fmt(d.today_reject));
        set('stat-reject-pct',    d.reject_pct + '%');

        if (d.target_pct !== null) {
            set('stat-target-actual', fmt(d.total_actual));
            set('stat-target-total',  fmt(d.total_target));
            set('stat-target-pct',    d.target_pct + '%');
            const bar = document.getElementById('stat-target-bar');
            if (bar) bar.style.width = d.target_pct + '%';
        }

        const listEl = document.getElementById('operatorList');
        if (listEl && d.top_operators?.length) {
            const esc   = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
            const maxT  = Math.max(...d.top_operators.map(o => o.total), 1);
            const rk    = ['bg-amber-400 text-white', 'bg-slate-300 text-slate-600', 'bg-slate-100 text-slate-500'];
            listEl.innerHTML = d.top_operators.map((o, i) => {
                const pct = Math.round((o.total / maxT) * 100);
                const rc  = rk[i] ?? rk[2];
                return `<div class="flex items-center gap-2.5">
                    <span class="w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center shrink-0 ${rc}">${i + 1}</span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-200 truncate">${esc(o.name)}</span>
                            <span class="text-xs font-bold text-slate-800 dark:text-white ml-2 shrink-0">${o.total.toLocaleString('id-ID')} <span class="font-normal text-slate-400 text-[10px]">unit</span></span>
                        </div>
                        <div class="mt-0.5 h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-blue-500 transition-all" style="width:${pct}%"></div>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        const ua = document.getElementById('refreshUpdatedAt');
        if (ua) { ua.textContent = ' · ' + d.updated_at; ua.classList.remove('hidden'); }
    } catch {}
}

function arStart() {
    cdVal = AR_INTERVAL;
    fetchLiveStats();
    arTimer = window._dashArTimer = setInterval(fetchLiveStats, AR_INTERVAL * 1000);
    cdTimer = window._dashCdTimer = setInterval(() => {
        cdVal--;
        if (cdVal <= 0) cdVal = AR_INTERVAL;
        const el = document.getElementById('refreshCountdown');
        if (el) el.textContent = cdVal + 's';
    }, 1000);
    arSetUI(true);
}

function arStop() {
    clearInterval(arTimer); clearInterval(cdTimer);
    arTimer = window._dashArTimer = null;
    cdTimer = window._dashCdTimer = null;
    arSetUI(false);
}

function arSetUI(on) {
    const dot   = document.getElementById('refreshDot');
    const label = document.getElementById('refreshLabel');
    const count = document.getElementById('refreshCountdown');
    if (dot) {
        dot.classList.toggle('bg-green-500', on);
        dot.classList.toggle('pulse-dot', on);
        dot.classList.toggle('bg-slate-400', !on);
    }
    if (label) label.textContent = on ? 'Live' : 'Paused';
    if (count) { count.textContent = AR_INTERVAL + 's'; count.classList.toggle('hidden', !on); }
}

document.getElementById('autoRefreshBtn')?.addEventListener('click', () => {
    arEnabled = !arEnabled;
    localStorage.setItem('dashAutoRefresh', arEnabled);
    arEnabled ? arStart() : arStop();
});

arEnabled ? arStart() : arSetUI(false);

// Bersihkan timer saat SPA navigasi ke halaman lain
document.addEventListener('spa:leave', function() {
    clearInterval(window._dashArTimer);
    clearInterval(window._dashCdTimer);
    window._dashArTimer = null;
    window._dashCdTimer = null;
}, { once: true });
</script>
@endpush
