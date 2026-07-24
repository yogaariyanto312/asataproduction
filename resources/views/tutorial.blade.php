@extends('layouts.app')

@section('title', 'Tutorial')
@section('page-title', 'Tutorial')
@section('page-subtitle', 'Panduan penggunaan aplikasi Asata Production')

@section('content')
<div class="max-w-3xl lg:max-w-5xl mx-auto space-y-4" x-data="{ open: null }">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl px-6 py-5 shadow-lg shadow-blue-900/20">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-white">Panduan Penggunaan Aplikasi</h2>
                <p class="text-blue-100 text-sm mt-0.5">Pilih topik di bawah untuk melihat langkah-langkahnya</p>
            </div>
        </div>
    </div>

    {{-- Embed Video / Website --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden"
         x-data="{ editOpen: false, url: '{{ addslashes($iframeUrl ?? '') }}' }">

        {{-- Card header --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-red-100 dark:bg-red-900/40 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="text-sm font-bold text-slate-700 dark:text-white">Tutorial</span>
            </div>
            @if(auth()->user()->isDeveloper())
            <button @click="editOpen = !editOpen"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg
                           bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300
                           hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit URL
            </button>
            @endif
        </div>

        {{-- Edit form (developer only) --}}
        @if(auth()->user()->isDeveloper())
        <div x-show="editOpen" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 bg-blue-50 dark:bg-blue-900/20">
            <form method="POST" action="{{ route('tutorial.embed.update') }}" class="flex gap-3 items-end">
                @csrf
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">
                        URL Embed
                        <span class="ml-1 font-normal text-slate-400">(YouTube embed, website, atau kosongkan untuk sembunyikan)</span>
                    </label>
                    <input type="text" name="iframe_url"
                           x-model="url"
                           placeholder="https://www.youtube.com/embed/VIDEO_ID atau URL website..."
                           class="w-full px-3 py-2.5 text-sm rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white
                                  border border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono">
                </div>
                <button type="submit"
                        class="shrink-0 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    Simpan
                </button>
            </form>
            @if(session('success'))
            <p class="mt-2 text-xs text-green-600 dark:text-green-400 font-medium">{{ session('success') }}</p>
            @endif
        </div>
        @endif

        {{-- Iframe / Placeholder --}}
        @if($iframeUrl)
        <div class="relative w-full" style="padding-bottom: 56.25%;">
            <iframe src="{{ $iframeUrl }}"
                    class="absolute inset-0 w-full h-full"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen
                    loading="lazy">
            </iframe>
        </div>
        @else
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <div class="w-16 h-16 bg-slate-100 dark:bg-slate-700 rounded-2xl flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">tutorial belum tersedia</p>
            @if(auth()->user()->isDeveloper())
            <p class="text-xs text-slate-400 mt-1">Klik <strong>Edit URL</strong> di atas untuk menambahkan video atau website.</p>
            @else
            <p class="text-xs text-slate-400 mt-1">Hubungi developer untuk menambahkan video tutorial.</p>
            @endif
        </div>
        @endif

    </div>

    @php
    $topics = [
        [
            'id'    => 1,
            'icon'  => 'M12 4v16m8-8H4',
            'color' => 'text-green-600 dark:text-green-400',
            'bg'    => 'bg-green-100 dark:bg-green-900/40',
            'title' => 'Bagaimana caranya input data hasil produksi?',
            'steps' => [
                'Klik menu <strong>Input Produksi</strong> di sidebar kiri.',
                'Pilih <strong>Tanggal Produksi</strong> sesuai hari produksi berlangsung.',
                'Pilih <strong>Produk</strong> dari dropdown (Channel, Cover, Tangki, dsb.).',
                'Jika produk bertipe <em>Swasta</em> atau <em>Typetest</em>, pilih <strong>Input Seri & KVA Manual</strong> lalu isi kolom <strong>Nomor Seri</strong> dan <strong>KVA</strong>.',
                'Isi jumlah unit yang diproduksi. Untuk Channel, isi kolom <strong>Channel UP</strong> dan <strong>Channel BT</strong> secara terpisah.',
                'Isi <strong>Nomor Urut</strong> — masukkan nomor awal, sistem akan otomatis mengisi preview range nomor.',
                'Jika ada barang reject, klik <strong>Ada Reject / Defect?</strong> dan isi jumlah serta keterangannya.',
                'Tambahkan <strong>Keterangan</strong> jika diperlukan (opsional).',
                'Klik tombol <strong>Save</strong> untuk menyimpan data.',
            ],
        ],
        [
            'id'    => 2,
            'icon'  => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
            'color' => 'text-blue-600 dark:text-blue-400',
            'bg'    => 'bg-blue-100 dark:bg-blue-900/40',
            'title' => 'Bagaimana caranya melihat riwayat hasil produksi?',
            'steps' => [
                'Klik menu <strong>Riwayat Produksi</strong> di sidebar kiri.',
                'Data ditampilkan dalam kartu per hari, dikelompokkan berdasarkan jenis produk (Channel, Cover, Tangki).',
                'Untuk mencari data tertentu, gunakan kolom <strong>Cari Produk</strong> atau filter berdasarkan <strong>Nama Produk</strong>, <strong>Dari Tanggal</strong>, dan <strong>Sampai Tanggal</strong>.',
                'Klik tombol <strong>Filter</strong> untuk menerapkan pencarian, atau <strong>Reset</strong> untuk kembali ke tampilan semua data.',
                'Klik ikon <svg class="inline w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> pada entri tertentu untuk melihat detail lengkap.',
            ],
        ],
        [
            'id'    => 3,
            'icon'  => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
            'color' => 'text-amber-600 dark:text-amber-400',
            'bg'    => 'bg-amber-100 dark:bg-amber-900/40',
            'title' => 'Bagaimana caranya edit atau hapus data produksi jika terjadi kesalahan?',
            'steps' => [
                'Buka menu <strong>Riwayat Produksi</strong>.',
                'Temukan entri yang ingin diubah (gunakan fitur filter jika perlu).',
                'Klik ikon <svg class="inline w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg> (pensil) untuk mengedit data.',
                'Ubah data yang salah, lalu klik <strong>Simpan Perubahan</strong>.',
                'Untuk menghapus, klik ikon <svg class="inline w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg> (tong sampah) pada entri tersebut.',
                'Konfirmasi penghapusan pada dialog yang muncul.',
                '<span class="text-red-500 font-semibold">Perhatian:</span> Data yang dihapus tidak dapat dikembalikan.',
            ],
        ],
        [
            'id'    => 4,
            'icon'  => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
            'color' => 'text-purple-600 dark:text-purple-400',
            'bg'    => 'bg-purple-100 dark:bg-purple-900/40',
            'title' => 'Bagaimana caranya ganti password?',
            'steps' => [
                'Klik foto atau nama profil Anda di pojok kanan atas, lalu pilih <strong>Profil</strong>. Atau langsung buka menu <strong>Profil</strong>.',
                'Gulir ke bawah hingga menemukan bagian <strong>Ganti Password</strong>.',
                'Isi kolom <strong>Password Lama</strong> dengan password yang sedang digunakan.',
                'Isi kolom <strong>Password Baru</strong> dengan password yang diinginkan (minimal 8 karakter).',
                'Isi kolom <strong>Konfirmasi Password Baru</strong> — harus sama persis dengan password baru.',
                'Klik tombol <strong>Simpan Password</strong>.',
                'Password berhasil diubah. Gunakan password baru saat login berikutnya.',
            ],
        ],
        [
            'id'    => 5,
            'icon'  => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
            'color' => 'text-red-600 dark:text-red-400',
            'bg'    => 'bg-red-100 dark:bg-red-900/40',
            'title' => 'Bagaimana jika saya lupa password?',
            'steps' => [
                'Pada halaman login, klik tautan <strong>Lupa Password?</strong>.',
                'Masukkan <strong>alamat email</strong> yang terdaftar di akun Anda.',
                'Klik tombol <strong>Kirim Link Reset</strong>.',
                'Cek email Anda — Anda akan menerima email berisi tautan reset password.',
                'Klik tautan di email tersebut, lalu isi password baru Anda.',
                'Klik <strong>Simpan Password Baru</strong> dan login menggunakan password baru.',
                '<span class="text-amber-500 font-semibold">Catatan:</span> Fitur ini hanya berfungsi jika email sudah ditautkan ke akun Anda. Lihat tutorial berikutnya jika belum.',
            ],
        ],
        [
            'id'    => 6,
            'icon'  => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            'color' => 'text-cyan-600 dark:text-cyan-400',
            'bg'    => 'bg-cyan-100 dark:bg-cyan-900/40',
            'title' => 'Bagaimana caranya menautkan email agar bisa reset password via email?',
            'steps' => [
                'Buka menu <strong>Profil</strong> dari sidebar atau pojok kanan atas.',
                'Pada bagian <strong>Informasi Akun</strong>, temukan kolom <strong>Email</strong>.',
                'Isi kolom email dengan alamat email aktif yang Anda miliki.',
                'Klik tombol <strong>Simpan Profil</strong>.',
                'Email berhasil ditautkan. Sekarang fitur lupa password sudah bisa digunakan.',
                '<span class="text-amber-500 font-semibold">Pastikan</span> email yang digunakan aktif dan Anda memiliki akses ke kotak masuk email tersebut.',
            ],
        ],
        [
            'id'    => 7,
            'icon'  => 'M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16',
            'color' => 'text-emerald-600 dark:text-emerald-400',
            'bg'    => 'bg-emerald-100 dark:bg-emerald-900/40',
            'title' => 'Bagaimana caranya update foto profil?',
            'steps' => [
                'Buka menu <strong>Profil</strong> dari sidebar atau klik nama Anda di pojok kanan atas.',
                'Pada bagian <strong>Link Foto Profil</strong>, tempel (paste) link URL gambar Anda.',
                'Upload foto ke <strong><a href="https://imgbb.com" target="_blank" class="underline">imgbb.com</a></strong> atau <strong><a href="https://imgur.com" target="_blank" class="underline">imgur.com</a></strong> (gratis, tanpa daftar), lalu salin <strong>Direct link</strong>-nya.',
                'Klik tombol <strong>Preview</strong> untuk memastikan foto berhasil dimuat.',
                'Klik <strong>Simpan Perubahan</strong> untuk menyimpan.',
                'Foto profil Anda akan langsung diperbarui di seluruh aplikasi.',
            ],
        ],
        [
            'id'    => 8,
            'icon'  => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z',
            'color' => 'text-indigo-600 dark:text-indigo-400',
            'bg'    => 'bg-indigo-100 dark:bg-indigo-900/40',
            'title' => 'Bagaimana caranya chatting dengan developer atau admin?',
            'steps' => [
                'Klik menu <strong>Chatting</strong> di sidebar kiri.',
                'Di panel kiri, akan tampil daftar kontak yang tersedia (Developer, Admin, Supervisor sesuai role Anda).',
                'Klik nama kontak yang ingin Anda ajak bicara.',
                'Ketik pesan di kolom teks di bagian bawah layar.',
                'Tekan <strong>Enter</strong> atau klik tombol kirim <svg class="inline w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> untuk mengirim pesan.',
                'Balasan dari admin atau developer akan muncul di jendela chat yang sama.',
                '<span class="text-blue-500 font-semibold">Tips:</span> Jika ada pesan masuk baru, akan muncul notifikasi di ikon Chatting pada sidebar.',
            ],
        ],
    ];
    @endphp

    @foreach($topics as $topic)
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">

        {{-- Accordion Header --}}
        <button @click="open === {{ $topic['id'] }} ? open = null : open = {{ $topic['id'] }}"
                class="w-full flex items-center justify-between px-5 py-4 text-left
                       hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl {{ $topic['bg'] }} flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 {{ $topic['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $topic['icon'] }}"/>
                    </svg>
                </div>
                <span class="text-sm font-semibold text-slate-800 dark:text-white leading-snug text-left">
                    {{ $topic['title'] }}
                </span>
            </div>
            <svg :class="open === {{ $topic['id'] }} ? 'rotate-180' : ''"
                 class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0 ml-3"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        {{-- Accordion Body --}}
        <div x-show="open === {{ $topic['id'] }}"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-cloak
             class="border-t border-slate-100 dark:border-slate-700 px-5 py-4">
            <ol class="space-y-3">
                @foreach($topic['steps'] as $i => $step)
                <li class="flex items-start gap-3">
                    <span class="shrink-0 w-6 h-6 rounded-full {{ $topic['bg'] }} {{ $topic['color'] }}
                                 flex items-center justify-center text-[11px] font-bold mt-0.5">
                        {{ $i + 1 }}
                    </span>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">{!! $step !!}</p>
                </li>
                @endforeach
            </ol>
        </div>

    </div>
    @endforeach

    {{-- Footer note --}}
    <div class="text-center py-4">
        <p class="text-xs text-slate-400">
            Butuh bantuan lebih lanjut? Hubungi developer atau admin melalui fitur
            <a href="{{ route('chatting') }}" class="text-blue-500 hover:underline font-medium">Chatting</a>.
        </p>
    </div>

</div>
@endsection
