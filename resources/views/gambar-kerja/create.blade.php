@extends('layouts.app')

@php $isAddMode = request()->has('judul'); @endphp
@section('title', $isAddMode ? 'Tambah File Gambar Kerja' : 'Upload Gambar Kerja')
@section('page-title', $isAddMode ? 'Tambah File' : 'Upload Gambar Kerja')
@section('page-subtitle', $isAddMode ? 'Menambah file ke grup yang sudah ada' : 'Tambah dokumen gambar teknik')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="bg-gradient-to-r {{ $isAddMode ? 'from-green-600 to-emerald-600' : 'from-blue-600 to-indigo-600' }} px-6 py-5">
            <h2 class="text-lg font-bold text-white">{{ $isAddMode ? 'Tambah File ke Grup' : 'Upload Gambar Kerja' }}</h2>
            <p class="text-sm mt-1 {{ $isAddMode ? 'text-green-100' : 'text-blue-100' }}">
                {{ $isAddMode ? 'File baru akan ditambahkan ke grup: ' . request('judul') : 'Bisa pilih banyak file sekaligus — nomor urut otomatis' }}
            </p>
        </div>

        <form method="POST" action="{{ route('gambar-kerja.store') }}" enctype="multipart/form-data" class="p-6 space-y-5"
              onsubmit="if(this.querySelector('input[type=file]').files.length){var o=document.getElementById('gkUploadOverlay');if(o)o.style.display='flex';}">
            @csrf

            @if($isAddMode)
            {{-- Mode tambah file: field dikunci, tampil sebagai info --}}
            <div class="flex items-center gap-3 px-4 py-3 bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-200 dark:border-green-800">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm">
                    <p class="font-semibold text-green-800 dark:text-green-300">{{ request('judul') }}</p>
                    <p class="text-green-600 dark:text-green-400 font-mono text-xs">
                        {{ request('seri') }}{{ request('kva') ? '-' . request('kva') . 'KVA' : '' }}
                        {{ request('tahun') ? '· ' . request('tahun') : '' }}
                    </p>
                </div>
            </div>
            <input type="hidden" name="judul" value="{{ request('judul') }}">
            <input type="hidden" name="seri"  value="{{ request('seri') }}">
            <input type="hidden" name="kva"   value="{{ request('kva') }}">
            <input type="hidden" name="tahun" value="{{ request('tahun') }}">

            @else
            {{-- Mode baru: semua field bisa diisi --}}

            {{-- Judul --}}
            <div x-data="{
                    chars: {{ strlen(old('judul', '')) }}, showToast: false, _t: null,
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
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Judul <span class="text-red-500">*</span>
                    <span class="ml-1 text-xs font-normal" :class="chars > 46 ? 'text-red-500' : 'text-slate-400'"
                          x-text="'(' + chars + '/46)'"></span>
                </label>
                <input type="text" name="judul" value="{{ old('judul') }}"
                       @input="check($event.target.value)"
                       placeholder="Contoh: Gambar Teknik Trafo 500KVA"
                       class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                              text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500
                              @error('judul') border-red-500 @enderror"
                       :class="chars > 46 ? 'border-red-400! ring-red-400!' : ''">
                @error('judul')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror

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
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.446 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Judul mendekati batas maksimum (46 karakter)
                </div>
            </div>

            {{-- Seri + KVA + Tahun --}}
            <div x-data="{
                    seri: '{{ old('seri') }}',
                    kva:  '{{ old('kva') }}',
                    tahun: '{{ old('tahun') }}',
                    autoTahun(v) {
                        const m = v.match(/^(\d{2})/);
                        if (m) this.tahun = '20' + m[1];
                    }
                 }"
                 x-init="if (!tahun && seri) autoTahun(seri); $watch('seri', v => autoTahun(v))">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            Seri
                            <span class="ml-1 text-xs font-normal text-slate-400"><span class="text-red-500">*</span></span>
                        </label>
                        <input type="text" name="seri" x-model="seri" value="{{ old('seri') }}"
                               placeholder="Contoh: 26#######"
                               class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                                      text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500
                                      @error('seri') border-red-500 @enderror">
                        @error('seri')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            KVA
                            <span class="ml-1 text-xs font-normal text-slate-400"><span class="text-red-500">*</span></span>
                        </label>
                        <input type="text" name="kva" x-model="kva" value="{{ old('kva') }}"
                               placeholder="Contoh: 50, 100, 160"
                               class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                                      text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500
                                      @error('kva') border-red-500 @enderror">
                        @error('kva')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <input type="hidden" name="tahun" :value="tahun">
                </div>

                {{-- Preview seri(kva) realtime --}}
                <div x-show="seri || kva" x-cloak
                     class="mt-3 flex items-center gap-2 px-3 py-2 bg-slate-50 dark:bg-slate-700/50 rounded-xl">
                    <span class="text-xs text-slate-500">Akan tampil sebagai:</span>
                    <span class="text-sm font-mono font-semibold text-slate-700 dark:text-slate-200"
                          x-text="seri + (kva ? '(' + kva + ')' : '')"></span>
                </div>
            </div>

            {{-- Kategori Seri --}}
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Kategori Seri <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-3 gap-2">
                    @foreach([
                        'pln'      => ['label' => 'Standar / PLN', 'dot' => 'bg-slate-400',  'ring' => 'ring-slate-400',  'bg' => 'bg-slate-50 dark:bg-slate-700/50',   'text' => 'text-slate-600 dark:text-slate-300'],
                        'swasta'   => ['label' => 'Seri Swasta',   'dot' => 'bg-amber-500',  'ring' => 'ring-amber-500',  'bg' => 'bg-amber-50 dark:bg-amber-900/20',   'text' => 'text-amber-700 dark:text-amber-300'],
                        'typetest' => ['label' => 'Seri Typetest', 'dot' => 'bg-purple-500', 'ring' => 'ring-purple-500', 'bg' => 'bg-purple-50 dark:bg-purple-900/20', 'text' => 'text-purple-700 dark:text-purple-300'],
                    ] as $val => $opt)
                    <label class="relative cursor-pointer">
                        <input type="radio" name="kategori_seri" value="{{ $val }}"
                               {{ old('kategori_seri', 'pln') === $val ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="flex flex-col items-center gap-1.5 px-3 py-3 rounded-xl border-2 border-slate-200 dark:border-slate-600
                                    peer-checked:border-current peer-checked:{{ $opt['bg'] }} peer-checked:ring-2 peer-checked:{{ $opt['ring'] }}
                                    hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-all {{ $opt['text'] }}">
                            <span class="w-3 h-3 rounded-full {{ $opt['dot'] }}"></span>
                            <span class="text-xs font-semibold text-center leading-tight">{{ $opt['label'] }}</span>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            @endif {{-- end isAddMode else --}}

            {{-- Info nomor urut --}}
            <div class="flex items-start gap-2.5 px-3 py-2.5 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
                <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-xs text-blue-700 dark:text-blue-300">
                    Beberapa file dengan judul & seri yang sama akan digabung otomatis dan diberi nomor urut lanjutan.
                </p>
            </div>

            {{-- Upload File --}}
            <div x-data="fileUploader()">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    File Gambar / PDF <span class="text-red-500">*</span>
                </label>

                <div class="relative border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl
                            hover:border-blue-400 transition-colors"
                     :class="files.length ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/10' : ''">
                    <input type="file" name="files[]" accept=".jpg,.jpeg,.png,.pdf" multiple
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                           @change="handleFiles($event)">
                    <div class="flex flex-col items-center justify-center py-10 px-4 text-center pointer-events-none">
                        <svg class="w-10 h-10 text-slate-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <template x-if="!files.length">
                            <div>
                                <p class="text-sm text-slate-500">Klik atau <span class="text-blue-600 font-medium">seret file</span> ke sini</p>
                                <p class="text-xs text-slate-400 mt-1">Pilih banyak file sekaligus — JPG, PNG, PDF, maks. 100MB/file</p>
                            </div>
                        </template>
                        <template x-if="files.length">
                            <p class="text-sm font-semibold text-blue-600" x-text="files.length + ' file dipilih — klik untuk ganti'"></p>
                        </template>
                    </div>
                </div>

                <template x-if="files.length">
                    <ul class="mt-3 space-y-2">
                        <template x-for="(f, i) in files" :key="i">
                            <li class="flex items-center gap-3 px-3 py-2 bg-slate-50 dark:bg-slate-700/50 rounded-xl text-sm">
                                <span class="shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold"
                                      :class="f.isPdf ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'"
                                      x-text="f.isPdf ? 'PDF' : 'IMG'"></span>
                                <span class="flex-1 truncate text-slate-700 dark:text-slate-300" x-text="f.name"></span>
                                <span class="shrink-0 text-xs text-slate-400" x-text="f.size"></span>
                            </li>
                        </template>
                    </ul>
                </template>

                @error('files')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                @error('files.*')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Keterangan --}}
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
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Keterangan
                    <span class="ml-1 text-xs font-normal" :class="words > 18 ? 'text-red-500' : 'text-slate-400'"
                          x-text="'(' + words + '/18 kata)'"></span>
                </label>
                <textarea name="keterangan" rows="2"
                          @input="check($event.target.value)"
                          placeholder="Contoh: Revisi ke-2, Tampak depan dan samping..."
                          class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                                 text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                          :class="words > 18 ? 'border-red-400! ring-red-400!' : ''">{{ old('keterangan') }}</textarea>

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
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.446 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Kalimat Anda melebihi batas maksimum (18 kata)
                </div>
            </div>

            {{-- Upload Loading Overlay --}}
            <div id="gkUploadOverlay" style="display:none"
                 class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center">
                <div class="bg-white dark:bg-slate-800 rounded-2xl px-10 py-8 flex flex-col items-center gap-4 shadow-2xl mx-4">
                    <div class="w-14 h-14 rounded-full border-[5px] border-blue-100 dark:border-slate-600 border-t-blue-500 animate-spin"></div>
                    <div class="text-center">
                        <p class="text-sm font-semibold text-slate-800 dark:text-white">Sedang mengupload...</p>
                        <p class="text-xs text-slate-400 mt-1">Mohon tunggu, jangan tutup halaman</p>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ $isAddMode
                        ? route('gambar-kerja.by-group', ['judul' => request('judul'), 'seri' => request('seri'), 'kva' => request('kva'), 'tahun' => request('tahun')])
                        : route('gambar-kerja.index') }}"
                   class="flex-1 py-3 text-center text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700
                          hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl font-semibold transition-colors">
                    Batal
                </a>
                <button type="submit"
                        class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition-colors flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Upload
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function fileUploader() {
    return {
        files: [],
        handleFiles(event) {
            this.files = Array.from(event.target.files).map(f => ({
                name:  f.name,
                isPdf: f.name.toLowerCase().endsWith('.pdf'),
                size:  f.size > 1046576
                    ? (f.size / 1046576).toFixed(1) + ' MB'
                    : Math.round(f.size / 1024) + ' KB',
            }));
        }
    };
}
</script>
@endpush
