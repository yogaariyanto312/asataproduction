@extends('layouts.app')

@section('title', 'Control Panel')
@section('page-title', 'Control Panel Aplikasi')
@section('page-subtitle', 'Pengaturan sistem, notifikasi bot, dan keamanan aplikasi')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    {{-- Developer badge info --}}
    <div class="flex items-center gap-2 px-4 py-2.5 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800">
        <span class="px-2 py-0.5 text-[10px] font-bold bg-blue-600 text-white rounded-full uppercase tracking-wide">Developer</span>
        <p class="text-sm text-blue-700 dark:text-blue-300">
            Setiap aktivitas user (login, logout, input produksi, dll.) akan dikirim otomatis ke bot yang aktif.
        </p>
    </div>

    {{-- ═══════════════════════════ FORM UTAMA ═══════════════════════════ --}}
    <form method="POST" action="{{ route('developer.bot-settings.update') }}">
        @csrf

    {{-- ══════════════════════ TELEGRAM ══════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden"
         x-data="{ show_token: false }">

        {{-- Header --}}
        <div class="bg-gradient-to-r from-sky-500 to-blue-600 px-6 py-5">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    {{-- Telegram icon --}}
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.17 13.737 4.25 12.82c-.652-.204-.666-.652.136-.965l11.26-4.339c.543-.194 1.018.133.848.705z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white">Telegram Bot</h2>
                        <p class="text-sky-100 text-xs">Kirim aktivitas ke grup / channel Telegram</p>
                    </div>
                </div>

                {{-- Toggle enabled --}}
                <div x-data="{ enabled: {{ $setting->telegram_enabled ? 'true' : 'false' }} }">
                    <button type="button" @click="enabled = !enabled"
                            :class="enabled ? 'bg-white/90' : 'bg-white/30'"
                            class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors shrink-0">
                        <span :class="enabled ? 'translate-x-6 bg-sky-600' : 'translate-x-1 bg-white/70'"
                              class="inline-block h-5 w-5 transform rounded-full transition-transform shadow"></span>
                    </button>
                    <input type="hidden" name="telegram_enabled" :value="enabled ? '1' : '0'">
                    <p class="text-[10px] text-sky-100 mt-1 text-right" x-text="enabled ? 'Aktif' : 'Nonaktif'"></p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-4">

            {{-- Bot Token --}}
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Bot Token
                    <span class="ml-1 text-xs font-normal text-slate-400">(dari @BotFather)</span>
                </label>
                <div class="relative">
                    <input :type="show_token ? 'text' : 'password'"
                           name="telegram_token"
                           value="{{ old('telegram_token', $setting->telegram_token) }}"
                           placeholder="1234567890:AAFxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                           class="w-full px-4 py-3 pr-12 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                                  text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500
                                  font-mono text-sm @error('telegram_token') border-red-500 @enderror">
                    <button type="button" @click="show_token = !show_token"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <svg x-show="!show_token" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="show_token" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
                @error('telegram_token')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Chat ID — Log Server --}}
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Chat ID — Log Server
                    <span class="ml-1 text-xs font-normal text-slate-400">(aktivitas: login, input, edit, hapus)</span>
                </label>
                <input type="text" name="telegram_chat_id"
                       value="{{ old('telegram_chat_id', $setting->telegram_chat_id) }}"
                       placeholder="Contoh: -1001234567890 atau 987654321"
                       class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                              text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500
                              font-mono text-sm @error('telegram_chat_id') border-red-500 @enderror">
                @error('telegram_chat_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Chat ID — Laporan Produksi (grup khusus, terpisah dari log server) --}}
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Chat ID — Laporan Produksi
                    <span class="ml-1 text-xs font-normal text-slate-400">(laporan harian & alert reject)</span>
                </label>
                <input type="text" name="telegram_report_chat_id"
                       value="{{ old('telegram_report_chat_id', $setting->telegram_report_chat_id) }}"
                       placeholder="Kosongkan untuk pakai Chat ID Log Server"
                       class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                              text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500
                              font-mono text-sm @error('telegram_report_chat_id') border-red-500 @enderror">
                <p class="mt-1 text-xs text-slate-400">Grup khusus untuk laporan produksi harian & alert reject. Jika kosong, dikirim ke Chat ID Log Server.</p>
                @error('telegram_report_chat_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Cara mendapat Chat ID (collapsible) --}}
            <div x-data="{ open: false }">
                <button type="button" @click="open = !open"
                        class="flex items-center gap-1.5 text-xs text-sky-600 dark:text-sky-400 hover:text-sky-700 font-medium">
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    Cara mendapatkan Bot Token & Chat ID
                </button>
                <div x-show="open" x-collapse class="mt-3 p-4 bg-sky-50 dark:bg-sky-900/20 rounded-xl text-xs text-slate-600 dark:text-slate-400 space-y-2 leading-relaxed">
                    <p class="font-semibold text-slate-700 dark:text-slate-300">📌 Bot Token:</p>
                    <ol class="list-decimal list-inside space-y-1 pl-1">
                        <li>Buka Telegram, cari <span class="font-mono bg-white dark:bg-slate-800 px-1 rounded">@BotFather</span></li>
                        <li>Kirim <span class="font-mono bg-white dark:bg-slate-800 px-1 rounded">/newbot</span> → ikuti instruksi</li>
                        <li>Copy token yang diberikan (format: <span class="font-mono">123456:ABC-xxx</span>)</li>
                    </ol>
                    <p class="font-semibold text-slate-700 dark:text-slate-300 pt-1">📌 Chat ID:</p>
                    <ol class="list-decimal list-inside space-y-1 pl-1">
                        <li>Tambahkan bot ke grup/channel Anda</li>
                        <li>Kirim pesan apa saja di grup tersebut</li>
                        <li>Buka URL: <span class="font-mono break-all bg-white dark:bg-slate-800 px-1 rounded">https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</span></li>
                        <li>Cari <span class="font-mono bg-white dark:bg-slate-800 px-1 rounded">"chat":{"id":-100xxxx}</span> — nilai itulah Chat ID</li>
                    </ol>
                    <p class="text-amber-600 dark:text-amber-400">⚠️ Chat ID grup/channel biasanya diawali <span class="font-mono">-100</span></p>
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="flex items-center gap-2 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════ DISCORD ══════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden mt-6">

        {{-- Header --}}
        <div class="bg-gradient-to-r from-indigo-500 to-violet-600 px-6 py-5">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    {{-- Discord icon --}}
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057.1 18.079.112 18.1.133 18.11a19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white">Discord Webhook</h2>
                        <p class="text-indigo-100 text-xs">Kirim aktivitas ke channel Discord via webhook</p>
                    </div>
                </div>

                {{-- Toggle enabled --}}
                <div x-data="{ enabled: {{ $setting->discord_enabled ? 'true' : 'false' }} }">
                    <button type="button" @click="enabled = !enabled"
                            :class="enabled ? 'bg-white/90' : 'bg-white/30'"
                            class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors shrink-0">
                        <span :class="enabled ? 'translate-x-6 bg-indigo-600' : 'translate-x-1 bg-white/70'"
                              class="inline-block h-5 w-5 transform rounded-full transition-transform shadow"></span>
                    </button>
                    <input type="hidden" name="discord_enabled" :value="enabled ? '1' : '0'">
                    <p class="text-[10px] text-indigo-100 mt-1 text-right" x-text="enabled ? 'Aktif' : 'Nonaktif'"></p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-4">

            {{-- Webhook URL --}}
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Webhook URL
                </label>
                <input type="url" name="discord_webhook"
                       value="{{ old('discord_webhook', $setting->discord_webhook) }}"
                       placeholder="https://discord.com/api/webhooks/..."
                       class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                              text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500
                              font-mono text-sm @error('discord_webhook') border-red-500 @enderror">
                @error('discord_webhook')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            {{-- Cara mendapat Webhook --}}
            <div x-data="{ open: false }">
                <button type="button" @click="open = !open"
                        class="flex items-center gap-1.5 text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 font-medium">
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    Cara mendapatkan Webhook URL
                </button>
                <div x-show="open" x-collapse class="mt-3 p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl text-xs text-slate-600 dark:text-slate-400 space-y-1 leading-relaxed">
                    <ol class="list-decimal list-inside space-y-1.5 pl-1">
                        <li>Buka Discord → masuk ke server yang ingin digunakan</li>
                        <li>Klik kanan channel → <span class="font-semibold">Edit Channel</span></li>
                        <li>Pilih tab <span class="font-semibold">Integrations</span> → <span class="font-semibold">Webhooks</span></li>
                        <li>Klik <span class="font-semibold">New Webhook</span> → beri nama → <span class="font-semibold">Copy Webhook URL</span></li>
                        <li>Paste URL tersebut di kolom di atas</li>
                    </ol>
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan
                </button>
            </div>
        </div>
    </div>

    </form>{{-- end main form --}}

    {{-- ══════════════════════ WEBHOOK / PERINTAH BOT ══════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-white">Webhook — Perintah Bot</h2>
                    <p class="text-green-100 text-xs mt-0.5">Aktifkan bot untuk menerima perintah dari Telegram</p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-5">

            {{-- Flash khusus webhook --}}
            @if(session('webhook_info'))
            <div class="flex items-start gap-2 px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl text-xs text-blue-700 dark:text-blue-300 font-mono">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('webhook_info') }}
            </div>
            @endif

            {{-- Daftar perintah --}}
            <div>
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">Perintah yang tersedia:</p>
                <div class="bg-slate-50 dark:bg-slate-900 rounded-xl p-4 space-y-2 font-mono text-xs border border-slate-200 dark:border-slate-700">
                    <div class="flex items-start gap-3">
                        <span class="text-green-600 dark:text-green-400 shrink-0">/jadwal</span>
                        <span class="text-slate-500">— Upload foto/PDF jadwal untuk hari ini</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="text-green-600 dark:text-green-400 shrink-0">/jadwal 2026-06-01</span>
                        <span class="text-slate-500">— Upload foto/PDF jadwal untuk tanggal tertentu</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="text-green-600 dark:text-green-400 shrink-0">/jadwal 01/06/2026</span>
                        <span class="text-slate-500">— Format tanggal DD/MM/YYYY juga didukung</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="text-slate-400 shrink-0">/batal</span>
                        <span class="text-slate-500">— Batalkan upload yang sedang berlangsung</span>
                    </div>
                </div>
            </div>

            {{-- Webhook URL --}}
            <div>
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">URL Webhook (daftarkan ke Telegram):</p>
                <div class="flex items-center gap-2">
                    <code class="flex-1 px-3 py-2.5 bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-mono break-all border border-slate-200 dark:border-slate-700">
                        {{ url('/telegram/webhook') }}
                    </code>
                </div>
                <p class="text-[10px] text-amber-600 dark:text-amber-400 mt-1.5">
                    ⚠️ URL harus bisa diakses publik (HTTPS). Untuk lokal gunakan ngrok/tunnel terlebih dahulu.
                </p>
            </div>

            {{-- Tombol aksi --}}
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('developer.bot-settings.webhook-register') }}">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Daftarkan Webhook
                    </button>
                </form>
                <form method="POST" action="{{ route('developer.bot-settings.webhook-info') }}">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Cek Status
                    </button>
                </form>
            </div>

        </div>
    </div>

    {{-- ══════════════════════ ALERT THRESHOLD ══════════════════════ --}}
    <form method="POST" action="{{ route('developer.bot-settings.update') }}">
        @csrf
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="bg-gradient-to-r from-rose-500 to-red-600 px-6 py-4">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-white">Alert Reject Rate</h2>
                        <p class="text-rose-100 text-xs mt-0.5">Notif otomatis ke bot jika reject rate melewati batas</p>
                    </div>
                </div>
                {{-- Hidden fields agar form ini tidak overwrite field lain --}}
                <input type="hidden" name="telegram_token"    value="{{ $setting->telegram_token }}">
                <input type="hidden" name="telegram_chat_id"  value="{{ $setting->telegram_chat_id }}">
                <input type="hidden" name="telegram_report_chat_id" value="{{ $setting->telegram_report_chat_id }}">
                <input type="hidden" name="telegram_enabled"  value="{{ $setting->telegram_enabled ? '1' : '0' }}">
                <input type="hidden" name="discord_webhook"   value="{{ $setting->discord_webhook }}">
                <input type="hidden" name="discord_enabled"   value="{{ $setting->discord_enabled ? '1' : '0' }}">
                <input type="hidden" name="report_enabled"    value="{{ $setting->report_enabled ? '1' : '0' }}">
                <input type="hidden" name="disable_devtools"  value="{{ $setting->disable_devtools ? '1' : '0' }}">
                <input type="hidden" name="maintenance_mode"  value="{{ $setting->maintenance_mode ? '1' : '0' }}">
                <input type="hidden" name="maintenance_message" value="{{ $setting->maintenance_message }}">
                <input type="hidden" name="maintenance_until" value="{{ $setting->maintenance_until?->format('Y-m-d\TH:i') }}">
            </div>
        </div>

        <div class="p-6 space-y-4">
            <div class="flex flex-col sm:flex-row items-start gap-4">
                <div class="flex-1 w-full">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Batas Reject Rate (%)
                    </label>
                    <p class="text-xs text-slate-400 mb-2">
                        Alert dikirim maksimal <strong>sekali per produk per hari</strong> saat reject rate melewati batas ini.
                        Isi <strong>0</strong> untuk menonaktifkan.
                    </p>
                    <div class="flex items-center gap-3">
                        <input type="number" name="reject_threshold" step="0.5" min="0" max="100"
                               value="{{ old('reject_threshold', $setting->reject_threshold ?? 5) }}"
                               class="w-32 px-4 py-2.5 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                                      text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-rose-500
                                      font-mono text-sm @error('reject_threshold') border-red-500 @enderror">
                        <span class="text-sm text-slate-500">%</span>
                        <span class="text-xs text-slate-400">· Default: 5%</span>
                    </div>
                    @error('reject_threshold')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div class="w-full sm:w-auto sm:shrink-0 p-4 bg-rose-50 dark:bg-rose-900/20 rounded-xl border border-rose-100 dark:border-rose-800 text-xs text-slate-600 dark:text-slate-400 space-y-1">
                    <p class="font-semibold text-rose-700 dark:text-rose-400 mb-1.5">Contoh pesan alert:</p>
                    <p>⚠️ <strong>ALERT: Reject Rate Tinggi!</strong></p>
                    <p>📦 Produk: <strong>Trafo 50KVA</strong></p>
                    <p>📉 Reject Rate: <strong>8.3%</strong> (batas: 5%)</p>
                    <p>✕ Reject: <strong>5</strong> dari 60 unit</p>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex items-center gap-2 px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan Batas
                </button>
            </div>
        </div>
    </div>
    </form>

    {{-- ══════════════════════ LAPORAN HARIAN ══════════════════════ --}}
    <form method="POST" action="{{ route('developer.bot-settings.update') }}">
        @csrf
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-4">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-white">Laporan Harian Terjadwal</h2>
                        <p class="text-amber-100 text-xs mt-0.5">Ringkasan produksi dikirim otomatis setiap jam 22:00 WIB</p>
                    </div>
                </div>
                {{-- Toggle --}}
                <div x-data="{ enabled: {{ $setting->report_enabled ? 'true' : 'false' }} }">
                    <button type="button" @click="enabled = !enabled"
                            :class="enabled ? 'bg-white/90' : 'bg-white/30'"
                            class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors shrink-0">
                        <span :class="enabled ? 'translate-x-6 bg-amber-600' : 'translate-x-1 bg-white/70'"
                              class="inline-block h-5 w-5 transform rounded-full transition-transform shadow"></span>
                    </button>
                    <input type="hidden" name="report_enabled" :value="enabled ? '1' : '0'">
                    <p class="text-[10px] text-amber-100 mt-1 text-right" x-text="enabled ? 'Aktif' : 'Nonaktif'"></p>
                </div>
                {{-- Hidden fields --}}
                <input type="hidden" name="telegram_token"    value="{{ $setting->telegram_token }}">
                <input type="hidden" name="telegram_chat_id"  value="{{ $setting->telegram_chat_id }}">
                <input type="hidden" name="telegram_report_chat_id" value="{{ $setting->telegram_report_chat_id }}">
                <input type="hidden" name="telegram_enabled"  value="{{ $setting->telegram_enabled ? '1' : '0' }}">
                <input type="hidden" name="discord_webhook"   value="{{ $setting->discord_webhook }}">
                <input type="hidden" name="discord_enabled"   value="{{ $setting->discord_enabled ? '1' : '0' }}">
                <input type="hidden" name="reject_threshold"  value="{{ $setting->reject_threshold ?? 5 }}">
                <input type="hidden" name="disable_devtools"  value="{{ $setting->disable_devtools ? '1' : '0' }}">
                <input type="hidden" name="maintenance_mode"  value="{{ $setting->maintenance_mode ? '1' : '0' }}">
                <input type="hidden" name="maintenance_message" value="{{ $setting->maintenance_message }}">
                <input type="hidden" name="maintenance_until" value="{{ $setting->maintenance_until?->format('Y-m-d\TH:i') }}">
            </div>
        </div>

        <div class="p-6 space-y-4">
            <div class="flex flex-col sm:flex-row items-start gap-4">
                <div class="flex-1 w-full text-sm text-slate-600 dark:text-slate-400 space-y-1.5">
                    <p>Laporan berisi ringkasan produksi per kategori, total qty, total reject, dan reject rate hari itu.</p>
                    <p class="text-xs text-slate-400">Dikirim ke semua bot yang aktif (Telegram + Discord).</p>
                    <p class="text-xs text-amber-600 dark:text-amber-400">⚠️ Pastikan Laravel Scheduler sudah aktif di server:
                        <code class="bg-slate-100 dark:bg-slate-900 px-1.5 py-0.5 rounded font-mono text-[10px]">* * * * * php artisan schedule:run</code>
                    </p>
                </div>
                <div class="w-full sm:w-auto sm:shrink-0 p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-800 text-xs text-slate-600 dark:text-slate-400 space-y-0.5">
                    <p class="font-semibold text-amber-700 dark:text-amber-400 mb-1.5">Contoh isi laporan:</p>
                    <p>📊 <strong>Laporan Harian Produksi</strong></p>
                    <p>📅 28 Mei 2026 · 🕙 22:00 WIB</p>
                    <p>━━━━━━━━━━━━━━━━━━</p>
                    <p>📦 <strong>Channel</strong>: 120 unit · ✕ 4 reject</p>
                    <p>📦 <strong>Tangki</strong>: 55 unit</p>
                    <p>📦 <strong>Cover</strong>: 80 unit</p>
                    <p>━━━━━━━━━━━━━━━━━━</p>
                    <p>📈 Total: <strong>255 unit</strong></p>
                    <p>🟡 Reject Rate: <strong>1.6%</strong></p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit"
                        class="flex items-center gap-2 px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan
                </button>
                <button type="submit"
                        formaction="{{ route('developer.bot-settings.send-daily-report') }}"
                        data-confirm="Kirim laporan produksi hari ini ke semua bot yang aktif?"
                        data-confirm-title="Kirim Laporan Harian"
                        class="flex items-center gap-2 px-5 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600
                               text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    Kirim Sekarang
                </button>
            </div>
        </div>
    </div>
    </form>

    {{-- ══════════════════════ MAINTENANCE ══════════════════════ --}}
    <form method="POST" action="{{ route('developer.bot-settings.update') }}">
        @csrf
        <input type="hidden" name="telegram_token"    value="{{ $setting->telegram_token }}">
        <input type="hidden" name="telegram_chat_id"  value="{{ $setting->telegram_chat_id }}">
        <input type="hidden" name="telegram_report_chat_id" value="{{ $setting->telegram_report_chat_id }}">
        <input type="hidden" name="telegram_enabled"  value="{{ $setting->telegram_enabled ? '1' : '0' }}">
        <input type="hidden" name="discord_webhook"   value="{{ $setting->discord_webhook }}">
        <input type="hidden" name="discord_enabled"   value="{{ $setting->discord_enabled ? '1' : '0' }}">
        <input type="hidden" name="reject_threshold"  value="{{ $setting->reject_threshold ?? 5 }}">
        <input type="hidden" name="report_enabled"    value="{{ $setting->report_enabled ? '1' : '0' }}">
        <input type="hidden" name="disable_devtools"  value="{{ $setting->disable_devtools ? '1' : '0' }}">

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="bg-linear-to-r from-orange-500 to-amber-600 px-6 py-4">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-white">Mode Maintenance</h2>
                        <p class="text-amber-100 text-xs mt-0.5">Tampilkan banner peringatan ke semua user selain developer</p>
                    </div>
                </div>
                <div x-data="{ enabled: {{ $setting->maintenance_mode ? 'true' : 'false' }} }">
                    <button type="button" @click="enabled = !enabled"
                            :class="enabled ? 'bg-white/90' : 'bg-white/30'"
                            class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors shrink-0">
                        <span :class="enabled ? 'translate-x-6 bg-orange-600' : 'translate-x-1 bg-white/70'"
                              class="inline-block h-5 w-5 transform rounded-full transition-transform shadow"></span>
                    </button>
                    <input type="hidden" name="maintenance_mode" :value="enabled ? '1' : '0'">
                    <p class="text-[10px] text-amber-100 mt-1 text-right" x-text="enabled ? 'Aktif' : 'Nonaktif'"></p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Ketika aktif, semua user <strong>(admin, supervisor, operator, visitor)</strong> akan melihat layar peringatan penuh dan tidak bisa mengakses aplikasi.
                Developer tetap bisa mengakses semua fitur seperti biasa.
            </p>

            {{-- Pesan --}}
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                    Pesan Maintenance <span class="font-normal text-slate-400">(opsional)</span>
                </label>
                <input type="text" name="maintenance_message"
                       value="{{ old('maintenance_message', $setting->maintenance_message) }}"
                       placeholder="Sistem sedang dalam pemeliharaan. Mohon maaf atas ketidaknyamanannya."
                       class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                              text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500
                              text-sm">
                <p class="text-[10px] text-slate-400 mt-1">Kosongkan untuk menggunakan pesan default.</p>
            </div>

            {{-- Jadwal Selesai --}}
            <div x-data="{ hasTimer: {{ $setting->maintenance_until ? 'true' : 'false' }} }">
                <div class="flex items-center gap-2 mb-2">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Jadwal Selesai Otomatis <span class="font-normal text-slate-400">(opsional)</span>
                    </label>
                    <button type="button" @click="hasTimer = !hasTimer; if (!hasTimer) $refs.until.value = ''"
                            :class="hasTimer ? 'bg-orange-500 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400'"
                            class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide transition-colors">
                        <span x-text="hasTimer ? 'Aktif' : 'Nonaktif'"></span>
                    </button>
                </div>

                <div x-show="hasTimer" x-collapse>
                    <input type="datetime-local" name="maintenance_until" x-ref="until"
                           value="{{ old('maintenance_until', $setting->maintenance_until?->format('Y-m-d\TH:i')) }}"
                           class="w-full sm:w-auto px-4 py-2.5 border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900
                                  text-slate-800 dark:text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500
                                  text-sm">
                    <p class="text-[10px] text-slate-400 mt-1">
                        Maintenance akan otomatis berakhir pada waktu yang dipilih (zona waktu server).
                        @if($setting->maintenance_until)
                        <span class="text-orange-500 font-semibold">
                            Saat ini: {{ $setting->maintenance_until->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
                        </span>
                        @endif
                    </p>
                </div>
                <input x-show="!hasTimer" type="hidden" name="maintenance_until" value="">
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex items-center gap-2 px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan
                </button>
            </div>
        </div>
    </div>
    </form>

    {{-- ══════════════════════ KEAMANAN ══════════════════════ --}}
    <form method="POST" action="{{ route('developer.bot-settings.update') }}">
        @csrf
        <input type="hidden" name="telegram_token"       value="{{ $setting->telegram_token }}">
        <input type="hidden" name="telegram_chat_id"     value="{{ $setting->telegram_chat_id }}">
        <input type="hidden" name="telegram_report_chat_id" value="{{ $setting->telegram_report_chat_id }}">
        <input type="hidden" name="telegram_enabled"     value="{{ $setting->telegram_enabled ? '1' : '0' }}">
        <input type="hidden" name="discord_webhook"      value="{{ $setting->discord_webhook }}">
        <input type="hidden" name="discord_enabled"      value="{{ $setting->discord_enabled ? '1' : '0' }}">
        <input type="hidden" name="reject_threshold"     value="{{ $setting->reject_threshold ?? 5 }}">
        <input type="hidden" name="report_enabled"       value="{{ $setting->report_enabled ? '1' : '0' }}">
        <input type="hidden" name="maintenance_mode"     value="{{ $setting->maintenance_mode ? '1' : '0' }}">
        <input type="hidden" name="maintenance_message"  value="{{ $setting->maintenance_message }}">
        <input type="hidden" name="maintenance_until"    value="{{ $setting->maintenance_until?->format('Y-m-d\TH:i') }}">

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="bg-linear-to-r from-slate-600 to-slate-800 px-6 py-4">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-white">Keamanan</h2>
                        <p class="text-slate-300 text-xs mt-0.5">Proteksi akses developer tools di browser</p>
                    </div>
                </div>
                {{-- Toggle --}}
                <div x-data="{ enabled: {{ $setting->disable_devtools ? 'true' : 'false' }} }">
                    <button type="button" @click="enabled = !enabled"
                            :class="enabled ? 'bg-white/90' : 'bg-white/30'"
                            class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors shrink-0">
                        <span :class="enabled ? 'translate-x-6 bg-slate-700' : 'translate-x-1 bg-white/70'"
                              class="inline-block h-5 w-5 transform rounded-full transition-transform shadow"></span>
                    </button>
                    <input type="hidden" name="disable_devtools" :value="enabled ? '1' : '0'">
                    <p class="text-[10px] text-slate-300 mt-1 text-right" x-text="enabled ? 'Aktif' : 'Nonaktif'"></p>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-4">
            <div class="flex items-start gap-4">
                <div class="flex-1 text-sm text-slate-600 dark:text-slate-400 space-y-2">
                    <p>Ketika diaktifkan, semua user <strong>kecuali developer</strong> tidak dapat membuka:</p>
                    <ul class="list-disc list-inside text-xs space-y-1 text-slate-500 dark:text-slate-400 pl-1">
                        <li>F12 (DevTools)</li>
                        <li>Ctrl+Shift+I / Ctrl+Shift+J / Ctrl+Shift+C (Inspect Element)</li>
                        <li>Ctrl+U (View Page Source)</li>
                        <li>Klik kanan (Context Menu)</li>
                    </ul>
                    <p class="text-xs text-amber-600 dark:text-amber-400">⚠️ Ini adalah pencegahan dasar — bukan perlindungan mutlak.</p>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex items-center gap-2 px-5 py-2.5 bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan
                </button>
            </div>
        </div>
    </div>
    </form>

    {{-- ══════════════════════ TEST BUTTONS ══════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1">Uji Koneksi Bot</h3>
        <p class="text-xs text-slate-400 mb-4">Simpan pengaturan terlebih dahulu, lalu klik tombol uji untuk mengirim pesan test.</p>
        <div class="flex flex-wrap gap-3">

            {{-- Test Telegram --}}
            <form method="POST" action="{{ route('developer.bot-settings.test') }}">
                @csrf
                <input type="hidden" name="type" value="telegram">
                <button type="submit"
                        data-confirm="Kirim test message ke Telegram sekarang?"
                        data-confirm-title="Test Telegram"
                        class="flex items-center gap-2 px-5 py-2.5 bg-sky-50 dark:bg-sky-900/20 hover:bg-sky-100 dark:hover:bg-sky-900/40
                               text-sky-700 dark:text-sky-400 border border-sky-200 dark:border-sky-800
                               text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.17 13.737 4.25 12.82c-.652-.204-.666-.652.136-.965l11.26-4.339c.543-.194 1.018.133.848.705z"/>
                    </svg>
                    Test Telegram
                </button>
            </form>

            {{-- Test Discord --}}
            <form method="POST" action="{{ route('developer.bot-settings.test') }}">
                @csrf
                <input type="hidden" name="type" value="discord">
                <button type="submit"
                        data-confirm="Kirim test message ke Discord sekarang?"
                        data-confirm-title="Test Discord"
                        class="flex items-center gap-2 px-5 py-2.5 bg-indigo-50 dark:bg-indigo-900/20 hover:bg-indigo-100 dark:hover:bg-indigo-900/40
                               text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800
                               text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057.1 18.079.112 18.1.133 18.11a19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                    </svg>
                    Test Discord
                </button>
            </form>
        </div>
    </div>

    {{-- ══════════════════════ FORMAT PESAN ══════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-4">Format Pesan Notifikasi</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            {{-- Telegram preview --}}
            <div>
                <p class="text-xs font-semibold text-sky-600 dark:text-sky-400 mb-2 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.17 13.737 4.25 12.82c-.652-.204-.666-.652.136-.965l11.26-4.339c.543-.194 1.018.133.848.705z"/>
                    </svg>
                    Telegram
                </p>
                <div class="bg-slate-50 dark:bg-slate-900 rounded-xl p-4 font-mono text-xs text-slate-700 dark:text-slate-300 leading-relaxed border border-slate-200 dark:border-slate-700">
                    <p>🔐 <strong>LOGIN</strong></p>
                    <p>👤 <strong>yoga ariyanto</strong> <em>(admin)</em></p>
                    <p>📝 User login ke sistem</p>
                    <p>🕒 28/05/2026 10:30:45 WIB</p>
                    <hr class="my-2 border-slate-200 dark:border-slate-700">
                    <p>➕ <strong>CREATE</strong></p>
                    <p>👤 <strong>Dicky</strong> <em>(operator)</em></p>
                    <p>📝 Input produksi: Trafo 50KVA #001</p>
                    <p>🕒 28/05/2026 08:15:20 WIB</p>
                </div>
            </div>

            {{-- Discord preview --}}
            <div>
                <p class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 mb-2 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057.1 18.079.112 18.1.133 18.11a19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                    </svg>
                    Discord (Embed)
                </p>
                <div class="bg-slate-50 dark:bg-slate-900 rounded-xl p-4 text-xs text-slate-700 dark:text-slate-300 leading-relaxed border border-slate-200 dark:border-slate-700 space-y-2">
                    <div class="border-l-4 border-green-500 pl-3 py-1">
                        <p class="font-bold text-green-700 dark:text-green-400">➕ Create</p>
                        <p class="text-slate-600 dark:text-slate-400">Input produksi: Trafo 50KVA #001</p>
                        <div class="flex gap-4 mt-1.5 text-[10px]">
                            <span><strong>👤 User</strong><br>Dicky</span>
                            <span><strong>🎭 Role</strong><br>operator</span>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1.5">Asata Production System · today at 08:15</p>
                    </div>
                    <div class="border-l-4 border-red-500 pl-3 py-1">
                        <p class="font-bold text-red-700 dark:text-red-400">🗑️ Delete</p>
                        <p class="text-slate-600 dark:text-slate-400">Hapus file gambar kerja: Trafo PLN</p>
                        <p class="text-[10px] text-slate-400 mt-1.5">Asata Production System · today at 10:05</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action map --}}
        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2">Kode warna Discord per aksi:</p>
            <div class="flex flex-wrap gap-2">
                @foreach([
                    ['icon' => '🔐', 'label' => 'login',  'cls' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'],
                    ['icon' => '🚪', 'label' => 'logout', 'cls' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400'],
                    ['icon' => '➕', 'label' => 'create', 'cls' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'],
                    ['icon' => '✏️', 'label' => 'update', 'cls' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'],
                    ['icon' => '🗑️', 'label' => 'delete', 'cls' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'],
                    ['icon' => '📤', 'label' => 'upload', 'cls' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'],
                    ['icon' => '📊', 'label' => 'export', 'cls' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400'],
                ] as $a)
                <span class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $a['cls'] }}">
                    {{ $a['icon'] }} {{ $a['label'] }}
                </span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ══════════════════════ SOCIAL LINKS DEVELOPER ══════════════════════ --}}
    <form method="POST" action="{{ route('profile.about-info') }}">
        @csrf
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 text-[10px] font-bold bg-blue-600 text-white rounded-full uppercase tracking-wide">Developer</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide">Social Links</h3>
                    <p class="text-xs text-slate-400">Ditampilkan di halaman <em>Tentang Aplikasi</em></p>
                </div>
            </div>
        </div>
        <div class="p-6 space-y-4">

            {{-- Handle --}}
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                    Handle <span class="ml-1 text-xs font-normal text-slate-400">(contoh: @qc.yoga)</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold">@</span>
                    <input type="text" name="handle"
                           value="{{ old('handle', ltrim(auth()->user()->handle ?? '', '@')) }}"
                           placeholder="qc.yoga"
                           class="w-full pl-8 pr-4 py-2.5 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
            </div>

            {{-- Bio --}}
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                    Bio <span class="ml-1 text-xs font-normal text-slate-400">(maks 500 karakter)</span>
                </label>
                <textarea name="bio" rows="8" placeholder="Tuliskan bio singkat..."
                          class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                 border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none text-sm">{{ old('bio', auth()->user()->bio) }}</textarea>
            </div>

            {{-- Links grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-pink-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                            Instagram
                        </span>
                    </label>
                    <input type="url" name="link_instagram" value="{{ old('link_instagram', auth()->user()->link_instagram) }}"
                           placeholder="https://instagram.com/username"
                           class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-slate-600 dark:text-slate-300" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                            GitHub
                        </span>
                    </label>
                    <input type="url" name="link_github" value="{{ old('link_github', auth()->user()->link_github) }}"
                           placeholder="https://github.com/username"
                           class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Portfolio
                        </span>
                    </label>
                    <input type="url" name="link_portfolio" value="{{ old('link_portfolio', auth()->user()->link_portfolio) }}"
                           placeholder="https://namakamu.com"
                           class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Email Publik
                        </span>
                    </label>
                    <input type="email" name="link_email" value="{{ old('link_email', auth()->user()->link_email) }}"
                           placeholder="email@domain.com"
                           class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
            </div>

            <div class="pt-1">
                <button type="submit"
                        class="flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan
                </button>
            </div>
        </div>
    </div>
    </form>

    {{-- ══════════════════════ FOTO HALAMAN ABOUT ══════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 text-[10px] font-bold bg-blue-600 text-white rounded-full uppercase tracking-wide">Developer</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide">Foto Halaman About</h3>
                    <p class="text-xs text-slate-400">Berbeda dari foto profil akun · Ditampilkan di halaman <em>Tentang Aplikasi</em></p>
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('profile.about-avatar') }}" enctype="multipart/form-data"
              id="about-avatar-form" class="p-6">
            @csrf
            <div class="flex items-center gap-4 flex-wrap">
                {{-- Preview --}}
                <div class="shrink-0 rounded-2xl overflow-hidden border-2 border-slate-200 dark:border-slate-600"
                     style="width:80px; height:80px; background: linear-gradient(145deg, #93c5fd, #1d4ed8);">
                    <div id="about-avatar-placeholder"
                         style="width:100%; height:100%; display:{{ auth()->user()->about_avatar ? 'none' : 'flex' }}; align-items:center; justify-content:center;">
                        <span style="font-size:1.5rem; font-weight:900; color:white;">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                    </div>
                    <img id="about-avatar-preview"
                         src="{{ auth()->user()->about_avatar ? route('storage.file', ['path' => auth()->user()->about_avatar]) : '' }}"
                         alt="Foto About"
                         style="width:100%; height:100%; object-fit:cover; display:{{ auth()->user()->about_avatar ? 'block' : 'none' }};"
                         onerror="this.style.display='none'; document.getElementById('about-avatar-placeholder').style.display='flex';">
                </div>
                {{-- Controls --}}
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <label for="about-avatar-input"
                               class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 dark:bg-slate-700
                                      hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300
                                      text-sm font-semibold rounded-xl cursor-pointer transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Pilih Foto
                        </label>
                        <input type="file" id="about-avatar-input" name="about_avatar"
                               accept="image/jpeg,image/png,image/webp,image/gif" class="hidden">
                        <button type="submit" id="about-avatar-submit" style="display:none;"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700
                                       text-white text-sm font-semibold rounded-xl transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan Foto
                        </button>
                    </div>
                    <p class="text-xs text-slate-400 mt-1.5">JPG, PNG, WebP · Maks 5 MB</p>
                    @error('about_avatar')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </form>
    </div>

</div>

@push('scripts')
<script>
const aboutInput  = document.getElementById('about-avatar-input');
const aboutSubmit = document.getElementById('about-avatar-submit');
if (aboutInput) {
    aboutInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) { window.appAlert('Ukuran file foto maksimal 5 MB. Silakan pilih file yang lebih kecil.', { title: 'File Terlalu Besar' }); this.value = ''; return; }
        const preview     = document.getElementById('about-avatar-preview');
        const placeholder = document.getElementById('about-avatar-placeholder');
        const reader      = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
        if (aboutSubmit) aboutSubmit.style.display = 'inline-flex';
    });
}
</script>
@endpush

@endsection
