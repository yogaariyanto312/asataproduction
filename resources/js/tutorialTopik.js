// Diambil apa adanya dari tutorial.blade.php referensi (langkah berisi HTML statis).
export const TOPIK = [
    {
        "id": 1,
        "icon": "M12 4v16m8-8H4",
        "tone": "green",
        "title": "Bagaimana caranya input data hasil produksi?",
        "steps": [
            "Klik menu <strong>Input Produksi</strong> di sidebar kiri.",
            "Pilih <strong>Tanggal Produksi</strong> sesuai hari produksi berlangsung.",
            "Pilih <strong>Produk</strong> dari dropdown (Channel, Cover, Tangki, dsb.).",
            "Jika produk bertipe <em>Swasta</em> atau <em>Typetest</em>, pilih <strong>Input Seri & KVA Manual</strong> lalu isi kolom <strong>Nomor Seri</strong> dan <strong>KVA</strong>.",
            "Isi jumlah unit yang diproduksi. Untuk Channel, isi kolom <strong>Channel UP</strong> dan <strong>Channel BT</strong> secara terpisah.",
            "Isi <strong>Nomor Urut</strong> — masukkan nomor awal, sistem akan otomatis mengisi preview range nomor.",
            "Jika ada barang reject, klik <strong>Ada Reject / Defect?</strong> dan isi jumlah serta keterangannya.",
            "Tambahkan <strong>Keterangan</strong> jika diperlukan (opsional).",
            "Klik tombol <strong>Save</strong> untuk menyimpan data."
        ]
    },
    {
        "id": 2,
        "icon": "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2",
        "tone": "blue",
        "title": "Bagaimana caranya melihat riwayat hasil produksi?",
        "steps": [
            "Klik menu <strong>Riwayat Produksi</strong> di sidebar kiri.",
            "Data ditampilkan dalam kartu per hari, dikelompokkan berdasarkan jenis produk (Channel, Cover, Tangki).",
            "Untuk mencari data tertentu, gunakan kolom <strong>Cari Produk</strong> atau filter berdasarkan <strong>Nama Produk</strong>, <strong>Dari Tanggal</strong>, dan <strong>Sampai Tanggal</strong>.",
            "Klik tombol <strong>Filter</strong> untuk menerapkan pencarian, atau <strong>Reset</strong> untuk kembali ke tampilan semua data.",
            "Klik ikon <svg class=\"inline w-3.5 h-3.5 text-blue-500\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z\"/></svg> pada entri tertentu untuk melihat detail lengkap."
        ]
    },
    {
        "id": 3,
        "icon": "M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z",
        "tone": "amber",
        "title": "Bagaimana caranya edit atau hapus data produksi jika terjadi kesalahan?",
        "steps": [
            "Buka menu <strong>Riwayat Produksi</strong>.",
            "Temukan entri yang ingin diubah (gunakan fitur filter jika perlu).",
            "Klik ikon <svg class=\"inline w-3.5 h-3.5 text-amber-500\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z\"/></svg> (pensil) untuk mengedit data.",
            "Ubah data yang salah, lalu klik <strong>Simpan Perubahan</strong>.",
            "Untuk menghapus, klik ikon <svg class=\"inline w-3.5 h-3.5 text-red-500\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16\"/></svg> (tong sampah) pada entri tersebut.",
            "Konfirmasi penghapusan pada dialog yang muncul.",
            "<span class=\"text-red-500 font-semibold\">Perhatian:</span> Data yang dihapus tidak dapat dikembalikan."
        ]
    },
    {
        "id": 4,
        "icon": "M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z",
        "tone": "purple",
        "title": "Bagaimana caranya ganti password?",
        "steps": [
            "Klik foto atau nama profil Anda di pojok kanan atas, lalu pilih <strong>Profil</strong>. Atau langsung buka menu <strong>Profil</strong>.",
            "Gulir ke bawah hingga menemukan bagian <strong>Ganti Password</strong>.",
            "Isi kolom <strong>Password Lama</strong> dengan password yang sedang digunakan.",
            "Isi kolom <strong>Password Baru</strong> dengan password yang diinginkan (minimal 8 karakter).",
            "Isi kolom <strong>Konfirmasi Password Baru</strong> — harus sama persis dengan password baru.",
            "Klik tombol <strong>Simpan Password</strong>.",
            "Password berhasil diubah. Gunakan password baru saat login berikutnya."
        ]
    },
    {
        "id": 5,
        "icon": "M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z",
        "tone": "red",
        "title": "Bagaimana jika saya lupa password?",
        "steps": [
            "Pada halaman login, klik tautan <strong>Lupa Password?</strong>.",
            "Masukkan <strong>alamat email</strong> yang terdaftar di akun Anda.",
            "Klik tombol <strong>Kirim Link Reset</strong>.",
            "Cek email Anda — Anda akan menerima email berisi tautan reset password.",
            "Klik tautan di email tersebut, lalu isi password baru Anda.",
            "Klik <strong>Simpan Password Baru</strong> dan login menggunakan password baru.",
            "<span class=\"text-amber-500 font-semibold\">Catatan:</span> Fitur ini hanya berfungsi jika email sudah ditautkan ke akun Anda. Lihat tutorial berikutnya jika belum."
        ]
    },
    {
        "id": 6,
        "icon": "M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z",
        "tone": "cyan",
        "title": "Bagaimana caranya menautkan email agar bisa reset password via email?",
        "steps": [
            "Buka menu <strong>Profil</strong> dari sidebar atau pojok kanan atas.",
            "Pada bagian <strong>Informasi Akun</strong>, temukan kolom <strong>Email</strong>.",
            "Isi kolom email dengan alamat email aktif yang Anda miliki.",
            "Klik tombol <strong>Simpan Profil</strong>.",
            "Email berhasil ditautkan. Sekarang fitur lupa password sudah bisa digunakan.",
            "<span class=\"text-amber-500 font-semibold\">Pastikan</span> email yang digunakan aktif dan Anda memiliki akses ke kotak masuk email tersebut."
        ]
    },
    {
        "id": 7,
        "icon": "M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16",
        "tone": "emerald",
        "title": "Bagaimana caranya update foto profil?",
        "steps": [
            "Buka menu <strong>Profil</strong> dari sidebar atau klik nama Anda di pojok kanan atas.",
            "Pada bagian <strong>Link Foto Profil</strong>, tempel (paste) link URL gambar Anda.",
            "Upload foto ke <strong><a href=\"https://imgbb.com\" target=\"_blank\" class=\"underline\">imgbb.com</a></strong> atau <strong><a href=\"https://imgur.com\" target=\"_blank\" class=\"underline\">imgur.com</a></strong> (gratis, tanpa daftar), lalu salin <strong>Direct link</strong>-nya.",
            "Klik tombol <strong>Preview</strong> untuk memastikan foto berhasil dimuat.",
            "Klik <strong>Simpan Perubahan</strong> untuk menyimpan.",
            "Foto profil Anda akan langsung diperbarui di seluruh aplikasi."
        ]
    },
    {
        "id": 8,
        "icon": "M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z",
        "tone": "indigo",
        "title": "Bagaimana caranya chatting dengan developer atau admin?",
        "steps": [
            "Klik menu <strong>Chatting</strong> di sidebar kiri.",
            "Di panel kiri, akan tampil daftar kontak yang tersedia (Developer, Admin, Supervisor sesuai role Anda).",
            "Klik nama kontak yang ingin Anda ajak bicara.",
            "Ketik pesan di kolom teks di bagian bawah layar.",
            "Tekan <strong>Enter</strong> atau klik tombol kirim <svg class=\"inline w-3.5 h-3.5\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M12 19l9 2-9-18-9 18 9-2zm0 0v-8\"/></svg> untuk mengirim pesan.",
            "Balasan dari admin atau developer akan muncul di jendela chat yang sama.",
            "<span class=\"text-blue-500 font-semibold\">Tips:</span> Jika ada pesan masuk baru, akan muncul notifikasi di ikon Chatting pada sidebar."
        ]
    }
];
