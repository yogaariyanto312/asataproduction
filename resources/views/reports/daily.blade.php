@extends('layouts.app')

@section('title', 'Laporan Harian')
@section('page-title', 'Laporan Harian')
@section('page-subtitle', 'Rekap produksi per hari')

@section('content')
<div class="space-y-5">

    {{-- Filter Date --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            @if(!empty($deptFilter))<input type="hidden" name="department" value="{{ $deptFilter }}">@endif
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Tanggal</label>
                <input type="date" name="date" value="{{ $date }}" max="{{ today()->toDateString() }}"
                       class="px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                              bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit"
                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl">
                Tampilkan
            </button>
            <div class="flex-1"></div>
            @allowedTo('laporan.export')
            <a href="{{ route('reports.daily-pdf', array_filter(['date' => $date, 'department' => $deptFilter ?? null])) }}"
               class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Export PDF
            </a>
            @endallowedTo
            <button onclick="window.print()"
                    class="px-4 py-2.5 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print
            </button>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 text-center">
            <p class="text-xs text-blue-500 mb-1">UP</p>
            <p class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ number_format($totalShift1) }}</p>
        </div>
        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-4 text-center">
            <p class="text-xs text-purple-500 mb-1">BT</p>
            <p class="text-2xl font-bold text-purple-700 dark:text-purple-300">{{ number_format($totalShift2) }}</p>
        </div>
        <div class="bg-slate-800 rounded-xl p-4 text-center">
            <p class="text-xs text-slate-400 mb-1">Grand Total</p>
            <p class="text-2xl font-bold text-white">{{ number_format($grandTotal) }}</p>
        </div>
    </div>

    {{-- Daily Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
            <h3 class="font-semibold text-slate-800 dark:text-white">
                Laporan Harian — {{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">No</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Produk</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Seri</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Kategori</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase">UP</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase">BT</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Total</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">No. Urut</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Operator</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($logs as $i => $log)
                    @php $isChannel = ($log->product->type ?? 'regular') === 'channel'; @endphp
                    <tr>
                        <td class="px-5 py-3 text-slate-400">{{ $i + 1 }}</td>
                        <td class="px-5 py-3">
                            <div class="font-semibold text-slate-800 dark:text-white">{{ $log->product->name ?? '-' }}</div>
                        </td>
                        <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $log->product->series_with_kva ?: '-' }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $log->product->category->name ?? '-' }}</td>
                        <td class="px-5 py-3 text-center font-medium text-slate-700 dark:text-slate-200">{{ number_format($log->shift1_qty) }}</td>
                        <td class="px-5 py-3 text-center font-medium text-slate-700 dark:text-slate-200">{{ number_format($log->shift2_qty) }}</td>
                        <td class="px-5 py-3 text-center font-bold text-blue-700 dark:text-blue-400">{{ number_format($log->total_qty) }}</td>
                        <td class="px-5 py-3 font-mono text-xs text-slate-500 whitespace-pre-line">{{ $log->notes ?: '-' }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $log->user->name ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                            Tidak ada data produksi untuk tanggal ini
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($logs->count() > 0)
                <tfoot class="bg-slate-50 dark:bg-slate-900/50 border-t-2 border-slate-300">
                    <tr>
                        <td colspan="4" class="px-5 py-3 text-sm font-bold text-slate-700 dark:text-slate-300">TOTAL</td>
                        <td class="px-5 py-3 text-center font-bold text-slate-800 dark:text-white">{{ number_format($totalShift1) }}</td>
                        <td class="px-5 py-3 text-center font-bold text-slate-800 dark:text-white">{{ number_format($totalShift2) }}</td>
                        <td class="px-5 py-3 text-center font-black text-blue-700">{{ number_format($grandTotal) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
