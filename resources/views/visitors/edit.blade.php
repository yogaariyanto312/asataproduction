@extends('layouts.app')
@section('title', 'Edit Visitor')
@section('page-title', 'Edit Visitor')
@section('page-subtitle', 'Perbarui data akun visitor')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

        <div class="px-6 py-5" style="background: linear-gradient(135deg, #0f766e, #0d9488);">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-white">Edit Visitor</h2>
                    <p class="text-teal-100 text-sm mt-0.5">{{ $visitor->name }}</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('visitors.update', $visitor) }}" class="p-6 space-y-5">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Nama Lengkap <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $visitor->name) }}"
                       class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                              border {{ $errors->has('name') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                              focus:outline-none focus:ring-2 focus:ring-teal-500">
                @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Username <span class="text-red-500">*</span>
                </label>
                <input type="text" name="username" value="{{ old('username', $visitor->username) }}"
                       class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                              border {{ $errors->has('username') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                              focus:outline-none focus:ring-2 focus:ring-teal-500">
                @error('username')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div class="border border-slate-200 dark:border-slate-600 rounded-xl p-4 space-y-4">
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-400">
                    Reset Password
                    <span class="ml-1 text-xs font-normal text-slate-400">(kosongkan jika tidak ingin mengubah)</span>
                </p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5">Password Baru</label>
                        <input type="password" name="password" value="" autocomplete="new-password" placeholder="Min. 8 karakter"
                               class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                      border {{ $errors->has('password') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                      focus:outline-none focus:ring-2 focus:ring-teal-500">
                        @error('password')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" value="" autocomplete="new-password" placeholder="Ulangi password baru"
                               class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                      border border-slate-300 dark:border-slate-600
                                      focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('management.index', ['tab' => 'visitor']) }}"
                   class="flex-1 py-3 text-center text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700
                          hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl font-semibold transition-colors">
                    Batal
                </a>
                <button type="submit"
                        class="flex-1 py-3 text-white rounded-xl font-semibold transition-colors"
                        style="background: linear-gradient(135deg, #0f766e, #0d9488);">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
