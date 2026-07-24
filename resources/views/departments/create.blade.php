@extends('layouts.app')

@section('title', 'Tambah Departemen')
@section('page-title', 'Tambah Departemen')
@section('page-subtitle', 'Daftarkan departemen baru')

@section('content')
<div class="max-w-md mx-auto">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

        <div class="bg-gradient-to-r from-teal-500 to-emerald-600 px-6 py-5">
            <h2 class="text-lg font-bold text-white">Tambah Departemen</h2>
            <p class="text-teal-100 text-sm mt-1">Departemen digunakan untuk mengelompokkan operator</p>
        </div>

        <form method="POST" action="{{ route('departments.store') }}" class="p-6 space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Nama Departemen <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}"
                       placeholder="Contoh: QC Welding"
                       autofocus
                       class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                              border {{ $errors->has('name') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                              focus:outline-none focus:ring-2 focus:ring-teal-500">
                @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('departments.index') }}"
                   class="flex-1 py-3 text-center text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700
                          hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl font-semibold transition-colors">
                    Batal
                </a>
                <button type="submit"
                        class="flex-1 py-3 bg-teal-600 hover:bg-teal-700 text-white rounded-xl font-semibold transition-colors">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
