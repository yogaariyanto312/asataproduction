@extends('layouts.app')

@section('title', 'Manajemen Pengguna')
@section('page-title', 'Manajemen Pengguna')
@section('page-subtitle', 'Kelola akun admin, supervisor, dan operator')

@section('content')
<div class="space-y-5" x-data="{ tab: '{{ $tab }}' }">

    {{-- Search bar --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <form method="GET" action="{{ route('management.index') }}" class="flex flex-wrap gap-3 items-end">
            <input type="hidden" name="tab" :value="tab">
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-medium text-slate-500 mb-1">Cari Pengguna</label>
                <input type="text" name="search" value="{{ $search }}"
                       placeholder="Nama, email, atau username..."
                       class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                              bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit"
                    class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors">
                Cari
            </button>
            @if($search)
            <a href="{{ route('management.index', ['tab' => $tab]) }}"
               class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600
                      text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                Reset
            </a>
            @endif
        </form>
    </div>

    {{-- Tab + Content --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

        {{-- Tab bar — tabs scroll independently, add button fixed on the right --}}
        <div class="flex items-stretch border-b border-slate-100 dark:border-slate-700">

            {{-- Scrollable tab buttons --}}
            <div class="flex overflow-x-auto flex-1 min-w-0 scrollbar-none">
                {{-- Tab Developer — hanya tampil untuk role developer --}}
                @if(auth()->user()->isDeveloper())
                <button type="button" @click="tab = 'developer'"
                        :class="tab === 'developer'
                            ? 'border-b-2 border-blue-700 text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/10'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        class="flex items-center gap-2 px-5 py-4 text-sm font-semibold transition-colors shrink-0">
                    <span class="w-2 h-2 rounded-full" style="background:linear-gradient(135deg,#1d4ed8,#0ea5e9);"></span>
                    Developer
                    <span class="text-xs px-2 py-0.5 rounded-full"
                          :class="tab === 'developer' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-500'">
                        {{ $developers->count() }}
                    </span>
                </button>
                @endif
                <button type="button" @click="tab = 'admin'"
                        :class="tab === 'admin'
                            ? 'border-b-2 border-purple-600 text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/10'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        class="flex items-center gap-2 px-5 py-4 text-sm font-semibold transition-colors shrink-0">
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                    Admin
                    <span class="text-xs px-2 py-0.5 rounded-full"
                          :class="tab === 'admin' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-500'">
                        {{ $admins->count() }}
                    </span>
                </button>
                <button type="button" @click="tab = 'supervisor'"
                        :class="tab === 'supervisor'
                            ? 'border-b-2 border-teal-600 text-teal-600 dark:text-teal-400 bg-teal-50 dark:bg-teal-900/10'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        class="flex items-center gap-2 px-5 py-4 text-sm font-semibold transition-colors shrink-0">
                    <span class="w-2 h-2 rounded-full bg-teal-500"></span>
                    Supervisor
                    <span class="text-xs px-2 py-0.5 rounded-full"
                          :class="tab === 'supervisor' ? 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-500'">
                        {{ $supervisors->count() }}
                    </span>
                </button>
                <button type="button" @click="tab = 'mandor'"
                        :class="tab === 'mandor'
                            ? 'border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/10'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        class="flex items-center gap-2 px-5 py-4 text-sm font-semibold transition-colors shrink-0">
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                    Mandor
                    <span class="text-xs px-2 py-0.5 rounded-full"
                          :class="tab === 'mandor' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-500'">
                        {{ $mandors->count() }}
                    </span>
                </button>
                <button type="button" @click="tab = 'operator'"
                        :class="tab === 'operator'
                            ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/10'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        class="flex items-center gap-2 px-5 py-4 text-sm font-semibold transition-colors shrink-0">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    Operator
                    <span class="text-xs px-2 py-0.5 rounded-full"
                          :class="tab === 'operator' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-500'">
                        {{ $operators->count() }}
                    </span>
                </button>
                <button type="button" @click="tab = 'visitor'"
                        :class="tab === 'visitor'
                            ? 'border-b-2 border-teal-500 text-teal-600 dark:text-teal-400 bg-teal-50 dark:bg-teal-900/10'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        class="flex items-center gap-2 px-5 py-4 text-sm font-semibold transition-colors shrink-0">
                    <span class="w-2 h-2 rounded-full bg-teal-400"></span>
                    Visitor
                    <span class="text-xs px-2 py-0.5 rounded-full"
                          :class="tab === 'visitor' ? 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-500'">
                        {{ $visitors->count() }}
                    </span>
                </button>
                <button type="button" @click="tab = 'department'"
                        :class="tab === 'department'
                            ? 'border-b-2 border-amber-500 text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/10'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        class="flex items-center gap-2 px-5 py-4 text-sm font-semibold transition-colors shrink-0">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Departemen
                    <span class="text-xs px-2 py-0.5 rounded-full"
                          :class="tab === 'department' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-500'">
                        {{ $departments->count() }}
                    </span>
                </button>
            </div>

            {{-- Add button — fixed to the right, separated by a border --}}
            <div class="flex items-center px-3 shrink-0 border-l border-slate-100 dark:border-slate-700">
                @if(auth()->user()->isDeveloper())
                <a x-show="tab === 'developer'" href="{{ route('developers.create') }}"
                   class="flex items-center gap-2 px-3 py-2 text-white text-sm font-semibold rounded-xl transition-colors"
                   style="background: linear-gradient(135deg,#1e3a8a,#2563eb);">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span class="hidden sm:inline">Tambah Developer</span>
                </a>
                @endif
                @if(auth()->user()->isDeveloper())
                <a x-show="tab === 'admin'" href="{{ route('admins.create') }}"
                   class="flex items-center gap-2 px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span class="hidden sm:inline">Tambah Admin</span>
                </a>
                <a x-show="tab === 'supervisor'" href="{{ route('supervisors.create') }}"
                   class="flex items-center gap-2 px-3 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span class="hidden sm:inline">Tambah Supervisor</span>
                </a>
                <a x-show="tab === 'mandor'" href="{{ route('mandors.create') }}"
                   class="flex items-center gap-2 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span class="hidden sm:inline">Tambah Mandor</span>
                </a>
                @endif
                @unless(auth()->user()->isOperator())
                <a x-show="tab === 'operator'" href="{{ route('operators.create') }}"
                   class="flex items-center gap-2 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span class="hidden sm:inline">Tambah Operator</span>
                </a>
                <a x-show="tab === 'visitor'" href="{{ route('visitors.create') }}"
                   class="flex items-center gap-2 px-3 py-2 text-white text-sm font-semibold rounded-xl transition-colors"
                   style="background: linear-gradient(135deg,#0f766e,#0d9488);">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span class="hidden sm:inline">Tambah Visitor</span>
                </a>
                @endunless
                <span x-show="tab === 'department'"
                      class="flex items-center gap-2 px-3 py-2 text-amber-600 dark:text-amber-400 text-sm font-semibold">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <span class="hidden sm:inline">Departemen</span>
                </span>
            </div>

        </div>

        {{-- ===== TAB: DEVELOPER ===== --}}
        @if(auth()->user()->isDeveloper())
        <div x-show="tab === 'developer'">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</th>
                        <th class="hidden sm:table-cell px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Email</th>
                        <th class="hidden md:table-cell px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Bergabung</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($developers as $dev)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full overflow-hidden shrink-0 flex items-center justify-center"
                                     style="{{ $dev->avatar ? '' : 'background: linear-gradient(135deg,#1e3a8a,#1d4ed8);' }}">
                                    @if($dev->avatar)
                                    <img src="{{ $dev->avatarUrl() }}" class="w-full h-full object-cover"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='';this.parentElement.style.background='linear-gradient(135deg,#1e3a8a,#1d4ed8)'">
                                    <span class="text-sm font-bold text-white" style="display:none">{{ strtoupper(substr($dev->name, 0, 1)) }}</span>
                                    @else
                                    <span class="text-sm font-bold text-white">{{ strtoupper(substr($dev->name, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800 dark:text-white flex items-center gap-1.5">
                                        {{ $dev->name }}
                                        @if($dev->id === auth()->id())
                                            <span class="text-xs px-1.5 py-0.5 rounded-full font-semibold"
                                                  style="background:#dbeafe;color:#1d4ed8;">Anda</span>
                                        @endif
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold"
                                              style="background: linear-gradient(135deg,#1e3a8a,#2563eb); color:white;">DEV</span>
                                    </p>
                                    <p class="sm:hidden text-xs text-slate-400 mt-0.5 truncate max-w-48">{{ $dev->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell px-5 py-4 text-slate-500 dark:text-slate-400 text-xs">{{ $dev->email }}</td>
                        <td class="hidden md:table-cell px-5 py-4 text-slate-500 dark:text-slate-400 text-xs">{{ $dev->created_at->format('d/m/Y') }}</td>
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('developers.edit', $dev) }}"
                                   class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @if($dev->id !== auth()->id())
                                <form method="POST" action="{{ route('developers.destroy', $dev) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" data-confirm="Hapus developer '{{ $dev->name }}'?"
                                            class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                </svg>
                                <p class="text-slate-400 text-sm">Belum ada akun developer</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        {{-- ===== TAB: ADMIN ===== --}}
        <div x-show="tab === 'admin'">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</th>
                        <th class="hidden sm:table-cell px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Bergabung</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($admins as $adm)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full overflow-hidden shrink-0 flex items-center justify-center
                                            {{ $adm->avatar ? '' : 'bg-purple-100 dark:bg-purple-900/30' }}">
                                    @if($adm->avatar)
                                    <img src="{{ $adm->avatarUrl() }}" class="w-full h-full object-cover"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='';this.parentElement.style.background='#f3e8ff'">
                                    <span class="text-sm font-bold text-purple-700" style="display:none">{{ strtoupper(substr($adm->name, 0, 1)) }}</span>
                                    @else
                                    <span class="text-sm font-bold text-purple-700 dark:text-purple-400">{{ strtoupper(substr($adm->name, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800 dark:text-white">
                                        {{ $adm->name }}
                                        @if($adm->id === auth()->id())
                                            <span class="ml-1.5 text-xs bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400 px-1.5 py-0.5 rounded-full font-semibold">Anda</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-400 mt-0.5 truncate max-w-48">{{ $adm->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell px-5 py-4 text-slate-500 dark:text-slate-400 text-xs">{{ $adm->created_at->format('d/m/Y') }}</td>
                        <td class="px-5 py-4 text-center">
                            @if(auth()->user()->isDeveloper())
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('admins.edit', $adm) }}"
                                   class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('admins.destroy', $adm) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" data-confirm="Hapus admin '{{ $adm->name }}'?"
                                            class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                            @else
                            <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-6 py-12 text-center text-slate-400 text-sm">Belum ada admin</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ===== TAB: SUPERVISOR ===== --}}
        <div x-show="tab === 'supervisor'">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</th>
                        <th class="hidden sm:table-cell px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Bergabung</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($supervisors as $spv)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full overflow-hidden shrink-0 flex items-center justify-center
                                            {{ $spv->avatar ? '' : 'bg-teal-100 dark:bg-teal-900/30' }}">
                                    @if($spv->avatar)
                                    <img src="{{ $spv->avatarUrl() }}" class="w-full h-full object-cover"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='';this.parentElement.style.background='#ccfbf1'">
                                    <span class="text-sm font-bold text-teal-700" style="display:none">{{ strtoupper(substr($spv->name, 0, 1)) }}</span>
                                    @else
                                    <span class="text-sm font-bold text-teal-700 dark:text-teal-400">{{ strtoupper(substr($spv->name, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800 dark:text-white">
                                        {{ $spv->name }}
                                        @if($spv->id === auth()->id())
                                            <span class="ml-1.5 text-xs bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-400 px-1.5 py-0.5 rounded-full font-semibold">Anda</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-400 mt-0.5 truncate max-w-48">{{ $spv->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell px-5 py-4 text-slate-500 dark:text-slate-400 text-xs">{{ $spv->created_at->format('d/m/Y') }}</td>
                        <td class="px-5 py-4 text-center">
                            @if(auth()->user()->isDeveloper())
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('supervisors.edit', $spv) }}"
                                   class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('supervisors.destroy', $spv) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" data-confirm="Hapus supervisor '{{ $spv->name }}'?"
                                            class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                            @else
                            <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-6 py-12 text-center text-slate-400 text-sm">Belum ada supervisor</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ===== TAB: MANDOR ===== --}}
        <div x-show="tab === 'mandor'">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</th>
                        <th class="hidden sm:table-cell px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Bergabung</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($mandors as $mdr)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full overflow-hidden shrink-0 flex items-center justify-center
                                            {{ $mdr->avatar ? '' : 'bg-indigo-100 dark:bg-indigo-900/30' }}">
                                    @if($mdr->avatar)
                                    <img src="{{ $mdr->avatarUrl() }}" class="w-full h-full object-cover"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='';this.parentElement.style.background='#e0e7ff'">
                                    <span class="text-sm font-bold text-indigo-700" style="display:none">{{ strtoupper(substr($mdr->name, 0, 1)) }}</span>
                                    @else
                                    <span class="text-sm font-bold text-indigo-700 dark:text-indigo-400">{{ strtoupper(substr($mdr->name, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800 dark:text-white">
                                        {{ $mdr->name }}
                                        @if($mdr->id === auth()->id())
                                            <span class="ml-1.5 text-xs bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400 px-1.5 py-0.5 rounded-full font-semibold">Anda</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-400 mt-0.5 truncate max-w-48">{{ $mdr->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell px-5 py-4 text-slate-500 dark:text-slate-400 text-xs">{{ $mdr->created_at->format('d/m/Y') }}</td>
                        <td class="px-5 py-4 text-center">
                            @if(auth()->user()->isDeveloper())
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('mandors.edit', $mdr) }}"
                                   class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('mandors.destroy', $mdr) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" data-confirm="Hapus mandor '{{ $mdr->name }}'?"
                                            class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                            @else
                            <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-6 py-12 text-center text-slate-400 text-sm">Belum ada mandor</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ===== TAB: OPERATOR ===== --}}
        <div x-show="tab === 'operator'">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</th>
                        <th class="hidden sm:table-cell px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Username</th>
                        <th class="hidden md:table-cell px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Email</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($operators as $op)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full overflow-hidden shrink-0 flex items-center justify-center
                                            {{ $op->avatar ? '' : 'bg-blue-100 dark:bg-blue-900/30' }}">
                                    @if($op->avatar)
                                    <img src="{{ $op->avatarUrl() }}" class="w-full h-full object-cover"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='';this.parentElement.style.background='#dbeafe'">
                                    <span class="text-sm font-bold text-blue-700" style="display:none">{{ strtoupper(substr($op->name, 0, 1)) }}</span>
                                    @else
                                    <span class="text-sm font-bold text-blue-700 dark:text-blue-400">{{ strtoupper(substr($op->name, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800 dark:text-white">{{ $op->name }}</p>
                                    <p class="sm:hidden text-xs text-slate-400 mt-0.5 truncate max-w-40">
                                        {{ $op->username ?? '' }}{{ ($op->username && $op->department) ? ' · ' : '' }}{{ $op->department ?? '' }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell px-5 py-4 text-slate-600 dark:text-slate-400">{{ $op->username ?? '-' }}</td>
                        <td class="hidden md:table-cell px-5 py-4 text-slate-500 dark:text-slate-400 text-xs">{{ $op->email ?? '-' }}</td>
                        <td class="px-5 py-4 text-center">
                            @if(!auth()->user()->isOperator())
                            <form method="POST" action="{{ route('operators.toggle-active', $op) }}" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="px-2.5 py-1 rounded-full text-xs font-semibold transition-colors
                                               {{ $op->is_active
                                                   ? 'bg-green-100 text-green-700 hover:bg-red-100 hover:text-red-700 dark:bg-green-900/30 dark:text-green-400'
                                                   : 'bg-red-100 text-red-700 hover:bg-green-100 hover:text-green-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                    {{ $op->is_active ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </form>
                            @else
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $op->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $op->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if(!auth()->user()->isOperator())
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('operators.edit', $op) }}"
                                   class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('operators.destroy', $op) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" data-confirm="Hapus operator '{{ $op->name }}'?"
                                            class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                            @else
                            <span class="text-xs text-slate-400 block text-center">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 text-sm">Belum ada operator</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ===== TAB: VISITOR ===== --}}
        <div x-show="tab === 'visitor'">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</th>
                        <th class="hidden sm:table-cell px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Email</th>
                        <th class="hidden md:table-cell px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Bergabung</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($visitors as $vis)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full overflow-hidden shrink-0 flex items-center justify-center"
                                     style="{{ $vis->avatar ? '' : 'background: linear-gradient(135deg,#0f766e,#0d9488);' }}">
                                    @if($vis->avatar)
                                    <img src="{{ $vis->avatarUrl() }}" class="w-full h-full object-cover"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='';this.parentElement.style.background='linear-gradient(135deg,#0f766e,#0d9488)'">
                                    <span class="text-sm font-bold text-white" style="display:none">{{ strtoupper(substr($vis->name, 0, 1)) }}</span>
                                    @else
                                    <span class="text-sm font-bold text-white">{{ strtoupper(substr($vis->name, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800 dark:text-white flex items-center gap-1.5">
                                        {{ $vis->name }}
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold"
                                              style="background:#ccfbf1;color:#0f766e;">VIEW</span>
                                    </p>
                                    <p class="sm:hidden text-xs text-slate-400 mt-0.5 truncate max-w-48">{{ $vis->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="hidden sm:table-cell px-5 py-4 text-slate-500 dark:text-slate-400 text-xs">{{ $vis->email }}</td>
                        <td class="hidden md:table-cell px-5 py-4 text-slate-500 dark:text-slate-400 text-xs">{{ $vis->created_at->format('d/m/Y') }}</td>
                        <td class="px-5 py-4">
                            @if(!auth()->user()->isOperator())
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('visitors.edit', $vis) }}"
                                   class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('visitors.destroy', $vis) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" data-confirm="Hapus visitor '{{ $vis->name }}'?"
                                            class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                            @else
                            <span class="text-xs text-slate-400 block text-center">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <p class="text-slate-400 text-sm">Belum ada akun visitor</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ===== TAB: DEPARTEMEN ===== --}}
        <div x-show="tab === 'department'" x-data="{ editId: null, editName: '', editActive: true }">

            {{-- Form tambah departemen — hanya developer --}}
            @if(auth()->user()->isDeveloper())
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                <form method="POST" action="{{ route('departments.store') }}" class="flex gap-2 items-start">
                    @csrf
                    <div class="flex-1">
                        <input type="text" name="name" value="{{ old('name') }}"
                               placeholder="Nama departemen baru..."
                               class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                                      bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                      focus:outline-none focus:ring-2 focus:ring-amber-500 placeholder-slate-400">
                        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit"
                            class="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl transition-colors shrink-0">
                        + Tambah
                    </button>
                </form>
            </div>
            @endif

            {{-- Tabel departemen --}}
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama Departemen</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Operator</th>
                        @if(auth()->user()->isDeveloper())
                        <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($departments as $dept)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-5 py-4">
                            {{-- Mode normal --}}
                            <div x-show="editId !== {{ $dept->id }}" class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ $dept->is_active ? 'bg-green-400' : 'bg-slate-400' }}"></span>
                                <span class="font-medium text-slate-800 dark:text-white">{{ $dept->name }}</span>
                                @unless($dept->is_active)
                                <span class="text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded-full font-medium">Nonaktif</span>
                                @endunless
                            </div>
                            {{-- Mode edit inline — hanya developer --}}
                            @if(auth()->user()->isDeveloper())
                            <form x-show="editId === {{ $dept->id }}"
                                  method="POST" action="{{ route('departments.update', $dept) }}"
                                  class="flex gap-2 items-center">
                                @csrf @method('PUT')
                                <input type="text" name="name" x-model="editName"
                                       class="flex-1 px-2.5 py-1.5 text-sm border border-amber-400 rounded-lg
                                              bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                              focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <input type="hidden" name="is_active" :value="editActive ? '1' : '0'">
                                <button type="button" @click="editActive = !editActive"
                                        :class="editActive ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-700'"
                                        class="px-2.5 py-1.5 text-xs font-semibold rounded-lg transition-colors shrink-0"
                                        x-text="editActive ? 'Aktif' : 'Nonaktif'"></button>
                                <button type="submit" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-lg transition-colors shrink-0">Simpan</button>
                                <button type="button" @click="editId = null"
                                        class="px-3 py-1.5 bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg transition-colors shrink-0">Batal</button>
                            </form>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-center">
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $dept->operators_count }}</span>
                            <span class="text-xs text-slate-400 ml-1">operator</span>
                        </td>
                        @if(auth()->user()->isDeveloper())
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-center gap-2" x-show="editId !== {{ $dept->id }}">
                                <button type="button"
                                        @click="editId = {{ $dept->id }}; editName = '{{ addslashes($dept->name) }}'; editActive = {{ $dept->is_active ? 'true' : 'false' }}"
                                        class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <form method="POST" action="{{ route('departments.destroy', $dept) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" data-confirm="Hapus departemen '{{ $dept->name }}'?"
                                            class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isDeveloper() ? 3 : 2 }}"
                            class="px-6 py-12 text-center text-slate-400 text-sm">Belum ada departemen</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>
@endsection
