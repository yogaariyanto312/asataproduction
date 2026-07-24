@extends('layouts.app')

@section('title', 'Detail Produksi')
@section('page-title', 'Detail Produksi')
@section('page-subtitle', 'Informasi lengkap data produksi')

@section('content')
<div class="max-w-2xl mx-auto space-y-5">

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="bg-gradient-to-r from-slate-700 to-slate-800 px-6 py-5">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-white">{{ $productionLog->product->name ?? '-' }}</h2>
                    @if($productionLog->product->series_with_kva)
                    <p class="text-slate-300 text-sm font-mono mt-1">{{ $productionLog->product->series_with_kva }}</p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-3xl font-black text-white">
                        {{ fmod((float)$productionLog->total_qty, 1) == 0 ? number_format($productionLog->total_qty) : number_format($productionLog->total_qty, 1) }}
                    </p>
                    <p class="text-slate-400 text-xs">Total Unit</p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-5">

            {{-- Channel breakdown --}}
            @if(($productionLog->product->type ?? 'regular') === 'channel')
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 text-center">
                    <p class="text-xs font-medium text-blue-600 dark:text-blue-400 mb-1">Channel UP</p>
                    <p class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ number_format($productionLog->shift1_qty) }}</p>
                    <p class="text-xs text-blue-500">unit</p>
                </div>
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-4 text-center">
                    <p class="text-xs font-medium text-purple-600 dark:text-purple-400 mb-1">Channel BT</p>
                    <p class="text-2xl font-bold text-purple-700 dark:text-purple-300">{{ number_format($productionLog->shift2_qty) }}</p>
                    <p class="text-xs text-purple-500">unit</p>
                </div>
            </div>
            <p class="text-xs text-center text-slate-400">Total = (UP + BT) ÷ 2 = ({{ $productionLog->shift1_qty }} + {{ $productionLog->shift2_qty }}) ÷ 2 = {{ fmod((float)$productionLog->total_qty, 1) == 0 ? number_format($productionLog->total_qty) : number_format($productionLog->total_qty, 1) }}</p>
            @endif

            {{-- Info rows --}}
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                <div class="flex items-center justify-between py-3">
                    <span class="text-sm text-slate-500">Tanggal Produksi</span>
                    <span class="text-sm font-medium text-slate-800 dark:text-white">
                        {{ $productionLog->production_date->translatedFormat('l, d F Y') }}
                    </span>
                </div>
                @if($productionLog->operator_name)
                <div class="flex items-center justify-between py-3">
                    <span class="text-sm text-slate-500">Nama Operator</span>
                    <span class="text-sm font-medium text-slate-800 dark:text-white">{{ $productionLog->operator_name }}</span>
                </div>
                @endif
                <div class="flex items-center justify-between py-3">
                    <span class="text-sm text-slate-500">Kategori</span>
                    <span class="text-sm font-medium text-slate-800 dark:text-white">
                        {{ $productionLog->product->category->name ?? '-' }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-3">
                    <span class="text-sm text-slate-500">Satuan</span>
                    <span class="text-sm font-medium text-slate-800 dark:text-white">
                        {{ $productionLog->product->unit ?? 'unit' }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-3">
                    <span class="text-sm text-slate-500">Diinput oleh</span>
                    <span class="text-sm font-medium text-slate-800 dark:text-white">
                        {{ $productionLog->user->name ?? '-' }}
                        <span class="text-xs text-slate-400 capitalize">({{ $productionLog->user->role ?? '' }})</span>
                    </span>
                </div>
                <div class="flex items-center justify-between py-3">
                    <span class="text-sm text-slate-500">Waktu Input</span>
                    <span class="text-sm font-medium text-slate-800 dark:text-white">
                        {{ $productionLog->created_at->format('d/m/Y H:i') }}
                    </span>
                </div>
                @php $isChannelProd = ($productionLog->product->type ?? 'regular') === 'channel'; @endphp
                @if($productionLog->notes || ($isChannelProd && ($lastChannelSerials['up'] || $lastChannelSerials['bt'])))
                <div class="py-3">
                    <span class="text-sm text-slate-500">Nomor Urut</span>
                    @if($isChannelProd)
                    @php
                        $chLines = collect(explode("\n", $productionLog->notes ?? ''))->map(fn($l) => trim($l))->filter();
                        $upLine  = $chLines->first(fn($l) => preg_match('/\bUP\b/i', $l));
                        $btLine  = $chLines->first(fn($l) => preg_match('/\bBT\b/i', $l));
                        $upDisp  = $upLine ?: ($lastChannelSerials['up'] ?? null);
                        $btDisp  = $btLine ?: ($lastChannelSerials['bt'] ?? null);
                    @endphp
                    <div class="mt-1 bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3 font-mono text-sm space-y-1">
                        @if($upDisp)
                        <p class="text-slate-800 dark:text-white">{{ $upDisp }}</p>
                        @endif
                        @if($btDisp)
                        <p class="text-slate-800 dark:text-white">{{ $btDisp }}</p>
                        @endif
                    </div>
                    @else
                    <p class="mt-1 text-sm text-slate-800 dark:text-white bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3 whitespace-pre-line font-mono">
                        {{ $productionLog->notes }}
                    </p>
                    @endif
                </div>
                @endif
                @if($productionLog->keterangan)
                <div class="py-3">
                    <span class="text-sm text-slate-500">Keterangan</span>
                    <p class="mt-1 text-sm text-slate-800 dark:text-white bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3 whitespace-pre-line">
                        {{ $productionLog->keterangan }}
                    </p>
                </div>
                @endif
            </div>

            {{-- Reject Info --}}
            @if($productionLog->reject_qty > 0)
            <div class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-xl p-4">
                <p class="text-sm font-semibold text-red-700 dark:text-red-400 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Reject / Defect
                </p>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div>
                        <p class="text-2xl font-black text-red-600 dark:text-red-400">{{ $productionLog->reject_qty }}</p>
                        <p class="text-xs text-slate-500">unit reject</p>
                    </div>
                    <div>
                        <p class="text-2xl font-black text-orange-600 dark:text-orange-400">{{ $productionLog->rejectRate() }}%</p>
                        <p class="text-xs text-slate-500">reject rate</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 mt-1">
                            {{ $productionLog->rejectCategoryLabel() }}
                        </p>
                        <p class="text-xs text-slate-500">kategori</p>
                    </div>
                </div>
                @if($productionLog->reject_notes)
                <p class="mt-3 text-xs text-slate-500 bg-white dark:bg-slate-800 rounded-lg px-3 py-2">
                    {{ $productionLog->reject_notes }}
                </p>
                @endif
            </div>
            @endif

            {{-- Buttons --}}
            <div class="flex gap-3 pt-2">
                <a href="{{ route('production.index') }}"
                   class="flex-1 py-3 text-center text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700
                          hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl font-semibold text-sm transition-colors">
                    ← Kembali
                </a>
                @unless(auth()->user()->isVisitor() || auth()->user()->isSupervisor() || auth()->user()->isMandor())
                <a href="{{ route('production.edit', $productionLog) }}"
                   class="flex-1 py-3 text-center text-white bg-amber-500 hover:bg-amber-600 rounded-xl font-semibold text-sm transition-colors">
                    Edit Data
                </a>
                @endunless
            </div>
        </div>
    </div>
</div>
@endsection
