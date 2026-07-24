@extends('layouts.app')

@section('title', "Gambar Kerja — {$judul}")
@section('page-title', $judul)
@php $seriKva = ($seri ?? '') . ($kva ? "({$kva})" : ''); @endphp
@section('page-subtitle', "Gambar Kerja" . ($seriKva ? " · {$seriKva}" : '') . " · {$gambarKerja->count()} file")

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm border border-slate-100 dark:border-slate-700">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-bold text-slate-800 dark:text-white">{{ $judul }}</h2>
                @if($seriKva)
                <p class="text-sm font-mono text-slate-400 mt-0.5">{{ $seriKva }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                @allowedTo('gambar-kerja.upload')
                <a href="{{ route('gambar-kerja.create', ['judul' => $judul, 'seri' => $seri, 'kva' => $kva, 'tahun' => $tahun]) }}"
                   class="flex items-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah File
                </a>
                @endallowedTo
                <a href="{{ route('gambar-kerja.index') }}"
                   class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                    ← Kembali
                </a>
            </div>
        </div>

        {{-- Thumbnail Upload (privileged only) --}}
        @allowedTo('gambar-kerja.edit')
        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700"
             x-data="{ preview: '{{ $thumbnailPath ? route('storage.file', $thumbnailPath) : '' }}' }">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Thumbnail Card</p>
            <div class="flex items-center gap-4 flex-wrap">

                {{-- Preview thumbnail --}}
                <div class="w-24 h-16 rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-700 shrink-0 border border-slate-200 dark:border-slate-600">
                    <template x-if="preview">
                        <img :src="preview" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!preview">
                        <div class="w-full h-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </template>
                </div>

                {{-- Upload form --}}
                <form method="POST" action="{{ route('gambar-kerja.upload-thumbnail') }}"
                      enctype="multipart/form-data" class="flex items-center gap-3 flex-wrap">
                    @csrf
                    <input type="hidden" name="judul" value="{{ $judul }}">
                    <input type="hidden" name="seri"  value="{{ $seri }}">
                    <input type="hidden" name="kva"   value="{{ $kva }}">
                    <input type="hidden" name="tahun" value="{{ $tahun }}">

                    <label class="relative cursor-pointer">
                        <input type="file" name="thumbnail" accept="image/jpg,image/jpeg,image/png"
                               class="absolute inset-0 opacity-0 w-full h-full cursor-pointer"
                               @change="preview = URL.createObjectURL($event.target.files[0]); $el.closest('form').querySelector('[type=submit]').removeAttribute('disabled')">
                        <span class="flex items-center gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-700
                                     hover:bg-slate-200 dark:hover:bg-slate-600
                                     text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Pilih Gambar
                        </span>
                    </label>

                    <button type="submit" disabled
                            class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed
                                   text-white text-sm font-semibold rounded-xl transition-colors">
                        Simpan Thumbnail
                    </button>
                </form>

                {{-- Hapus thumbnail (hanya tampil jika ada thumbnail tersimpan) --}}
                @if($thumbnailPath)
                <form method="POST" action="{{ route('gambar-kerja.delete-thumbnail') }}">
                    @csrf @method('DELETE')
                    <input type="hidden" name="judul" value="{{ $judul }}">
                    <input type="hidden" name="seri"  value="{{ $seri }}">
                    <input type="hidden" name="kva"   value="{{ $kva }}">
                    <input type="hidden" name="tahun" value="{{ $tahun }}">
                    <button type="submit" @click="preview = ''"
                            class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold rounded-xl transition-colors
                                   text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 dark:text-red-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Hapus Thumbnail
                    </button>
                </form>
                @endif

                <p class="text-xs text-slate-400 w-full sm:w-auto">JPG / PNG · maks. 5MB</p>
            </div>
            @error('thumbnail')<p class="mt-2 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        {{-- Kategori Seri --}}
        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Kategori Seri</p>
            <form method="POST" action="{{ route('gambar-kerja.update-kategori') }}" class="flex items-center gap-3 flex-wrap">
                @csrf @method('PATCH')
                <input type="hidden" name="judul" value="{{ $judul }}">
                <input type="hidden" name="seri"  value="{{ $seri }}">
                <input type="hidden" name="kva"   value="{{ $kva }}">
                <input type="hidden" name="tahun" value="{{ $tahun }}">

                <div class="flex gap-2" x-data="{ kat: '{{ $kategoriSeri }}' }">
                    @foreach([
                        'pln'      => ['label' => 'Standar / PLN', 'dot' => 'bg-slate-400',  'active' => 'bg-slate-100 dark:bg-slate-700 ring-2 ring-slate-400 text-slate-700 dark:text-slate-200',  'idle' => 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50'],
                        'swasta'   => ['label' => 'Seri Swasta',   'dot' => 'bg-amber-500',  'active' => 'bg-amber-50 dark:bg-amber-900/30 ring-2 ring-amber-500 text-amber-700 dark:text-amber-300',  'idle' => 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-amber-50 dark:hover:bg-amber-900/10'],
                        'typetest' => ['label' => 'Seri Typetest', 'dot' => 'bg-purple-500', 'active' => 'bg-purple-50 dark:bg-purple-900/30 ring-2 ring-purple-500 text-purple-700 dark:text-purple-300', 'idle' => 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-purple-50 dark:hover:bg-purple-900/10'],
                    ] as $val => $opt)
                    <label class="cursor-pointer">
                        <input type="radio" name="kategori_seri" value="{{ $val }}"
                               x-model="kat" class="sr-only">
                        <span class="flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-600 text-xs font-semibold transition-all"
                              :class="kat === '{{ $val }}' ? '{{ $opt['active'] }}' : '{{ $opt['idle'] }}'">
                            <span class="w-2 h-2 rounded-full {{ $opt['dot'] }} shrink-0"></span>
                            {{ $opt['label'] }}
                        </span>
                    </label>
                    @endforeach

                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-xl transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>

        {{-- Edit Judul & Keterangan --}}
        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Edit Judul & Keterangan</p>

            <form method="POST" action="{{ route('gambar-kerja.update-info') }}"
                  class="space-y-3">
                @csrf @method('PATCH')
                <input type="hidden" name="judul" value="{{ $judul }}">
                <input type="hidden" name="seri"  value="{{ $seri }}">
                <input type="hidden" name="kva"   value="{{ $kva }}">
                <input type="hidden" name="tahun" value="{{ $tahun }}">

                <div x-data="{
                        chars: {{ strlen(old('judul_baru', $judul)) }}, showToast: false, _t: null,
                        check(v) {
                            const c = v.length;
                            if (c > 40 && this.chars <= 40) {
                                this.showToast = true;
                                clearTimeout(this._t);
                                this._t = setTimeout(() => this.showToast = false, 3000);
                            }
                            this.chars = c;
                        }
                    }">
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                        Judul <span class="text-red-500">*</span>
                        <span class="font-normal" :class="chars > 46 ? 'text-red-500' : 'text-slate-400'"
                              x-text="'(' + chars + '/46)'"></span>
                    </label>
                    <input type="text" name="judul_baru" value="{{ old('judul_baru', $judul) }}" required
                           @input="check($event.target.value)"
                           class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                                  text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500"
                           :class="chars > 46 ? 'border-red-400! ring-red-400!' : ''">
                    @error('judul_baru')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror

                    <div x-show="showToast"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 translate-y-2"
                         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2 px-4 py-3
                                bg-amber-500 text-white rounded-xl shadow-xl text-sm font-semibold whitespace-nowrap">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.448 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Judul mendekati batas maksimum (46 karakter)
                    </div>
                </div>

                <div x-data="{
                        words: 0, showToast: false, _t: null,
                        check(v) {
                            const w = v.trim() ? v.trim().split(/\s+/).length : 0;
                            if (w > 18 && this.words <= 18) {
                                this.showToast = true;
                                clearTimeout(this._t);
                                this._t = setTimeout(() => this.showToast = false, 3000);
                            }
                            this.words = w;
                        }
                    }">
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                        Keterangan
                        <span class="font-normal" :class="words > 18 ? 'text-red-500' : 'text-slate-400'" x-text="'(' + words + '/18 kata)'"></span>
                    </label>
                    <textarea name="keterangan" rows="2"
                              @input="check($event.target.value)"
                              placeholder="Catatan untuk semua file di grup ini..."
                              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                                     text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                              :class="words > 18 ? 'border-red-400! ring-red-400!' : ''">{{ old('keterangan', $gambarKerja->first()?->keterangan) }}</textarea>

                    {{-- Toast --}}
                    <div x-show="showToast"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 translate-y-2"
                         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2 px-4 py-3
                                bg-amber-500 text-white rounded-xl shadow-xl text-sm font-semibold whitespace-nowrap">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.448 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Kalimat Anda melebihi batas maksimum (18 kata)
                    </div>
                </div>

                <div>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-xl transition-colors">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
        @endallowedTo
    </div>

    {{-- Daftar file --}}
    @if($gambarKerja->isEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-2xl py-16 text-center border border-slate-100 dark:border-slate-700">
        <svg class="w-14 h-14 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-slate-500 font-medium">Belum ada file untuk dokumen ini</p>
    </div>
    @else
    <div class="space-y-4">
        @foreach($gambarKerja as $item)
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

            {{-- Header file --}}
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 dark:border-slate-700">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-blue-600 text-white text-sm font-bold flex items-center justify-center shrink-0">
                        {{ $item->urutan }}
                    </span>
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-white text-sm">File {{ $item->urutan }}</p>
                        @if($item->keterangan)
                        <p class="text-xs text-slate-400">{{ $item->keterangan }}</p>
                        @endif
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold
                        {{ $item->isImage() ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700' }}">
                        {{ $item->isImage() ? 'Gambar' : 'PDF' }}
                    </span>
                </div>
                <div class="flex items-center gap-2 flex-wrap justify-end">
                    <span class="text-xs text-slate-400 hidden sm:block">{{ $item->created_at->format('d M Y') }}</span>

                    @if(auth()->user()->isPrivileged())
                    <a href="{{ route('storage.file', $item->file_path) }}" target="_blank" download
                       class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium
                              text-green-700 bg-green-50 hover:bg-green-100 dark:bg-green-900/20 dark:hover:bg-green-900/40 dark:text-green-400 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span class="hidden sm:inline">Download</span>
                    </a>
                    @endif

                    @allowedTo('gambar-kerja.delete')
                    <form method="POST" action="{{ route('gambar-kerja.destroy', $item) }}">
                        @csrf @method('DELETE')
                        <button type="submit"
                                data-confirm="Hapus file {{ $item->urutan }} dari '{{ $judul }}'? Nomor urut akan diperbarui otomatis."
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium
                                       text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 dark:text-red-400 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Hapus
                        </button>
                    </form>
                    @endallowedTo
                </div>
            </div>

            {{-- Preview --}}
            @if($item->isImage())
            <div class="bg-slate-50 dark:bg-slate-900/50 p-4 flex justify-center" x-data="{ zoom: false }">
                <img src="{{ route('storage.file', $item->file_path) }}"
                     alt="File {{ $item->urutan }}"
                     @click="zoom = !zoom"
                     :class="zoom ? 'w-full cursor-zoom-out' : 'max-h-125 cursor-zoom-in'"
                     class="rounded-xl shadow object-contain transition-all duration-300">
            </div>
            @else
            <iframe src="{{ route('storage.file', $item->file_path) }}"
                    class="w-full border-0"
                    style="height: 70vh;"
                    title="File {{ $item->urutan }}">
            </iframe>
            @endif

        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection
