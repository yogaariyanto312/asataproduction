@extends('layouts.app')

@section('title', 'Tambah Operator')
@section('page-title', 'Tambah Operator')
@section('page-subtitle', 'Daftarkan akun operator baru')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-5">
            <h2 class="text-lg font-bold text-white">Form Tambah Operator</h2>
            <p class="text-green-100 text-sm mt-1">Operator dapat login dan menginput data produksi</p>
        </div>

        <form method="POST" action="{{ route('operators.store') }}" class="p-6 space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Nama Lengkap <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}"
                       placeholder="Nama lengkap operator"
                       class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                              border {{ $errors->has('name') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                              focus:outline-none focus:ring-2 focus:ring-green-500">
                @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Username <span class="text-red-500">*</span>
                </label>
                <input type="text" name="username" value="{{ old('username') }}"
                       placeholder="Username untuk login"
                       class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white font-mono
                              border {{ $errors->has('username') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                              focus:outline-none focus:ring-2 focus:ring-green-500">
                @error('username')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Email <span class="text-slate-400 font-normal">(opsional, untuk reset password)</span>
                </label>
                <input type="email" name="email" value="{{ old('email') }}"
                       placeholder="contoh@email.com"
                       class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                              border {{ $errors->has('email') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                              focus:outline-none focus:ring-2 focus:ring-green-500">
                @error('email')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Departemen <span class="text-slate-400 font-normal">(opsional)</span>
                </label>
                <select name="department"
                        class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                               border {{ $errors->has('department') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                               focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">-- Pilih Departemen --</option>
                    @foreach($departments as $dept)
                    <option value="{{ $dept->name }}" {{ old('department') == $dept->name ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
                @error('department')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password"
                           placeholder="Min. 6 karakter"
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border {{ $errors->has('password') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                  focus:outline-none focus:ring-2 focus:ring-green-500">
                    @error('password')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Konfirmasi Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password" name="password_confirmation"
                           placeholder="Ulangi password"
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600
                                  focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('operators.index') }}"
                   class="flex-1 py-3 text-center text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700
                          hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl font-semibold transition-colors">
                    Batal
                </a>
                <button type="submit"
                        class="flex-1 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold transition-colors">
                    Simpan Operator
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
