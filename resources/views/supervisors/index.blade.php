@extends('layouts.app')

@section('title', 'Manajemen Supervisor')
@section('page-title', 'Manajemen Supervisor')
@section('page-subtitle', 'Kelola akun supervisor yang dapat mengakses laporan dan data produksi')

@section('content')
<div class="space-y-5">

    {{-- Filter + Tambah --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <form method="GET" action="{{ route('supervisors.index') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-medium text-slate-500 mb-1">Cari Supervisor</label>
                <input type="text" name="search" id="search-realtime"
                       value="{{ request('search') }}"
                       placeholder="Nama atau email..."
                       class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                              bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <button type="submit"
                    class="px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-xl transition-colors">
                Filter
            </button>
            <a href="{{ route('supervisors.index') }}"
               class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600
                      text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                Reset
            </a>
            <div class="flex-1"></div>
            <a href="{{ route('supervisors.create') }}"
               class="px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Supervisor
            </a>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-700">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Menampilkan <strong class="text-slate-700 dark:text-slate-300">{{ $supervisors->count() }}</strong>
                dari <strong class="text-slate-700 dark:text-slate-300">{{ $supervisors->total() }}</strong> supervisor
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Email</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Bergabung</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($supervisors as $spv)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center shrink-0">
                                    <span class="text-sm font-bold text-teal-700 dark:text-teal-400">
                                        {{ strtoupper(substr($spv->name, 0, 1)) }}
                                    </span>
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800 dark:text-white">
                                        {{ $spv->name }}
                                        @if($spv->id === auth()->id())
                                            <span class="ml-1.5 text-xs bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-400 px-1.5 py-0.5 rounded-full font-semibold">Anda</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                            {{ $spv->email }}
                        </td>
                        <td class="px-5 py-4 text-slate-500 dark:text-slate-400 text-xs">
                            {{ $spv->created_at->format('d/m/Y') }}
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('supervisors.edit', $spv) }}"
                                   class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors"
                                   title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                @if($spv->id !== auth()->id())
                                <form method="POST" action="{{ route('supervisors.destroy', $spv) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            data-confirm="Hapus supervisor '{{ $spv->name }}'? Aksi ini tidak bisa dibatalkan."
                                            class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                            title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                                @else
                                <span class="p-1.5 text-slate-200 dark:text-slate-700 cursor-not-allowed" title="Tidak bisa hapus akun sendiri">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-16 text-center">
                            <svg class="w-14 h-14 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="text-slate-500 dark:text-slate-400 font-medium">Belum ada supervisor</p>
                            <a href="{{ route('supervisors.create') }}"
                               class="mt-4 inline-block px-5 py-2 bg-teal-600 text-white rounded-xl text-sm font-semibold hover:bg-teal-700">
                                Tambah Supervisor Sekarang
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($supervisors->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700">
            {{ $supervisors->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
