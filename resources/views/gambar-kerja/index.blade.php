@extends('layouts.app')

@section('title', 'Gambar Kerja')
@section('page-title', 'Gambar Kerja')
@section('page-subtitle', 'Dokumen gambar teknik produksi')

@section('content')
<div class="space-y-6">

    {{-- Filter & Action Bar --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <form method="GET" action="{{ route('gambar-kerja.index') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-44">
                <label class="block text-xs font-medium text-slate-500 mb-1">Cari Judul / Seri / KVA</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Nama judul, seri, atau KVA..."
                       class="w-full px-3 py-2.5 text-sm border border-slate-300 dark:border-slate-600 rounded-xl
                              bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex gap-2">
                <button type="submit"
                        class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    Filter
                </button>
                <a href="{{ route('gambar-kerja.index') }}"
                   class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                    Reset
                </a>
                @allowedTo('gambar-kerja.upload')
                <a href="{{ route('gambar-kerja.create') }}"
                   class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Upload Gambar
                </a>
                @endallowedTo
            </div>
        </form>
    </div>

    {{-- Empty state --}}
    @if($yearGroups->isEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-2xl py-16 text-center border border-slate-100 dark:border-slate-700">
        <svg class="w-14 h-14 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-slate-500 font-medium">Belum ada gambar kerja</p>
        @allowedTo('gambar-kerja.upload')
        <a href="{{ route('gambar-kerja.create') }}"
           class="mt-4 inline-block px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700">
            Upload Sekarang
        </a>
        @endallowedTo
    </div>

    @else

    {{-- Sections per tahun --}}
    @foreach($yearGroups as $tahun => $items)
    <div class="space-y-3">

        {{-- Year Header --}}
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 px-4 py-1.5 bg-blue-600 text-white rounded-full shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="text-sm font-bold tracking-wide">{{ $tahun }}</span>
            </div>
            <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div>
            <span class="text-xs text-slate-400 font-medium">{{ $items->count() }} dokumen</span>
        </div>

        {{-- Pre-group by series category --}}
        @php
            $subGroups = [
                'standar'  => ['label' => 'Standar / PLN', 'dot' => 'bg-slate-400',  'text' => 'text-slate-500 dark:text-slate-400',   'items' => collect()],
                'swasta'   => ['label' => 'Seri Swasta',   'dot' => 'bg-amber-500',  'text' => 'text-amber-600 dark:text-amber-400',   'items' => collect()],
                'typetest' => ['label' => 'Seri Typetest', 'dot' => 'bg-purple-500', 'text' => 'text-purple-600 dark:text-purple-400', 'items' => collect()],
            ];
            foreach ($items as $_g) {
                $kat = $_g->kategori_seri ?? 'pln';
                if ($kat === 'typetest')     $subGroups['typetest']['items']->push($_g);
                elseif ($kat === 'swasta')   $subGroups['swasta']['items']->push($_g);
                else                         $subGroups['standar']['items']->push($_g);
            }
        @endphp

        @foreach($subGroups as $subKey => $sub)
        @if($sub['items']->isNotEmpty())
        <div class="space-y-3">

            {{-- Sub-category Header --}}
            <div class="flex items-center gap-2 pl-1">
                <span class="w-2 h-2 rounded-full {{ $sub['dot'] }} shrink-0"></span>
                <span class="text-xs font-bold {{ $sub['text'] }} uppercase tracking-wider">{{ $sub['label'] }}</span>
                <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div>
                <span class="text-xs text-slate-400">{{ $sub['items']->count() }} dokumen</span>
            </div>

            {{-- Cards Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($sub['items'] as $group)
            @php
                $firstFile   = $firstFiles[$group->first_id] ?? null;
                $kat         = $group->kategori_seri ?? 'pln';
                $seriesBadge = match($kat) {
                    'typetest' => ['label' => 'Typetest', 'class' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'],
                    'swasta'   => ['label' => 'Swasta',   'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'],
                    default    => null,
                };
            @endphp

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700
                        hover:shadow-md hover:border-blue-200 dark:hover:border-blue-800 transition-all flex flex-col group">

                {{-- Thumbnail --}}
                <a href="{{ route('gambar-kerja.by-group', ['judul' => $group->judul, 'seri' => $group->seri, 'kva' => $group->kva, 'tahun' => $group->tahun]) }}"
                   class="block">
                    <div class="h-36 rounded-t-2xl overflow-hidden bg-slate-100 dark:bg-slate-700 relative">
                        @if($group->group_thumbnail)
                            <img src="{{ route('storage.file', $group->group_thumbnail) }}"
                                 alt="{{ $group->judul }}"
                                 class="w-full h-full object-cover">
                        @elseif($firstFile?->isImage())
                            <img src="{{ route('storage.file', $firstFile->file_path) }}"
                                 alt="{{ $group->judul }}"
                                 class="w-full h-full object-cover">
                        @elseif($firstFile && $firstFile->isPdf())
                            <div class="w-full h-full flex flex-col items-center justify-center gap-2 bg-red-50 dark:bg-red-900/20">
                                <svg class="w-10 h-10 text-red-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 20V4h5v7h7v9H6z"/>
                                </svg>
                                <span class="text-xs font-bold text-red-500 uppercase tracking-widest">PDF</span>
                            </div>
                        @else
                            <div class="w-full h-full flex flex-col items-center justify-center gap-2 text-slate-300">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                        @endif

                        {{-- File count badge --}}
                        <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-xs font-bold
                                     bg-black/50 backdrop-blur-sm text-white">
                            {{ $group->total }} file
                        </span>

                    </div>

                    {{-- Info --}}
                    <div class="p-4 pb-3">
                        <h3 class="font-semibold text-slate-800 dark:text-white text-sm leading-tight line-clamp-2
                                    group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors min-h-10">
                            {{ $group->judul }}
                        </h3>
                        @if($seriesBadge)
                        <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $seriesBadge['class'] }}">
                            Seri {{ $seriesBadge['label'] }}
                        </span>
                        @endif
                        @if($group->seri || $group->kva)
                        <p class="text-xs font-mono text-slate-400 mt-1 truncate">
                            {{ $group->seri }}{{ $group->kva ? "-{$group->kva}KVA" : '' }}
                        </p>
                        @endif
                        @if($group->keterangan)
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 italic">{{ Str::words($group->keterangan, 18, '...') }}</p>
                        @endif
                        <div class="flex items-center justify-end mt-2">
                            <span class="text-xs font-semibold text-blue-600 dark:text-blue-400 flex items-center gap-1">
                                Lihat gambar
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                </a>

                {{-- Tombol Hapus --}}
                @allowedTo('gambar-kerja.delete')
                <div class="px-4 pb-4 mt-auto">
                    <form method="POST" action="{{ route('gambar-kerja.destroy-by-group') }}">
                        @csrf @method('DELETE')
                        <input type="hidden" name="judul" value="{{ $group->judul }}">
                        <input type="hidden" name="seri"  value="{{ $group->seri }}">
                        <input type="hidden" name="kva"   value="{{ $group->kva }}">
                        <input type="hidden" name="tahun" value="{{ $group->tahun }}">
                        <button type="submit"
                                data-confirm="Hapus semua {{ $group->total }} file gambar kerja '{{ $group->judul }}'?"
                                class="w-full flex items-center justify-center gap-2 py-2 text-xs font-medium
                                       text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 dark:text-red-400
                                       rounded-xl transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Hapus Semua
                        </button>
                    </form>
                </div>
                @endallowedTo

            </div>
            @endforeach
            </div>{{-- /Cards Grid --}}

        </div>{{-- /sub-category --}}
        @endif
        @endforeach

    </div>
    @endforeach

    @endif

</div>
@endsection
