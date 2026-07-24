@extends('layouts.app')

@section('title', 'Hak Akses Menu')
@section('page-title', 'Hak Akses Menu & Aksi')
@section('page-subtitle', 'Atur menu & aksi yang bisa diakses tiap role / departemen')

@section('content')
@php
    $roleLabels = [
        'admin' => 'Admin', 'supervisor' => 'Supervisor',
        'mandor' => 'Mandor', 'operator' => 'Operator', 'visitor' => 'Visitor',
    ];
    $deptMode = !empty($selectedDept);
@endphp
<div class="space-y-5">

    {{-- Info --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl p-4 flex gap-3">
        <svg class="w-5 h-5 shrink-0 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
            <p><b>Lihat</b> = boleh membuka menu. Baris di bawahnya = izin aksi (Tambah/Edit/Hapus/Export). Menu/aksi yang dimatikan akan <b>disembunyikan</b> & URL-nya <b>ditolak (403)</b>.</p>
            <p class="text-xs text-blue-600/80 dark:text-blue-400/80">
                Akses efektif = <b>role</b> mengizinkan <b>DAN</b> <b>departemen</b> mengizinkan. Role <b>Developer</b> selalu penuh.
                Menu <b>Master Produk</b> &amp; <b>Kategori</b> ditandai <b>shared</b> (data seri produk dipakai semua departemen), namun kini bisa diatur manual per departemen.
            </p>
        </div>
    </div>

    {{-- Pemilih mode: Role default atau per Departemen --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-4">
        <form method="GET" action="{{ route('permissions.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Atur hak akses untuk</label>
                <select name="department" onchange="this.form.submit()"
                        class="px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                               bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 min-w-[220px]">
                    <option value="">Semua Role (default)</option>
                    @foreach($departments as $d)
                    <option value="{{ $d }}" {{ $selectedDept === $d ? 'selected' : '' }}>Departemen: {{ $d }}</option>
                    @endforeach
                </select>
            </div>
            @if($deptMode)
            <div class="text-xs text-amber-600 dark:text-amber-400 pb-2.5">
                Sedang mengatur <b>Departemen {{ $selectedDept }}</b>. Ini membatasi menu untuk <i>semua role</i> di departemen tsb (kecuali developer).
            </div>
            @endif
        </form>
    </div>

    <form method="POST" action="{{ route('permissions.update') }}">
        @csrf
        @if($deptMode)<input type="hidden" name="department" value="{{ $selectedDept }}">@endif

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium sticky left-0 bg-slate-50 dark:bg-slate-900/50 z-10">Menu / Aksi</th>
                            @if($deptMode)
                            <th class="px-4 py-3 text-center font-medium whitespace-nowrap min-w-[120px]">Izinkan</th>
                            @else
                            @foreach($roles as $role)
                            <th class="px-4 py-3 text-center font-medium whitespace-nowrap min-w-[90px]">{{ $roleLabels[$role] ?? ucfirst($role) }}</th>
                            @endforeach
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @php $colCount = $deptMode ? 2 : count($roles) + 1; @endphp
                        @foreach($rows as $row)
                        {{-- Header menu --}}
                        <tr class="bg-slate-50/70 dark:bg-slate-900/30">
                            <td colspan="{{ $colCount }}" class="px-4 py-2.5 sticky left-0 bg-slate-50/70 dark:bg-slate-900/30 z-10">
                                <div class="flex items-center gap-2.5 font-bold text-slate-800 dark:text-white">
                                    <svg class="w-4 h-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        @foreach($row['paths'] ?? [$row['icon']] as $p)
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $p }}"/>
                                        @endforeach
                                    </svg>
                                    {{ $row['label'] }}
                                    @if($deptMode && ($row['shared'] ?? false))
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Shared</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        {{-- Baris permission --}}
                        @foreach($row['perms'] as $perm)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                            <td class="pl-11 pr-4 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap sticky left-0 bg-white dark:bg-slate-800 z-10">
                                {{ $perm['label'] }}
                            </td>
                            @if($deptMode)
                            <td class="px-4 py-2.5 text-center">
                                @php $isChecked = $deptState[$perm['key']] ?? true; @endphp
                                <label class="relative inline-flex items-center cursor-pointer"
                                       title="{{ ($perm['shared'] ?? false) ? 'Menu shared (data seri produk dipakai semua departemen) — atur manual di sini' : '' }}">
                                    <input type="checkbox" name="allowed[{{ $perm['key'] }}]" value="1"
                                           class="peer absolute inset-0 w-full h-full m-0 opacity-0 cursor-pointer" {{ $isChecked ? 'checked' : '' }}>
                                    <span class="relative w-10 h-5 bg-slate-300 dark:bg-slate-600 rounded-full
                                                 peer-checked:bg-blue-600 transition-colors
                                                 after:content-[''] after:absolute after:top-0.5 after:left-0.5
                                                 after:w-4 after:h-4 after:bg-white after:rounded-full after:transition-transform
                                                 peer-checked:after:translate-x-5"></span>
                                </label>
                            </td>
                            @else
                            @foreach($roles as $role)
                            @php $isChecked = $state["{$role}|{$perm['key']}"] ?? false; @endphp
                            <td class="px-4 py-2.5 text-center">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="allowed[{{ $role }}][{{ $perm['key'] }}]" value="1"
                                           class="peer absolute inset-0 w-full h-full m-0 opacity-0 cursor-pointer" {{ $isChecked ? 'checked' : '' }}>
                                    <span class="relative w-10 h-5 bg-slate-300 dark:bg-slate-600 rounded-full
                                                 peer-checked:bg-blue-600 transition-colors
                                                 after:content-[''] after:absolute after:top-0.5 after:left-0.5
                                                 after:w-4 after:h-4 after:bg-white after:rounded-full after:transition-transform
                                                 peer-checked:after:translate-x-5"></span>
                                </label>
                            </td>
                            @endforeach
                            @endif
                        </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end mt-5">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white
                           bg-blue-600 hover:bg-blue-700 rounded-xl shadow-lg shadow-blue-900/20 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Simpan Perubahan{{ $deptMode ? ' — '.$selectedDept : '' }}
            </button>
        </div>
    </form>
</div>
@endsection
