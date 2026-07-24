@extends('layouts.app')
@section('title', 'Tambah Kategori')
@section('page-title', 'Tambah Kategori')
@section('content')
<div class="max-w-lg mx-auto">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-5">
            <h2 class="text-lg font-bold text-white">Tambah Kategori Baru</h2>
        </div>
        <form method="POST" action="{{ route('categories.store') }}" class="p-6 space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nama Kategori <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Tangki, Cover, Body"
                       class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                              text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500
                              @error('name') border-red-500 @enderror">
                @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Kode Kategori <span class="text-slate-400 font-normal">(opsional)</span></label>
                <input type="text" name="code" value="{{ old('code') }}" placeholder="Contoh: TNK, CVR"
                       class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                              text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono uppercase
                              @error('code') border-red-500 @enderror">
                @error('code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Keterangan</label>
                <textarea name="description" rows="3"
                          class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                                 text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none">{{ old('description') }}</textarea>
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" id="is_active" name="is_active" value="1" checked
                       class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <label for="is_active" class="text-sm font-medium text-slate-700 dark:text-slate-300">Kategori Aktif</label>
            </div>
            <div class="flex gap-3 pt-2">
                <a href="{{ route('categories.index') }}"
                   class="flex-1 py-3 text-center text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700
                          hover:bg-slate-200 rounded-xl font-semibold transition-colors">Batal</a>
                <button type="submit"
                        class="flex-1 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold transition-colors">
                    Simpan Kategori
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
