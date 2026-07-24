@extends('layouts.app')

@section('title', 'Tambah Produk')
@section('page-title', 'Tambah Produk')
@section('page-subtitle', 'Daftarkan produk baru ke sistem')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-5">
            <h2 class="text-lg font-bold text-white">Tambah Produk Baru</h2>
            <p class="text-green-100 text-sm mt-1">Isi informasi produk dengan lengkap</p>
        </div>

        <form method="POST" action="{{ route('products.store') }}" class="p-6 space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Kategori <span class="text-red-500">*</span>
                </label>
                <select name="category_id"
                        class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                               text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500
                               @error('category_id') border-red-500 @enderror">
                    <option value="">-- Pilih Kategori --</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ old('category_id', request('category_id')) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Tipe Produk <span class="text-red-500">*</span>
                </label>
                <select name="type"
                        class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                               text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="regular" {{ old('type', request('type', 'regular')) == 'regular' ? 'selected' : '' }}>Regular</option>
                    <option value="channel" {{ old('type', request('type')) == 'channel' ? 'selected' : '' }}>Channel (UP + BT)</option>
                </select>
                @error('type')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Nama Produk <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', request('name')) }}"
                       placeholder="Contoh: Tanki, Cover, Body"
                       class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                              text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500
                              @error('name') border-red-500 @enderror">
                @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Seri Produk <span class="text-red-500">*</span>
                </label>
                <input type="text" id="input-series" name="series" value="{{ old('series') }}"
                       placeholder="Contoh: 26B0091000"
                       class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                              text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono
                              @error('series') border-red-500 @enderror">
                @error('series')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        KVA <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="kva" value="{{ old('kva') }}"
                           placeholder="Contoh: 50, 100, 160, 250"
                           class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                                  text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500
                                  @error('kva') border-red-500 @enderror">
                    @error('kva')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Tahun
                    </label>
                    <input type="number" id="select-tahun" name="tahun" value="{{ old('tahun') }}"
                           placeholder="Contoh: 2026" min="2020" max="2099"
                           class="w-full px-4 py-3 border bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500
                                  {{ $errors->has('tahun') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}">
                    @error('tahun')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Satuan <span class="text-red-500">*</span>
                </label>
                <select name="unit"
                        class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                               text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @foreach(['unit', 'pcs', 'set', 'buah', 'lembar'] as $u)
                    <option value="{{ $u }}" {{ old('unit', 'unit') == $u ? 'selected' : '' }}>{{ $u }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Keterangan</label>
                <textarea name="description" rows="3"
                          placeholder="Deskripsi tambahan produk..."
                          class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                                 text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none">{{ old('description') }}</textarea>
            </div>

            <div class="flex items-center gap-3">
                <input type="checkbox" id="is_active" name="is_active" value="1"
                       {{ old('is_active', '1') ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <label for="is_active" class="text-sm font-medium text-slate-700 dark:text-slate-300">Produk Aktif</label>
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('products.index') }}"
                   class="flex-1 py-3 text-center text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700
                          hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl font-semibold transition-colors">
                    Batal
                </a>
                <button type="submit"
                        class="flex-1 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold transition-colors">
                    Simpan Produk
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const seriesInput = document.getElementById('input-series');
    const tahunInput  = document.getElementById('select-tahun');
    if (!seriesInput || !tahunInput) return;

    seriesInput.addEventListener('input', function () {
        if (tahunInput.dataset.manualSet) return;
        const m = this.value.match(/^(\d{2})/);
        if (!m) return;
        tahunInput.value = 2000 + parseInt(m[1]);
    });

    tahunInput.addEventListener('input', function () {
        this.dataset.manualSet = '1';
    });
}());
</script>
@endpush
