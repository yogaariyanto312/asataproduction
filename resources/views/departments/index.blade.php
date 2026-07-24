@extends('layouts.app')

@section('title', 'Departemen')
@section('page-title', 'Departemen')
@section('page-subtitle', 'Kelola daftar departemen operator')

@section('content')
<div class="space-y-5">

    {{-- Filter + Tambah --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <form method="GET" action="{{ route('departments.index') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-medium text-slate-500 mb-1">Cari Departemen</label>
                <input type="text" name="search" id="search-realtime"
                       value="{{ request('search') }}"
                       placeholder="Nama departemen..."
                       class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                              bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit"
                    class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors">
                Filter
            </button>
            <a href="{{ route('departments.index') }}"
               class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600
                      text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                Reset
            </a>
            <div class="flex-1"></div>
            <a href="{{ route('departments.create') }}"
               class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Departemen
            </a>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-700">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                <strong class="text-slate-700 dark:text-slate-300">{{ $departments->total() }}</strong> departemen terdaftar
            </p>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($departments as $dept)
            <div class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors group">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0
                            {{ $dept->is_active ? 'bg-teal-100 dark:bg-teal-900/30' : 'bg-slate-100 dark:bg-slate-700' }}">
                    <span class="text-sm font-bold {{ $dept->is_active ? 'text-teal-700 dark:text-teal-400' : 'text-slate-400' }}">
                        {{ strtoupper(substr($dept->name, 0, 2)) }}
                    </span>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-sm text-slate-800 dark:text-white">{{ $dept->name }}</span>
                        @if(!$dept->is_active)
                        <span class="text-xs text-red-500 bg-red-50 dark:bg-red-900/20 px-1.5 py-0.5 rounded">Nonaktif</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $dept->operators_count }} operator terdaftar</p>
                </div>

                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                    <a href="{{ route('departments.edit', $dept) }}"
                       class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors"
                       title="Edit">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </a>
                    <form method="POST" action="{{ route('departments.destroy', $dept) }}" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit"
                                data-confirm="Hapus departemen '{{ $dept->name }}'?"
                                class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                title="Hapus">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
            @empty
            <div class="px-6 py-16 text-center">
                <svg class="w-14 h-14 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <p class="text-slate-500 dark:text-slate-400 font-medium">Belum ada departemen</p>
                <a href="{{ route('departments.create') }}"
                   class="mt-4 inline-block px-5 py-2 bg-green-600 text-white rounded-xl text-sm font-semibold hover:bg-green-700">
                    Tambah Departemen
                </a>
            </div>
            @endforelse
        </div>

        @if($departments->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700">
            {{ $departments->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
