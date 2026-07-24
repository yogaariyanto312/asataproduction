@extends('layouts.app')

@section('title', 'Profil Saya')
@section('page-title', 'Profil Saya')
@section('page-subtitle', 'Kelola informasi akun Anda')

@section('content')

@php
$baseUrl = 'https://api.dicebear.com/9.x/adventurer/svg?backgroundColor=b6e3f4,c0aede,d1d4f9,ffd5dc,ffdfbf&seed=';

$seeds = [
    'Felix','Aneka','Liam','Sophie','Max','Lily','Jack','Emma','Oliver','Zoe',
    'Henry','Grace','Ethan','Mia','Noah','Ava','Leo','Sara','Adam','Luna',
    'Kai','Nara','Rio','Hana','Budi','Indah','Raka','Sari','Dani','Putri',
    'Arya','Wulan','Bagas','Tari','Rian','Dewi','Fajar','Laras','Dimas','Ayu',
];

$currentAvatar = str_starts_with($user->avatar ?? '', 'http') ? $user->avatar : '';

// Cari seed dari URL (cocok dengan format lama maupun baru)
$currentSeed = '';
foreach ($seeds as $s) {
    if ($currentAvatar && str_contains($currentAvatar, 'seed=' . $s)) {
        $currentSeed = $s;
        break;
    }
}
@endphp

<div class="max-w-2xl mx-auto space-y-5">

    {{-- ══════════════════════════ CARD 1: Avatar ══════════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6"
         x-data="{
             selected: '{{ $currentSeed }}',
             baseUrl:  '{{ $baseUrl }}',
             saving:   false,
             saved:    false,
             get avatarUrl() { return this.selected ? this.baseUrl + this.selected : ''; },
             async save() {
                 this.saving = true;
                 this.saved  = false;
                 const res = await fetch('{{ route('profile.avatar') }}', {
                     method: 'POST',
                     headers: {
                         'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                         'Content-Type': 'application/json',
                         'Accept': 'application/json',
                     },
                     body: JSON.stringify({ avatar: this.avatarUrl }),
                 });
                 this.saving = false;
                 if (res.ok) {
                     this.saved = true;
                     setTimeout(() => this.saved = false, 2500);
                     // Update avatar di header sidebar/navbar
                     const img = document.getElementById('header-avatar');
                     const ini = document.getElementById('header-initials');
                     if (img && ini) {
                         if (this.avatarUrl) {
                             img.src = this.avatarUrl;
                             img.style.display = '';
                             ini.style.display = 'none';
                         } else {
                             img.src = '';
                             img.style.display = 'none';
                             ini.style.display = 'flex';
                         }
                     }
                 }
             }
         }">

        <h3 class="text-base font-bold text-slate-800 dark:text-white">Avatar Profil</h3>
        <p class="text-sm text-slate-400 mt-0.3" style="margin-bottom:20px;">
            Pilih avatar dari pilihan di bawah, atau biarkan kosong untuk menggunakan inisial nama Anda.
        </p>

        {{-- Preview --}}
        <div class="flex items-center gap-4 rounded-2xl"
             style="padding:16px 20px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1);">

            {{-- Avatar preview --}}
            <div class="w-16 h-16 rounded-full overflow-hidden shrink-0 bg-blue-600 flex items-center justify-center"
                 style="box-shadow:0 0 0 3px rgba(59,130,246,0.35);">
                <template x-if="selected">
                    <img :src="avatarUrl" alt="Preview" class="w-full h-full object-cover">
                </template>
                <template x-if="!selected">
                    <span class="text-2xl font-bold text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                </template>
            </div>

            {{-- Teks info --}}
            <div class="flex-1 min-w-0">
                <p style="font-size:0.875rem; font-weight:600; color:rgba(255,255,255,0.9); margin:0;"
                   x-text="selected ? 'Avatar dipilih: ' + selected : 'Belum ada avatar'"></p>
                <template x-if="selected">
                    <button type="button" @click="selected = ''"
                            style="margin-top:4px; font-size:0.75rem; color:rgba(255,255,255,0.4);
                                   background:none; border:none; cursor:pointer; padding:0;
                                   text-decoration:underline;"
                            onmouseover="this.style.color='#f87171'"
                            onmouseout="this.style.color='rgba(255,255,255,0.4)'">
                        Hapus avatar
                    </button>
                </template>
                <template x-if="!selected">
                    <p style="margin-top:4px; font-size:0.75rem; color:rgba(255,255,255,0.4); margin-bottom:0;">
                        Pilih avatar dari pilihan di bawah
                    </p>
                </template>
            </div>

            {{-- Tombol simpan --}}
            <div class="shrink-0 flex flex-col items-end" style="gap:6px;">
                <button type="button" @click="save()" :disabled="saving"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50
                               text-white text-sm font-semibold rounded-xl transition-colors">
                    <span x-show="!saving">Simpan Avatar</span>
                    <span x-show="saving" x-cloak>Menyimpan…</span>
                </button>
                <span x-show="saved" x-cloak x-transition
                      style="font-size:0.75rem; color:#34d399; font-weight:500;">✓ Tersimpan!</span>
            </div>
        </div>

        {{-- Label grid --}}
        <p style="font-size:0.7rem; font-weight:600; color:rgba(255,255,255,0.35);
                  letter-spacing:0.1em; text-transform:uppercase; margin:24px 0 10px;">
            Pilih Avatar
        </p>

        {{-- Grid avatar --}}
        <style>
            .av-grid {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 10px;
            }
            @media (min-width: 640px) {
                .av-grid { grid-template-columns: repeat(10, 1fr); }
            }
            .av-btn {
                position: relative;
                width: 100%;
                aspect-ratio: 1 / 1;
                border-radius: 9999px;
                overflow: hidden;
                transition: all 150ms ease;
                opacity: .75;
                outline: none;
                cursor: pointer;
                background: transparent;
                border: none;
                padding: 0;
            }
            .av-btn:hover { opacity: 1; transform: scale(1.06); }
            .av-btn.av-selected {
                opacity: 1;
                transform: scale(1.1);
                box-shadow: 0 0 0 3px #3b82f6;
            }
            .av-btn img {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
        </style>
        <div class="av-grid">
            @foreach($seeds as $seed)
            <button type="button"
                    @click="selected = '{{ $seed }}'"
                    :class="selected === '{{ $seed }}' ? 'av-btn av-selected' : 'av-btn'"
                    class="av-btn">
                <img src="{{ $baseUrl }}{{ $seed }}"
                     alt="{{ $seed }}"
                     loading="lazy">
            </button>
            @endforeach
        </div>

    </div>
    {{-- END CARD 1 --}}


    {{-- ══════════════════════════ CARD 2: Akun & Password ══════════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

        {{-- Header banner --}}
        <div class="bg-linear-to-r from-slate-700 to-slate-800 px-6 py-5">
            @php
                $avatarUrl = $user->avatar
                    ? (str_starts_with($user->avatar, 'http')
                        ? $user->avatar
                        : route('storage.file', ['path' => $user->avatar]))
                    : null;
            @endphp
            <div class="flex items-center gap-4">
                <div class="relative shrink-0 w-14 h-14">
                    <div id="header-initials"
                         class="w-14 h-14 rounded-full bg-blue-600 flex items-center justify-center absolute inset-0"
                         style="{{ $avatarUrl ? 'display:none' : '' }}">
                        <span class="text-xl font-black text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    </div>
                    <img id="header-avatar"
                         src="{{ $avatarUrl ?? '' }}" alt="Foto Profil"
                         class="w-14 h-14 rounded-full object-cover border-2 border-white/30"
                         style="{{ $avatarUrl ? '' : 'display:none' }}"
                         onerror="this.style.display='none';document.getElementById('header-initials').style.display='flex'">
                </div>
                <div>
                    <p class="text-base font-bold text-white">{{ $user->name }}</p>
                    <p class="text-slate-400 text-sm capitalize mt-0.5">{{ $user->role }}
                        @if($user->department) · {{ $user->department }} @endif
                    </p>
                    <p class="text-slate-500 text-xs mt-1">Bergabung {{ $user->created_at->format('d M Y') }}</p>
                </div>
            </div>
        </div>

        {{-- Form info & password --}}
        <form method="POST" action="{{ route('profile.update') }}" class="p-6 space-y-5">
            @csrf @method('PUT')

            <div>
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-4 uppercase tracking-wide">
                    Informasi Akun
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            Nama Lengkap <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                               class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                      border {{ $errors->has('name') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                      focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}"
                               placeholder="email@perusahaan.com" required
                               class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                      border {{ $errors->has('email') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                      focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @error('email')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <hr class="border-slate-100 dark:border-slate-700">

            <div>
                <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-1 uppercase tracking-wide">
                    Ganti Password
                </h3>
                <p class="text-xs text-slate-400 mb-4">Kosongkan jika tidak ingin mengubah password</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            Password Saat Ini
                        </label>
                        <div class="relative">
                            <input type="password" id="current_password" name="current_password"
                                   placeholder="Masukkan password saat ini"
                                   class="w-full px-4 py-3 pr-11 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                          border {{ $errors->has('current_password') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                          focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="button" onclick="togglePwd('current_password',this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                        @error('current_password')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                Password Baru
                            </label>
                            <div class="relative">
                                <input type="password" id="new_password" name="password"
                                       placeholder="Min. 6 karakter"
                                       class="w-full px-4 py-3 pr-11 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                              border {{ $errors->has('password') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                              focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <button type="button" onclick="togglePwd('new_password',this)"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </div>
                            @error('password')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                Konfirmasi Password
                            </label>
                            <input type="password" name="password_confirmation"
                                   placeholder="Ulangi password baru"
                                   class="w-full px-4 py-3 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                          border border-slate-300 dark:border-slate-600
                                          focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('dashboard') }}"
                   class="flex-1 py-3 text-center text-slate-700 dark:text-slate-300
                          bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600
                          rounded-xl font-semibold transition-colors">
                    Batal
                </a>
                <button type="submit"
                        class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold transition-colors">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
    {{-- END CARD 2 --}}


    {{-- ══════════════════════════ CARD 3: Keamanan Sesi ══════════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-1 uppercase tracking-wide">
            Keamanan Sesi
        </h3>
        <p class="text-xs text-slate-400 mb-4">
            Lupa logout di perangkat lain (HP, komputer kantor, warnet)? Keluarkan semua sesi lain
            sekaligus. Perangkat ini tetap login.
        </p>

        @if($errors->has('logout_others'))
        <p class="mb-3 text-xs text-red-500">{{ $errors->first('logout_others') }}</p>
        @endif

        <form method="POST" action="{{ route('profile.logout-others') }}"
              class="flex flex-col sm:flex-row sm:items-end gap-3">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Konfirmasi Password Saat Ini
                </label>
                <div class="relative">
                    <input type="password" id="logout_others_password" name="current_password"
                           placeholder="Masukkan password untuk konfirmasi" required
                           class="w-full px-4 py-3 pr-11 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border {{ $errors->has('logout_others') ? 'border-red-500' : 'border-slate-300 dark:border-slate-600' }}
                                  focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="button" onclick="togglePwd('logout_others_password',this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit"
                    data-confirm="Semua sesi di perangkat lain akan dikeluarkan. Perangkat ini tetap login. Lanjutkan?"
                    data-confirm-title="Logout Semua Perangkat Lain"
                    class="shrink-0 inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-semibold
                           text-white bg-red-600 hover:bg-red-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Logout Semua Perangkat
            </button>
        </form>
    </div>
    {{-- END CARD 3 --}}

</div>

<script>
function togglePwd(id, btn) {
    const input = document.getElementById(id);
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    const paths = btn.querySelectorAll('path');
    if (show) {
        paths[0].setAttribute('d', 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21');
        paths[1].setAttribute('d', '');
    } else {
        paths[0].setAttribute('d', 'M15 12a3 3 0 11-6 0 3 3 0 016 0z');
        paths[1].setAttribute('d', 'M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z');
    }
}
</script>
@endsection
