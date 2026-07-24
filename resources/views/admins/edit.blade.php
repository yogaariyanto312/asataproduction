@extends('layouts.app')

@section('title', 'Edit Admin')
@section('page-title', 'Edit Admin')
@section('page-subtitle', 'Perbarui informasi akun admin')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

        <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-5">
            <h2 class="text-lg font-bold text-white">Edit Admin</h2>
            <p class="text-amber-100 text-sm mt-1">{{ $admin->name }}</p>
        </div>

        <form method="POST" action="{{ route('admins.update', $admin) }}" class="p-6 space-y-5">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Nama Lengkap <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $admin->name) }}"
                       class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                              border {{ $errors->has('name') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                              focus:outline-none focus:ring-2 focus:ring-amber-500">
                @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="email" value="{{ old('email', $admin->email) }}"
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border {{ $errors->has('email') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                  focus:outline-none focus:ring-2 focus:ring-amber-500">
                    @error('email')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Username
                        <span class="ml-1 text-xs font-normal text-slate-400">(opsional)</span>
                    </label>
                    <input type="text" name="username" value="{{ old('username', $admin->username) }}"
                           placeholder="Contoh: admin123"
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border {{ $errors->has('username') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                  focus:outline-none focus:ring-2 focus:ring-amber-500">
                    @error('username')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Reset Password --}}
            <div class="border border-slate-200 dark:border-slate-600 rounded-xl p-4 space-y-4">
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-400">
                    Reset Password
                    <span class="ml-1 text-xs font-normal text-slate-400">(kosongkan jika tidak ingin mengubah)</span>
                </p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5">Password Baru</label>
                        <input type="password" name="password" value="" autocomplete="new-password"
                               placeholder="Min. 8 karakter"
                               class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                      border {{ $errors->has('password') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                      focus:outline-none focus:ring-2 focus:ring-amber-500">
                        @error('password')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" value="" autocomplete="new-password"
                               placeholder="Ulangi password baru"
                               class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                      border border-slate-300 dark:border-slate-600
                                      focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('admins.index') }}"
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
