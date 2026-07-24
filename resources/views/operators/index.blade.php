@extends('layouts.app')

@section('title', 'Manajemen Operator')
@section('page-title', 'Manajemen Operator')
@section('page-subtitle', 'Kelola akun operator yang dapat input produksi')

@section('content')
<div class="space-y-5">

    {{-- Filter + Tambah --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <form method="GET" action="{{ route('operators.index') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-medium text-slate-500 mb-1">Cari Operator</label>
                <input type="text" name="search" id="search-realtime"
                       value="{{ request('search') }}"
                       placeholder="Nama, username, atau email..."
                       class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                              bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                <select name="status"
                        class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                               bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <button type="submit"
                    class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors">
                Filter
            </button>
            <a href="{{ route('operators.index') }}"
               class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600
                      text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                Reset
            </a>
            <div class="flex-1"></div>
            <a href="{{ route('operators.create') }}"
               class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Operator
            </a>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-700">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Menampilkan <strong class="text-slate-700 dark:text-slate-300">{{ $operators->count() }}</strong>
                dari <strong class="text-slate-700 dark:text-slate-300">{{ $operators->total() }}</strong> operator
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Username</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Departemen</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($operators as $op)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center shrink-0">
                                    <span class="text-sm font-bold text-blue-700 dark:text-blue-400">
                                        {{ strtoupper(substr($op->name, 0, 1)) }}
                                    </span>
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800 dark:text-white">{{ $op->name }}</p>
                                    <p class="text-xs text-slate-400">Bergabung {{ $op->created_at->format('d/m/Y') }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 font-mono text-sm text-slate-600 dark:text-slate-400">
                            {{ $op->username ?? '-' }}
                        </td>
                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                            {{ $op->department ?? '-' }}
                        </td>
                        <td class="px-5 py-4 text-center">
                            <form method="POST" action="{{ route('operators.toggle-active', $op) }}" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold transition-colors
                                               {{ $op->is_active
                                                   ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 hover:bg-green-200 dark:hover:bg-green-900/50'
                                                   : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50' }}"
                                        title="Klik untuk {{ $op->is_active ? 'nonaktifkan' : 'aktifkan' }}">
                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $op->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                    {{ $op->is_active ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('operators.edit', $op) }}"
                                   class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors"
                                   title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form method="POST" action="{{ route('operators.destroy', $op) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            data-confirm="Hapus operator '{{ $op->name }}'? Aksi ini tidak bisa dibatalkan."
                                            class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                            title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center">
                            <svg class="w-14 h-14 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="text-slate-500 dark:text-slate-400 font-medium">Belum ada operator</p>
                            <a href="{{ route('operators.create') }}"
                               class="mt-4 inline-block px-5 py-2 bg-green-600 text-white rounded-xl text-sm font-semibold hover:bg-green-700">
                                Tambah Operator Sekarang
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($operators->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700">
            {{ $operators->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
