@extends('layouts.app')

@section('title', 'Edit Departemen')
@section('page-title', 'Edit Departemen')
@section('page-subtitle', 'Perbarui informasi departemen')

@section('content')
<div class="max-w-md mx-auto">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

        <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-5">
            <h2 class="text-lg font-bold text-white">Edit Departemen</h2>
            <p class="text-amber-100 text-sm mt-1">{{ $department->name }}</p>
        </div>

        <form method="POST" action="{{ route('departments.update', $department) }}" class="p-6 space-y-5">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Nama Departemen <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $department->name) }}"
                       class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                              border {{ $errors->has('name') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                              focus:outline-none focus:ring-2 focus:ring-amber-500">
                @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 p-4 bg-slate-50 dark:bg-slate-700/50 rounded-xl">
                <input type="checkbox" id="is_active" name="is_active" value="1"
                       {{ old('is_active', $department->is_active) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-slate-300 text-amber-500 focus:ring-amber-500">
                <div>
                    <label for="is_active" class="text-sm font-medium text-slate-700 dark:text-slate-300">Departemen Aktif</label>
                    <p class="text-xs text-slate-400">Departemen nonaktif tidak muncul di form operator</p>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('departments.index') }}"
                   class="flex-1 py-3 text-center text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700
                          hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl font-semibold transition-colors">
                    Batal
                </a>
                <button type="submit"
                        class="flex-1 py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-semibold transition-colors">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
