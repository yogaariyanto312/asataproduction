# Asata Production System

Sistem pencatatan produksi dan quality control berbasis web untuk pabrik/manufaktur. Dibangun dengan Laravel 12, memungkinkan operator mencatat hasil produksi harian per shift, sementara admin dapat memantau, menganalisis, dan mengekspor laporan.

---

## Fitur Utama

| Fitur | Deskripsi |
|---|---|
| **Input Produksi** | Catat jumlah produksi per shift (Shift 1, 2, 3) per produk setiap harinya |
| **Gambar Kerja** | Upload dan lihat gambar kerja (JPG/PNG/PDF) tiap produk secara berurutan |
| **Ukuran Produk** | Referensi dimensi produk (panjang × lebar) lengkap dengan KVA |
| **Laporan** | Rekap harian & bulanan, export ke PDF dan Excel |
| **Manajemen User** | Role Admin dan Operator dengan hak akses berbeda |
| **Chat Internal** | Operator bisa kirim pesan ke admin, admin bisa membalas |
| **Activity Log** | Riwayat aktivitas seluruh pengguna tercatat otomatis |
| **Dark Mode** | Tampilan gelap/terang tersedia di semua halaman |

---

## Teknologi

- **Backend** — Laravel 12, PHP 8.2
- **Frontend** — Tailwind CSS v4, Alpine.js, Vite
- **Database** — MySQL
- **Export** — barryvdh/laravel-dompdf (PDF), maatwebsite/excel (Excel)

---

## Persyaratan Sistem

- PHP >= 8.2 (dengan ekstensi: `pdo_mysql`, `gd`, `zip`, `mbstring`, `xml`, `fileinfo`)
- Composer >= 2
- Node.js >= 18 & npm
- MySQL >= 8.0
- Web server: Apache (XAMPP) atau Nginx

---

## Instalasi Lokal (XAMPP)

### 1. Clone / Letakkan Proyek

```bash
# Letakkan folder proyek di dalam www (Laragon)
D:\laragon\www\asata-production\
```

### 2. Install Dependensi PHP

```bash
composer install
```

### 3. Install Dependensi Frontend

```bash
npm install
```

### 4. Konfigurasi Environment

```bash
# Salin file contoh .env
copy .env.example .env

# Generate application key
php artisan key:generate
```

Lalu buka file `.env` dan sesuaikan konfigurasi database:

```env
APP_NAME="Asata Production"
APP_URL=http://asata-production.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qc_production_db
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Buat Database

Buka phpMyAdmin (`http://localhost/phpmyadmin`) lalu buat database baru:

```sql
CREATE DATABASE qc_production_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 6. Jalankan Migrasi

```bash
php artisan migrate
```

### 7. Buat Storage Symlink

```bash
php artisan storage:link
```

> **Penting untuk XAMPP di Windows:** Pastikan baris berikut ada di `public/.htaccess` di dalam blok `<IfModule mod_rewrite.c>`:
> ```
> Options +FollowSymLinks
> ```
> Tanpa ini, gambar yang diupload tidak akan tampil.

### 8. Build Asset Frontend

```bash
# Untuk development (hot-reload)
npm run dev

# Untuk production
npm run build
```

### 9. Konfigurasi PHP untuk Upload File Besar

Buka `C:\xampp\php\php.ini` dan ubah nilai berikut:

```ini
post_max_size = 500M
upload_max_filesize = 100M
max_file_uploads = 50
```

Restart Apache setelah menyimpan perubahan.

### 10. Buat Akun Admin Pertama

```bash
php artisan tinker
```

```php
App\Models\User::create([
    'name'     => 'Admin',
    'username' => 'admin',
    'email'    => 'admin@example.com',
    'password' => bcrypt('password'),
    'role'     => 'admin',
    'is_active'=> true,
]);
```

### 11. Akses Aplikasi

Buka browser dan akses:

```
http://asata-production.test
```

Login menggunakan username dan password yang dibuat di langkah sebelumnya.

---

## Instalasi di Shared Hosting

### 1. Upload File

Upload semua isi folder proyek ke direktori hosting (misal: `public_html/qc/`), **kecuali** folder `node_modules`.

### 2. Pindahkan Isi Folder `public`

Pindahkan semua isi folder `public/` ke root domain (`public_html/`) atau ke direktori yang diakses browser.

Lalu buka file `public_html/index.php` dan ubah path-nya:

```php
// Sesuaikan path ke folder proyek
require __DIR__.'/../qc/vendor/autoload.php';
$app = require_once __DIR__.'/../qc/bootstrap/app.php';
```

### 3. Konfigurasi `.env`

Upload dan sesuaikan `.env` dengan kredensial database hosting.

### 4. Jalankan via SSH (jika tersedia)

```bash
cd ~/public_html/qc
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. Build Asset (lakukan lokal, lalu upload)

Karena shared hosting biasanya tidak punya Node.js:

```bash
# Di komputer lokal — sesuaikan APP_URL ke domain hosting dulu di .env
npm run build

# Upload folder public/build/ ke server
```

---

## Struktur Role Pengguna

| Role | Hak Akses |
|---|---|
| **Admin** | Semua fitur: manajemen user, produk, kategori, departemen, laporan, upload gambar kerja, hapus data |
| **Operator** | Input produksi, lihat gambar kerja, lihat ukuran produk, kirim pesan ke admin |

---

## Perintah Berguna

```bash
# Jalankan semua sekaligus (server + queue + log + vite)
composer run dev

# Jalankan migrasi ulang (hati-hati: menghapus semua data)
php artisan migrate:fresh

# Bersihkan cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Lihat semua route
php artisan route:list
```

---

## Lisensi

Proyek ini untuk keperluan internal. Tidak untuk didistribusikan ulang tanpa izin.
