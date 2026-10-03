# Asata Production System

Sistem pencatatan produksi dan quality control berbasis web untuk pabrik trafo. Operator mencatat hasil produksi harian (UP/BT untuk channel, jumlah unit untuk cover/tangki), sementara admin, supervisor, dan mandor memantau target, laporan, dan riwayat — dari PC maupun HP, termasuk lewat bot Telegram.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

---

## Fitur Utama

| Menu | Isi |
|---|---|
| **Dashboard** | Ringkasan hari ini & bulan ini, kalender (hari libur nasional & fase bulan), tren produksi, target aktif, reject, catatan & input terbaru, log aktivitas |
| **Catatan** | Catatan berwarna dengan editor teks, foto/kamera, tenggat, dan penerima (read-only bagi penerima) |
| **Target Produksi** | Foto jadwal mingguan (gambar/PDF), set target per produk (jumlah unit atau sampai no. urut), progres live, hapus otomatis setelah tercapai |
| **Chatting** | Pesan antar pengguna dan kotak masuk admin |
| **Gambar Kerja** | Upload banyak file (JPG/PNG/PDF) per judul-seri-kVA, pratinjau PDF, cache offline |
| **Input & Riwayat Produksi** | Input per produk dengan nomor urut UP/BT, seri & kVA manual, reject unit; riwayat per tanggal & kategori |
| **Barang Pengganti & Aksesoris Keluar** | Pencatatan barang pengganti dan aksesoris keluar beserta ekspor |
| **Master Produk & Kategori** | Produk dikelompokkan per tahun dan nama, urutan & warna kartu bisa diatur, kategori dengan opsi *seri manual* |
| **Laporan** | Rekap harian & bulanan, ekspor PDF dan Excel |
| **Manajemen & Hak Akses** | Kelola pengguna per departemen; hak akses menu & aksi per peran bisa diubah tanpa ubah kode |
| **Bot Telegram** | Lapor produksi, cek jadwal, dan notifikasi lewat Telegram (akun ditautkan dari halaman Profil) |
| **Mode Maintenance** | Saklar dari Settings dengan hitung mundur; developer tetap bisa masuk |
| **Panel `/admin`** | Panel Filament untuk dashboard ringkas |

---

## Teknologi

- **Backend** — Laravel 13, PHP 8.4, Filament 5
- **Frontend** — Inertia.js 3 + React 19, Vite 8, CSS kustom (tema gelap)
- **Database** — MySQL 8 / MariaDB 11 (SQLite untuk pengujian)
- **Ekspor** — barryvdh/laravel-dompdf (PDF), maatwebsite/excel (Excel)
- **PDF viewer** — pdf.js (di `public/vendor/pdfjs`)

---

## Peran Pengguna

Hak akses bawaan di bawah bisa diubah per peran lewat menu **Hak Akses Menu** (`config/menus.php` menjadi daftar menu & aksinya).

| Peran | Gambaran akses bawaan |
|---|---|
| **Developer** | Semua menu, termasuk Kategori, Hak Akses, Settings/bot, Tutorial, dan riwayat update |
| **Admin** | Semua menu operasional: master produk, gambar kerja, manajemen pengguna, laporan |
| **Supervisor** | Pantau produksi, target, dan laporan |
| **Mandor** | Pantau produksi, set target, laporan |
| **Operator** | Input & riwayat produksi, barang pengganti, aksesoris, gambar kerja (lihat) |
| **Visitor** | Lihat dashboard dan menu yang diizinkan |

Data produksi dipisah per **departemen**; selain developer, pengguna hanya melihat data departemennya.

---

## Persyaratan Sistem

- PHP >= 8.4 dengan ekstensi `pdo_mysql`, `gd`, `zip`, `mbstring`, `xml`, `fileinfo`, `intl`, `bcmath`, `exif`
- Composer >= 2
- Node.js >= 20 & npm (hanya untuk build aset)
- MySQL >= 8 atau MariaDB >= 10.6
- Apache/LiteSpeed (`mod_rewrite`) atau Nginx

---

## Instalasi Lokal (Laragon)

```bash
# 1. Letakkan proyek di folder www Laragon, lalu masuk ke foldernya
cd G:\laragon\www\asata-production

# 2. Dependensi
composer install
npm install

# 3. Environment
copy .env.example .env
php artisan key:generate
```

Sesuaikan `.env`:

```env
APP_NAME="Asata Production"
APP_ENV=local
APP_URL=http://localhost/asata-production/public
APP_LOCALE=id
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=asataprdct
DB_USERNAME=root
DB_PASSWORD=

# Inertia DevTools menambah ±250 ms tiap request — nyalakan hanya saat debug
INERTIA_DEVTOOLS_ENABLED=false
```

```bash
# 4. Database (buat dulu database kosong utf8mb4), lalu migrasi
php artisan migrate

# 5. Symlink storage
php artisan storage:link

# 6. Aset frontend
npm run build      # atau: npm run dev (hot reload)
```

**Akun pertama** — seeder bawaan berisi data contoh dan password lemah, jangan dipakai di server. Buat akun developer lewat tinker:

```bash
php artisan tinker
```

```php
App\Models\User::create([
    'name'      => 'Developer',
    'username'  => 'developer',
    'email'     => 'developer@example.com',
    'password'  => bcrypt('ganti-dengan-password-kuat'),
    'role'      => 'developer',
    'is_active' => true,
]);
```

Buka `http://localhost/asata-production/public` lalu login.

---

## Deploy ke Shared Hosting (Hostinger)

Deploy memakai script `deployment/deploy.py` (folder `deployment/` tidak di-commit karena berisi kredensial). Kebutuhan: Python 3 + `paramiko`.

Isi blok berikut di `.env` lokal:

```env
DEPLOY_SSH_HOST=
DEPLOY_SSH_PORT=65002
DEPLOY_SSH_USER=
DEPLOY_SSH_PASS=
DEPLOY_REMOTE_ROOT=/home/USER/domains/DOMAIN/public_html
DEPLOY_REMOTE_PHP=/opt/alt/php84/usr/bin/php
DEPLOY_URL=https://DOMAIN
DEPLOY_DB_DATABASE=
DEPLOY_DB_USERNAME=
DEPLOY_DB_PASSWORD=
```

```bash
python deployment/deploy.py --check       # lihat file yang akan dikirim, server tidak diubah
python deployment/deploy.py               # build → kirim file yang berubah → migrate → cache → uji /login
python deployment/deploy.py --buat-akun   # (sekali) akun developer awal, password acak
python deployment/deploy.py --push-env    # tulis ulang .env server (APP_KEY & rahasia webhook dipertahankan)
```

Yang dilakukan script: build aset lokal (server tanpa Node.js), mengirim hanya file yang berubah dalam satu arsip, mode perawatan selama deploy, `composer install --no-dev` bila perlu, `migrate --force`, `storage:link`, `filament:assets`, `optimize`, lalu uji asap. Script menolak berjalan bila folder tujuan bukan domain asataproduction.

Catatan hosting:

- `.htaccess` di akar memaksa PHP 8.4 untuk domain ini (`<IfModule LiteSpeed>`), karena PHP bawaan akun bisa lebih lama.
- **Cron** (atur di hPanel → Cron Jobs, setiap menit) untuk jadwal otomatis — hapus foto jadwal tiap Senin 01.00 dan hapus catatan selesai:

  ```
  /opt/alt/php84/usr/bin/php /home/USER/domains/DOMAIN/public_html/artisan schedule:run
  ```

- Bot Telegram diatur dari menu **Settings** setelah deploy (butuh HTTPS).

---

## Pengujian

```bash
php artisan test                                   # SQLite (cepat)
php vendor/bin/phpunit -c phpunit.verify.xml       # MySQL, database khusus *_verify
```

`tests/TestCase.php` menolak `RefreshDatabase` pada database selain SQLite atau yang namanya berakhiran `_verify`, supaya data asli tidak terhapus.

---

## Perintah Berguna

```bash
composer run dev            # server + queue + log + vite sekaligus
php artisan optimize:clear  # bersihkan semua cache
php artisan route:list      # daftar route
php artisan jadwal:clear    # hapus semua foto jadwal
php artisan catatan:clear-done
```

> `php artisan migrate:fresh` **menghapus semua data** — backup database dulu.

---

## Lisensi & Ketentuan

- Kode sumber dirilis di bawah **[MIT License](LICENSE)**.
- Penggunaan aplikasi yang berjalan (layanan) tunduk pada **[Ketentuan Layanan](TERMS_OF_SERVICE.md)**.
