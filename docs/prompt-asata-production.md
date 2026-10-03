# PROMPT KONTEKS PROYEK — ASATA PRODUCTION SYSTEM

> Tempelkan seluruh dokumen ini ke asisten AI sebelum meminta bantuan apa pun
> tentang proyek ini. Semua isi di bawah diambil langsung dari kode per
> 4 Oktober 2026 (branch `upgrade/laravel-13`). Kalau ada yang bertentangan
> dengan kode terbaru, KODE yang benar — periksa dulu, jangan menebak.

---

## 0. Peran & aturan kerja untuk AI

Kamu membantu mengembangkan **Asata Production System**, aplikasi web internal
pabrik trafo untuk mencatat produksi harian tim QC (Quality Control) bagian
Welding. Aplikasi ini dipakai setiap hari oleh operator di lantai pabrik, jadi
bug langsung mengganggu pekerjaan.

Aturan yang wajib diikuti:

1. **Bahasa**: antarmuka, komentar kode, nama variabel/fungsi baru, dan pesan
   ke pengguna memakai **Bahasa Indonesia** (gaya kode yang sudah ada: `rentangBulan()`,
   `hapusBerkas()`, `nomorUrutTerakhir()`). Pesan commit juga Bahasa Indonesia.
2. **Jangan mengubah logika perhitungan** (bagian 6) tanpa diminta. Semua angka
   sudah disamakan persis dengan aplikasi acuan dan dijaga tes.
3. **Uji sebelum menyatakan selesai**: jalankan dua konfigurasi PHPUnit
   (SQLite `phpunit.xml` dan MySQL `phpunit.verify.xml`) dan periksa di browser
   (PC + HP). Data uji di database wajib dihapus lagi.
4. **Hemat bandwidth**: jaringan pabrik ±10 Mbps dan banyak dibuka dari HP.
   Hindari pustaka besar, gambar penuh di daftar, dan polling yang boros.
5. **Jangan commit/push** kecuali diminta. **Backup database** sebelum mengubah skema.
6. **Jangan menebak kata sandi akun asli.** Untuk uji browser buat akun sementara
   lalu hapus beserta `activity_logs`-nya.
7. Kalau mem-port kode dari aplikasi acuan (Blade), sesuaikan ke pola Inertia
   (lihat jebakan di bagian 11).

---

## 1. Ringkasan produk

- **Nama**: Asata Production System (nama dari `config('app.name')`, ditampilkan `ucwords`).
- **Fungsi**: pencatatan & monitoring produksi trafo — input hasil produksi harian
  (UP/BT untuk Channel, total untuk Cover/Tangki), nomor urut unit, reject,
  target produksi, laporan bulanan/harian (layar, PDF, Excel), gambar kerja
  (dokumen teknik), aksesoris keluar, barang pengganti, catatan/tugas, chat
  internal, kalender agenda, dan bot Telegram/Discord.
- **Aplikasi acuan**: `G:\laragon\www\Production-QC-Logging-System` (Laravel + Blade +
  Alpine). Asata adalah versi baru (React/Inertia) yang **perilaku & tata letaknya
  disamakan dengan acuan**, tetapi **memakai tema gelap asata**. Tes acuan diport ke
  `tests/Feature/Ref/` dan `tests/Unit/Ref/`.
- **Deploy**: server lokal pabrik, `APP_URL=http://192.168.30.242`, aplikasi dilayani
  dari **subfolder** `/asata-production/public` (bukan root domain). Zona waktu
  `Asia/Jakarta`. Tidak ada HTTPS di alamat IP → service worker hanya aktif di
  https/localhost.

---

## 2. Teknologi (versi persis)

**Backend** (composer): PHP `^8.4`, `laravel/framework ^13.17`,
`inertiajs/inertia-laravel ^3.3`, `filament/filament ^5.8` (panel `/admin`),
`barryvdh/laravel-dompdf ^3.1` (PDF), `maatwebsite/excel ^3.1` (Excel),
`tightenco/ziggy ^2.6` (terpasang tapi `@routes` sudah dicabut — URL selalu dikirim
dari server). Dev: `phpunit/phpunit ^12.5`, `laravel/pint`, `mockery`.

**Frontend** (npm): `react ^19.2`, `@inertiajs/react ^3.7`, `vite ^8`,
`@vitejs/plugin-react ^6`, `tailwindcss ^4.3` + `@tailwindcss/vite` (hanya dipakai
`app.css`/halaman Blade lama), `chart.js ^4.5` + `react-chartjs-2 ^5.3`, `axios`.

**Database**: MySQL (produksi: `asataprdct`, uji MySQL: `asataprdct_verify`),
SQLite in-memory untuk `phpunit.xml`.

**PHP CLI**: `G:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe` (php di PATH
masih 8.3 dan ditolak composer).

**Penting soal build**: `vite.config.js` memakai `base: './'` saat build karena
aplikasi di subfolder; halaman dimuat lazy lewat
`import.meta.glob('./Pages/**/*.jsx')` di `resources/js/inertia.jsx`. Tanpa
`base: './'` chunk lazy 404.

---

## 3. Struktur folder

```
app/
  Exports/            AccessoryExport, ProductionReportExport (Excel)
  Filament/           Pages/Dashboard + Widgets (panel /admin, transisi — lihat §9)
  Http/
    Controllers/      Accessory, Admin, Auth/{Login,ForgotPassword}, BotSetting,
                      CalendarEvent, Category, Dashboard, Department, Developer,
                      GambarKerja, Management, Mandor, Message, Note, Operator,
                      Permission, Product, ProductionLog, ProductionTarget,
                      Profile, Replacement, Report, Supervisor, TelegramWebhook, Visitor
    Middleware/       EnforceMenuAccess, HandleInertiaRequests, RoleMiddleware,
                      SecurityHeaders, AuthenticateFilamentPanel
    Requests/         ProductionLogRequest, ProductRequest, CategoryRequest
  Models/             Accessory, ActivityLog, BotSetting, CalendarEvent, Category,
                      Changelog, Department, DepartmentMenuPermission, GambarKerja,
                      Message, Note, NoteCompletion, Product, ProductionLog,
                      ProductionTarget, Replacement, RoleMenuPermission,
                      SchedulePhoto, User
    Concerns/BelongsToDepartment.php   (trait: scope + auto isi department)
    Scopes/DepartmentScope.php
  Services/
    BotNotificationService.php  (notifikasi aktivitas, alert reject, target tercapai,
                                 laporan harian, klien HTTP terpusat)
    HolidayService.php          (libur nasional dari Google Calendar API, cache)
    Telegram/                   Berkas, BotPerintah, Panduan, Pelaporan,
                                Pemberitahuan, PenautanAkun, Pengirim, Penyimpanan,
                                Ringkasan, Tanggal, Tautan, Teks
  Support/
    AksesBerkas.php     (izin baca berkas per folder)
    FaseBulan.php       (purnama/bulan baru, algoritma Meeus, tanpa API)
    HtmlCatatan.php     (penyaring HTML isi catatan)
    ImageThumbnail.php  (turunan gambar kecil)
    MenuAccess.php      (hak akses menu & aksi)
    NomorUrut.php       (rentang nomor urut sebulan)
    UrutanProduksi.php  (urutan baku Channel→Cover→Tangki, KVA, seri)
config/menus.php      SUMBER TUNGGAL menu sidebar + hak akses (lihat §5)
resources/
  css/app.css         Tailwind v4 (Blade lama) + .no-scrollbar
  css/asata-ui.css    seluruh desain halaman React (prefix .au-)
  css/login-robot.css tema halaman login (ilustrasi robot)
  js/inertia.jsx      entry React (lazy pages, pantau CSRF)
  js/Layouts/AppLayout.jsx   kerangka: sidebar, header, notifikasi
  js/Components/      Ui.jsx (Btn, Card, Field, Input, Select, Textarea, Pagination,
                      DeleteButton, IconBtn, Icon, ICON), Calendar.jsx, PdfViewer.jsx
  js/Pages/           About, Accessories/Index, Auth/*, Categories/*, Chat/{Index,Admin},
                      Dashboard, Departments/Form, GambarKerja/{Index,Group,Create},
                      Management/Index, Notes/Index, Permissions/Index,
                      Production/{Form,Index,Show,Targets}, Products/{Index,Form,Show,Ukuran},
                      Profile/Edit, Replacements/Index, Reports/{Index,Daily},
                      Settings/Bot, SystemCheck, Tutorial, Users/Form
  js/dialog.js        konfirmasi()/peringatan() — modal pengganti window.confirm
  js/csrf.js          token CSRF + penyegaran otomatis (/csrf-token)
  js/offlineFiles.js  unduh-semua gambar kerja ke Cache Storage
  js/tutorialTopik.js 8 topik tanya-jawab Tutorial
  views/inertia.blade.php   root view React
  views/reports/{pdf,daily-pdf}.blade.php   template DomPDF
public/panduan/       panduan interaktif statis (index.html, panduan.css, panduan.js)
public/vendor/pdfjs/  PDF.js lokal (pdf.min.js, worker, cmaps, standard_fonts, qc-pdf-viewer.js)
public/sw.js          service worker cache berkas gambar kerja
routes/web.php        semua route; routes/console.php jadwal tugas
tests/Feature, tests/Feature/Ref, tests/Unit/Ref
```

---

## 4. Peran (role) & departemen

Enum `users.role`: `developer`, `admin`, `supervisor`, `mandor`, `operator`, `visitor`.

| Peran | Keterangan (konstanta `ManagementController::PERAN`) | Warna lencana |
|---|---|---|
| Developer | Akses penuh ke seluruh sistem | biru |
| Admin | Mengelola operator, visitor, dan data produksi | ungu |
| Supervisor | Memantau laporan dan data produksi | teal |
| Mandor | Mengawasi pekerjaan di lapangan | violet |
| Operator | Mencatat hasil produksi harian | sky |
| Visitor | Hanya bisa melihat, tanpa mengubah data | emerald |

Helper di `User`: `isDeveloper/isAdmin/isSupervisor/isMandor/isOperator/isVisitor`,
`isPrivileged()` = developer **atau** admin **atau** supervisor.

**Departemen** (`users.department`, tabel `departments`): data `production_logs`,
`accessories`, `replacements` memakai trait `BelongsToDepartment`:
- `DepartmentScope`: developer & request tanpa login → tidak disaring; role lain
  hanya melihat departemennya; user tanpa departemen hanya melihat baris
  `department IS NULL`.
- Saat `creating`, kolom `department` diisi dari user login bila kosong.
- Produk, kategori, gambar kerja, target **tidak** per departemen (org-wide).
- Developer bisa memilih departemen tujuan saat input produksi dan menyaring
  per departemen di Riwayat/Laporan/Aksesoris/Pengganti.

**Login**: field `identifier` (email jika berisi `@`, selain itu `username`
atau `name`), maks 5 gagal per identifier+IP lalu blokir 60 detik, akun
`is_active=false` ditolak. Lupa password via email (token reset).
Middleware web (urutan): `AuthenticateSession` (dukung "logout perangkat lain"),
`HandleInertiaRequests`, `MaintenanceMode`, `EnforceMenuAccess`; global `SecurityHeaders`.

---

## 5. Hak akses menu (`config/menus.php` + `App\Support\MenuAccess`)

Setiap menu punya `key`, `label`, `route`, `default_roles`, `match` (pola nama
route), opsional `actions` (aksi granular), `manageable`, `locked`, `group`,
`dept_shared`.

Aturan `MenuAccess::can($user, $key)`:
1. Developer selalu boleh.
2. Selain itu **role DAN departemen** harus mengizinkan.
3. Izin role: baris di `role_menu_permissions (role, menu_key, allowed)` bila ada,
   kalau tidak pakai `default_roles`.
4. Izin departemen: baris di `department_menu_permissions` bila ada, kalau tidak
   **boleh**; user tanpa departemen tidak dibatasi.
5. **Aksi mewarisi menu induk**: mematikan menu "lihat" ikut mematikan semua aksinya.
6. `EnforceMenuAccess` memetakan nama route → key (aksi diutamakan) dan menolak 403.
7. Menu `locked` (Hak Akses Menu, Settings) tampil di UI tapi tidak bisa diubah.
   Dashboard `manageable => 'actions'`: halamannya tidak bisa dimatikan, hanya aksinya.

Daftar menu (urutan sidebar) beserta bawaan:

| Key | Label | Bawaan role | Aksi (bawaan) |
|---|---|---|---|
| dashboard | Dashboard | semua | dashboard.agenda (semua), dashboard.aktivitas (developer, admin) |
| notes | Catatan | semua kecuali visitor | create/edit/delete (sama) |
| targets | Target Produksi | semua kecuali visitor | targets.edit, targets.delete (dev, admin, supervisor, mandor) |
| chatting | Chatting | semua | chatting.send, chatting.delete (semua), chatting.inbox (dev, admin, spv, mandor) |
| gambar-kerja | Gambar Kerja | semua | upload/edit/delete (dev, admin) |
| input-produksi | Input Produksi | dev, admin, operator | input-produksi.create |
| riwayat-produksi | Riwayat Produksi | semua | edit, delete, reject (dev, admin, operator) |
| barang-pengganti | Barang Pengganti | semua kecuali visitor | create, edit (=Tandai Selesai), delete (dev, admin, operator) |
| aksesoris | Aksesoris Keluar | semua kecuali visitor | create, edit, delete, export (dev, admin, operator) |
| master-produk | Master Produk | dev, admin (dept_shared) | create/edit/delete (dev, admin) |
| laporan | Laporan | dev, admin, supervisor, mandor | laporan.export |
| kategori | Kategori | developer (dept_shared) | create/edit/delete (developer) |
| manajemen | Manajemen | dev, admin | create/edit/delete pengguna |
| tutorial | Tutorial | semua | tutorial.edit = ganti video (developer) |
| about | Tentang Aplikasi | semua | about.edit (developer) |
| permissions | Hak Akses Menu | developer (LOCKED) | — |
| settings | Settings | developer (LOCKED) | — |

Menu dengan `group => 'produksi'` (Input, Riwayat, Pengganti, Aksesoris, Master
Produk, Kategori) **untuk developer** dirender sebagai dropdown **"QC-Welding"**;
role lain melihatnya rata.

Halaman **Hak Akses Menu**: matriks role × menu/aksi dengan saklar, pencarian,
tombol "semua" per baris, "Semua"/"Bawaan" per kolom, penanda titik bila beda dari
bawaan, aksi meredup bila menu induk mati, bilah simpan lengket; mode per
departemen; disimpan dengan upsert dan tercatat rinci di log aktivitas.

---

## 6. LOGIKA PERHITUNGAN (JANGAN DIUBAH)

### 6.1 Produk & kategori
- `products.type`: `regular` atau `channel`.
- `Product::series_with_kva` = `"{series} · {kva} KVA"` (atau seri saja; kosong bila tak ada seri).
- `categories.has_manual_serial = true` → kategori "seri manual" (Swasta/Typetest):
  produk tanpa `series` di kategori itu adalah **placeholder** yang di dropdown
  berlabel `Seri & KVA Manual {CH|CV|TK} → {PLN|Swasta|TypeTest}`.
- Penanda PLN/Swasta/Typetest dibaca dari **nama kategori** (mengandung `swasta`,
  `type`, atau `pln/channel/cover/tangki`).
- Dropdown produk urut: `name`, lalu `CAST(kva AS UNSIGNED)`, lalu `series`.

### 6.2 Input produksi (`ProductionLogController@store`)
Validasi (`ProductionLogRequest`): `product_id` wajib & ada; `production_date`
wajib, ≤ hari ini; `up_qty`, `bt_qty`, `reject_qty` integer 0–9999 (kosong → 0);
`total_qty` wajib 0–99999; `notes` ≤500; `manual_series` ≤100; `manual_kva` ≤50;
`keterangan` ≤500; `reject_category` ∈ {material, mesin, human_error, desain,
lainnya} (label: Bahan Baku, Mesin / Alat, Human Error, Desain / Spesifikasi, Lainnya);
`reject_notes` ≤300.

Urutan proses:
1. `user_id`, `operator_name` = user login; departemen = pilihan developer (wajib
   bila ada departemen aktif) atau departemen user.
2. **Anti klik ganda**: kiriman identik (user, produk, tanggal, total, up, bt,
   seri manual, notes) dalam **10 detik** diabaikan (cache).
3. **Seri manual**: bila kategori seri manual & `manual_series` diisi →
   `Product::firstOrCreate(category, series, kva)` dengan `tahun = 2000 + 2 digit
   awal seri` (mis. `26xxxx` → 2026); `product_id` diganti produk spesifik itu.
4. **Total**:
   - **Channel**: `total_qty = (up_qty + bt_qty) / 2` (desimal .5 dimungkinkan;
     kolom `decimal(8,1)`).
   - **Lainnya**: `total_qty` diisi manual; `up_qty`/`bt_qty` = 0.
5. **Gabung (merge)**: jika sudah ada entri **produk sama + tanggal sama +
   departemen sama** → TIDAK membuat baris baru:
   - Channel: UP & BT dijumlah, total dihitung ulang `(UP+BT)/2`.
   - Lainnya: `total_qty` dijumlah.
   - `notes` (nomor urut) digabung via `mergeSerialNotes` (lihat 6.3).
   - Reject dijumlah; kategori diganti bila diisi; catatan reject disambung `"; "`
     (maks 300). `keterangan` disambung `"; "`.
   - Pesan: "Ditambahkan ke entri yang ada. Total sekarang: N unit."
6. Entri baru: `notes` dibersihkan oleh `nomorUrutSesuaiJumlah`:
   total ≤ 0 → nomor urut dikosongkan; channel → baris `UP ...` dibuang bila UP = 0,
   baris `BT ...` dibuang bila BT = 0.
7. Setelah respons terkirim (`afterResponse`): cek **alert reject** dan **target tercapai**.

**Edit** (`update`): total channel dihitung ulang `(UP+BT)/2`; `nomorUrutSesuaiJumlah`
diterapkan; tanpa merge. **Hapus**: hapus baris; kembali ke URL daftar sebelumnya
(filter tetap) kecuali asal dari halaman detail → ke daftar utama.

### 6.3 Nomor urut (kolom `production_logs.notes`)
- Format: `NO.001-010` (padding **minimal 3 digit**), channel per baris:
  `UP NO.435-437` dan `BT NO.438-440`.
- Di form: "Nomor Awal" + jumlah → rentang. Regular: jumlah = `ceil(total)`;
  qty 1 tetap `NO.015-015` di form produksi. Channel: rentang UP dari jumlah UP,
  BT dari jumlah BT.
- Petunjuk "Entri terakhir" dari `GET /api/production/last-serial?product_id=`:
  catatan terakhir produk itu; untuk channel baris UP dan BT terakhir diambil
  **terpisah** (bisa dari entri berbeda). Nomor Awal otomatis = nomor akhir + 1.
- `mergeSerialNotes`: baris unik, dikelompokkan per awalan (`""`, `UP `, `BT `),
  rentang yang bersambung/tumpang tindih disatukan (`473-482` + `483-492` →
  `473-492`), padding dipertahankan; baris non-format disimpan apa adanya.
- `NomorUrut::rentang()` (laporan): dari semua catatan sebulan, ambil nomor
  **terkecil–terbesar** per awalan; urutan awalan `""`, `UP`, `BT`; padding min 3;
  bila tak ada yang berformat → tampil apa adanya.

### 6.4 Reject unit (`POST /production/{id}/reject-unit`)
Untuk unit yang ditemukan cacat setelah dicatat. **Tidak untuk Channel** (422).
- Validasi: `jumlah` 1..floor(total entri); `tanggal` ditemukan ≥ tanggal produksi
  dan ≤ hari ini; kategori & catatan opsional.
- Dalam transaksi (lockForUpdate):
  - Entri asal: `total -= jumlah`; **nomor paling akhir dilepas** (`lepasNomorTerakhir`:
    `NO.023-027` dikurangi 2 → sisa `NO.023-025`, dilepas `NO.026-027`); bila total
    jadi 0 → notes null.
  - Reject dicatat di **hari ditemukan**: sama hari → entri asal; beda hari →
    digabung ke entri produk sama + tanggal itu + **departemen entri asal**, atau
    dibuat entri baru (total 0).
  - `keterangan` ditambah rujukan: `Reject N unit NO.xxx dari produksi dd/mm/YYYY`.

### 6.5 Riwayat Produksi (`/production`)
- Filter: cari (nama/seri produk atau nomor urut), nama produk, rentang tanggal,
  bulan, tahun, departemen (developer). Pencarian langsung (reload parsial).
- **Paginasi per TANGGAL**: 16 tanggal per halaman (satu tanggal tak terpotong).
- Pengelompokan: per tanggal (nama hari, tanggal, total, jumlah entri) → per
  kategori via `UrutanProduksi::kelompokkan` → kartu entri.
- Ringkasan hari ini: total UP, total BT, total Tangki, total Cover, grand total, jumlah entri.
- Nama produk ditampilkan tanpa akhiran `typetest|swasta|pln`.
- Channel tanpa nomor UP/BT di catatannya memakai nomor terakhir produk sebagai cadangan.

### 6.6 Urutan baku (`UrutanProduksi`)
Label kategori dari kata kunci: `channel`→Channel, `cover`→Cover, `tangki`→Tangki,
lain → nama asli/"Lainnya". Prioritas Channel(0) → Cover(1) → Tangki(2) → sisanya
(99, abjad). Di dalam kategori: **KVA terkecil** (numerik), lalu **seri** (strcmp).
Dipakai sama di Riwayat, Laporan layar, PDF, dan Excel.

### 6.7 Laporan bulanan (`ReportController::rekapBulanan`)
- Periode: bulan 1–12, tahun 2000–2100 (di-clamp); rentang
  `[tgl 1, tgl 1 bulan depan)` (bukan MONTH()/YEAR()).
- Per produk: `SUM(up_qty)`, `SUM(bt_qty)`, `SUM(total_qty)`, nomor urut sebulan
  (`NomorUrut::rentang`, catatan urut tanggal lalu created_at).
- Ringkasan: total UP, total BT, total Tangki, total Cover, grand total, jumlah produk.
- Dikelompokkan per kategori, tiap kelompok punya subtotal.
- Export PDF (DomPDF, A4 landscape) & Excel (tabel per kategori) memakai fungsi yang sama.
- **Laporan Harian**: semua entri satu tanggal (urutan baku), kartu UP/BT/Grand
  Total, tombol Export PDF & Print (CSS cetak A4 landscape, header tabel diulang).

### 6.8 Target produksi
- **Satu target aktif per produk** (unique `product_id`), tanpa batas waktu.
- Saat dibuat/diubah: `baseline_qty` = **total produksi kumulatif produk saat itu,
  lintas departemen**; `reached_at` direset null.
- **Aktual** = `max(0, kumulatif_sekarang − baseline)` (lintas departemen).
- Total keseluruhan: `Σ target` dan `Σ min(aktual, target)` (di-**cap per produk**
  supaya over-produksi tak menutupi produk lain); persen = `min(round(aktual/target·100), 100)`.
- Daftar: yang belum tercapai di atas.
- Tercapai → `reached_at` diisi atomik (cegah notifikasi ganda) + notifikasi bot;
  target **dihapus otomatis 2 jam setelah tercapai** (jadwal tiap 30 menit).
- Foto/PDF **jadwal mingguan** (`schedule_photos`, kunci = tanggal **Senin**);
  semua dihapus tiap Senin 01:00 WIB.

### 6.9 Dashboard
- Statistik: total unit hari ini (+ jumlah entri), total bulan ini, jumlah produk aktif.
- **Reject hari ini**: `reject%` = `reject / (total + reject) × 100` (1 desimal).
  Label "⚠ High Alert" bila reject > 0, "✓ All Good" bila 0.
- Tren 7 hari: bar total unit + garis rata-rata/input (`total/entri`, 1 desimal);
  tooltip jumlah input & seri terbanyak hari itu; kotak "Seri terbanyak hari ini".
- Produk per tipe bulan ini: dikelompokkan dari **kata pertama nama produk**
  (huruf besar), doughnut ungu + legenda persen.
- Top 5 operator hari ini & bulan ini (berdasar `SUM(total_qty)` per `operator_name`).
- Polling statistik tiap **60 detik** (tombol Live/Paused, disimpan di localStorage
  `dashAutoRefresh`).
- Log Aktivitas: 100 baris terbaru (urut `id` turun), 12 terlihat lalu gulir,
  filter Tambah/Ubah/Hapus/Masuk(+Keluar), pemisah "Hari ini"/"Kemarin"/tanggal,
  disegarkan tiap 20 detik dari `/api/dashboard-aktivitas` (izin `dashboard.aktivitas`).

### 6.10 Alert & laporan bot
- **Alert reject** (setelah simpan produksi): per produk per tanggal, rate =
  `reject / total × 100` (catatan: rumus ini BERBEDA dari kartu dashboard), dikirim
  bila ≥ `bot_settings.reject_threshold` (bawaan 5%), maksimal sekali per produk per
  hari (cache sampai akhir hari).
- **Laporan harian** otomatis pukul **22:00 WIB** bila `report_enabled`; rate reject
  = `reject/(total+reject)`; emoji 🔴 > 5%, 🟡 > 2%, 🟢 selain itu.

### 6.11 Aksesoris keluar
- Satuan hanya `pcs` atau `unit` (dinormalkan huruf kecil). Validasi: tanggal wajib,
  nama ≤150, qty ≥1, produk opsional, nomor urut ≤150, keterangan ≤1000.
- Nomor urut: "Nomor Awal" + qty → `NO.015` (qty 1) atau `NO.015-017` (padding 3).
- **Saran lanjut**: nomor terakhir per **(nama aksesoris | seri produk)** — seri, bukan
  product_id; diambil dengan `ROW_NUMBER()` di DB; untuk non-developer hanya dari
  departemennya. Nomor Awal otomatis = terakhir + 1.
- Dropdown produk: grup "Nomor Seri" (produk kategori seri manual, digabung unik per
  seri+KVA) + grup per nama produk.
- Export Excel 9 kolom: Tanggal, Aksesoris, Seri Terkait, KVA, No. Urut, Jumlah,
  Satuan, Keterangan, Diinput (mengikuti filter aktif).

### 6.12 Barang pengganti
Field: produk, tanggal, qty ≥1, penerima, alasan, nomor seri asal, keterangan;
status selesai (`completed_at`) bisa di-toggle. Filter cari/bulan/tahun/departemen.

---

## 7. Fitur per menu (perilaku & tampilan)

> Tata letak mengikuti aplikasi acuan (Blade), warna mengikuti tema asata (§8).

- **Login**: halaman bertema ilustrasi robot (`login-robot.css`), pesan peringatan
  sesi kedaluwarsa (`flash.warning`).
- **Dashboard**: baris statistik + kartu "Aksi Cepat" biru (Input Produksi bila bukan
  visitor/supervisor, Laporan Harian bila privileged; disembunyikan untuk visitor) →
  Target Aktif | Reject Hari Ini → Trend Produksi (2/3) | Produk per Tipe →
  Top Operator hari ini | bulan ini → Catatan | Kalender → Input Produksi Terbaru
  (per kategori, UP/BT untuk channel) → Log Aktivitas (konsol gelap) → tombol Live.
  - Kartu Catatan: 5 catatan terbaru milik/ditujukan ke user, baris bisa dibentang
    (animasi max-height), tombol "Lihat detail" → modal (judul, tenggat, tujuan,
    isi berformat, foto).
  - Kalender: pindah bulan (JSON `/api/dashboard-calendar?month=Y-m`, rentang ±5
    tahun, 422 di luar itu), "Hari ini" muncul saat di bulan lain, titik merah = libur
    nasional, kuning = agenda, putih = purnama, gelap = bulan baru; tooltip rata kiri
    untuk Sen/Sel, rata kanan untuk Sab/Min; klik tanggal → modal agenda (tambah/hapus,
    agenda pribadi milik user).
- **Catatan**: grid kartu berpita warna (8 warna: blue, green, teal, purple, amber,
  red, yellow, slate), cari + saring (Semua/Aktif/Selesai/Hari Ini/Terlambat),
  ringkasan jumlah. Modal 2 kolom (lebar 672px HP / 1024px PC): judul, kirim ke
  (pribadi / semua user / user tertentu), deadline (+label "Besok", "3 hari lagi",
  "Terlambat N hari"), tandai selesai, editor berformat (tebal, miring, garis bawah,
  coret, daftar, menjorok, rata kiri/tengah/kanan, bersihkan format; tempel = teks
  polos), warna label, foto bukti (upload/kamera, maks 5 MB, dikecilkan di server).
  - Isi disaring `HtmlCatatan` (tag boleh: p, br, div, strong, b, em, i, u, s, strike,
    ul, ol, li, blockquote; hanya style text-align); batas 5000 karakter **teks**.
  - Penerima hanya bisa menandai selesai; catatan broadcast punya status selesai
    **per orang** (`note_completions`) dan pemilik melihat "N selesai".
  - `due_date` diserialkan `Y-m-d` (cast `date:Y-m-d`) — jangan diubah ke `date`,
    deadline akan mundur sehari di Asia/Jakarta.
  - Catatan selesai dihapus otomatis **8 jam** setelah ditandai selesai (tiap jam).
- **Target Produksi**: form set target (produk / seri manual + KVA, jumlah, catatan),
  kartu progres total & per produk (polling `/api/production/targets-live`), foto jadwal
  minggu ini (upload/hapus).
- **Chatting**: chat langsung per kontak, status online (`last_seen_at` via ping),
  indikator mengetik (8 detik), tandai dibaca per percakapan, hapus percakapan,
  notifikasi toast + badge belum dibaca (poll 30 detik di AppLayout). Kotak masuk
  admin (`chatting.inbox`). Kontak yang terlihat bergantung peran (developer: semua;
  admin: semua kecuali admin; supervisor/mandor: dev, admin, spv, mandor, operator;
  operator: dev, admin, spv, mandor; visitor: dev & admin). Pesan dihapus otomatis
  setelah 7 hari (02:00 WIB).
- **Gambar Kerja**: dokumen dikelompokkan per (judul, seri, kva, tahun) → seksi per
  tahun → sub-seksi Standar/PLN, Seri Swasta (amber), Seri Typetest (ungu). Kartu
  bergambar (thumbnail kecil dari `/thumb/{path}`), urutkan Seri terkecil/terbesar,
  KVA terkecil/terbesar, Judul A–Z (nilai seri = 1–4 digit terakhir sebelum "-";
  KVA dari kolom atau pola "NNN KVA"), cari langsung, penyegaran otomatis bila ada
  unggahan baru (`/api/gambar-kerja/poll` tiap 20 detik), tombol **Unduh Semua**
  (simpan di perangkat lewat service worker; hanya https/localhost).
  - Halaman grup: info grup, edit judul/keterangan/kategori, thumbnail grup (dikompres),
    daftar berkas (gambar bisa diperbesar; **PDF di HP digambar PDF.js** di kanvas,
    di PC iframe; berkas diambil sekali via fetch lalu blob), unduh untuk non-visitor,
    hapus berkas (nomor urut diatur ulang; berkas terakhir ikut menghapus thumbnail).
  - Upload: judul (penghitung /46), seri + KVA (tahun otomatis dari 2 digit awal
    seri, pratinjau `seri(kva)`), kategori PLN/Swasta/Typetest, zona seret banyak
    berkas (JPG/PNG/PDF maks 100 MB/berkas), keterangan (penghitung /18 kata),
    **overlay progres nyata XHR** (persen, MB, laju, sisa waktu, coba lagi; 413/419
    ditangani).
- **Input Produksi**: form satu halaman — tanggal, departemen (developer), produk
  berkelompok, seri & KVA manual bila placeholder, UP/BT (channel, total otomatis)
  atau total besar, nomor awal + pratinjau rentang + petunjuk entri terakhir,
  reject (jumlah, kategori, catatan), keterangan. Simpan tanpa pindah halaman.
- **Riwayat Produksi**: lihat 6.5; aksi lihat/edit/hapus/reject unit per entri.
- **Barang Pengganti**, **Aksesoris Keluar**: filter + tabel di PC, **kartu di HP**
  (`< 1024px`), modal tambah/edit.
- **Master Produk**: CRUD produk, aktif/nonaktif, detail produk (data produk + daftar
  produksi **bulan berjalan**: tanggal, operator, UP/BT, total),
  halaman **Ukuran** (panjang × lebar per produk).
- **Kategori**: CRUD (developer), flag `has_manual_serial`.
- **Laporan**: lihat 6.7.
- **Manajemen**: satu halaman bertab per peran (jumlah mengikuti pencarian),
  departemen (tab khusus developer). Developer mengelola semua peran; admin hanya
  operator/supervisor/mandor/visitor; tab developer hanya untuk developer; tak bisa
  menghapus diri sendiri.
- **Tutorial**: kepala biru, iframe panduan interaktif `public/panduan/index.html`
  (+ "Buka di tab baru"), tab Video hanya bila URL diisi (izin `tutorial.edit`),
  8 topik tanya-jawab yang bisa dibentang.
- **Tentang Aplikasi**: versi = entri Riwayat Update terbaru yang punya versi
  (`Changelog::versiAplikasi()`, tambah "v" bila belum, bawaan `v1.0`), Tentang Sistem
  + fitur, kartu pengembang (foto, bio, jumlah repo GitHub via API publik, versi,
  tahun, tautan), portfolio iframe, teknologi, Riwayat Update (6 entri terlihat lalu
  gulir; form tambah & hapus untuk developer).
- **Hak Akses Menu**: lihat §5.
- **Settings** (developer): **satu form** — Telegram (token, chat ID log, chat ID
  laporan, uji), Discord (webhook, uji), Laporan & Alert (batas reject %, kirim
  sekarang), Maintenance (pesan, selesai otomatis), Keamanan (blokir DevTools/klik
  kanan untuk non-developer); tiap saklar = hidden `0` + checkbox `1`; controller
  hanya menyentuh field yang dikirim; bilah simpan melayang dengan penanda "ada
  perubahan" + Batalkan. Lalu Perintah Bot (daftar dari `BotPerintah::daftar()`,
  daftarkan webhook dengan secret token, cek status), contoh format pesan, profil
  di halaman Tentang (handle, bio, tautan, foto).
- **Profil**: ubah nama, email, password (min 6, wajib password lama), avatar berupa
  **URL gambar** (bukan unggah), logout perangkat lain,
  kartu tautkan Telegram (kode sekali pakai; hanya developer/admin/supervisor yang bisa
  membuat kode, tautan lama peran lain tetap bisa diputus).

---

## 8. Desain

**Prinsip**: tata letak mengikuti aplikasi acuan; **warna tema gelap asata**.
Semua kelas React berprefix `.au-` di `resources/css/asata-ui.css` (tidak memakai
utilitas Tailwind di JSX).

Token warna (`.au-app`):
| Token | Nilai | Pakai |
|---|---|---|
| --au-paper | #0f172a | latar halaman (slate-900) + pola titik radial 24px |
| --au-shell | #1e293b | kartu, sidebar (slate-800) |
| --au-surface | #172032 | permukaan dalam |
| --au-ink | #334155 | warna GARIS (slate-700) |
| --au-text | #e2e8f0 | teks |
| --au-muted | #94a3b8 | teks redup |
| --au-accent | #2563eb | aksen biru (deep #1d4ed8, link #60a5fa) |
| --au-teal | #10b981 | sukses |
| --au-yellow | #f59e0b | peringatan |
| --au-danger | #ef4444 | bahaya |
| --au-shadow | 4px 5px 0 rgba(0,0,0,.5) | bayangan keras tanpa blur |

Font: **Sora** (utama UI), **Inter** (cadangan/teks Blade), monospace untuk
seri/nomor urut. Muat dari Google Fonts.

Kerangka (`AppLayout.jsx`):
- Sidebar kiri **264px**, tetap di ≥1024px, laci geser di HP (tombol burger +
  overlay). Mode **sidebar kecil 68px** (tombol logo, disimpan di localStorage
  `qc:sidebar-mini`, kelas `sb-mini` dipasang sebelum render) dengan tooltip label.
- Header: judul + subjudul halaman, tanggal & **jam hidup** di kanan.
- Kartu user di bawah sidebar dengan menu Profil Saya/Logout.
- Tombol kembali ke atas, toast flash, modal konfirmasi `dialog.js` (bukan
  `window.confirm`), blokir DevTools bila diaktifkan, footer "© {tahun} Asata Production".
- Kartu: radius 12–16px, border 1px #334155. Tabel di PC, **kartu di HP** untuk daftar
  panjang. Tidak boleh ada gulir horizontal di lebar 390px.
- Cetak: `@media print` menyembunyikan `#sidebar` & `#app-header`, A4 landscape.
- Warna kategori: Channel biru, Cover emerald, Tangki amber; lencana Swasta merah,
  Typetest biru/ungu.

---

## 9. Skema database (tabel aplikasi)

- **users**: id, name, email?, username?, handle?, bio?, link_instagram/github/portfolio?,
  link_email?, role (enum di atas, bawaan operator), department?, avatar?, about_avatar?,
  last_seen_at?, typing_to?, typing_at?, is_active (1), password, remember_token?,
  telegram_user_id? (unik), telegram_linked_at?. Unik: email, username, telegram_user_id.
- **departments**: name (unik), is_active.
- **categories**: name, code? (unik), description?, is_active, has_manual_serial.
- **products**: category_id, type (regular|channel), name, series?, kva? (varchar),
  tahun?, panjang?, lebar?, unit (bawaan "unit"), description?, is_active. Index (name, series).
- **production_logs**: product_id, user_id, department?, operator_name?, production_date,
  up_qty int, bt_qty int, total_qty decimal(8,1), notes? (nomor urut), manual_series?,
  manual_kva?, keterangan?(500), reject_qty, reject_category? (enum), reject_notes?(300),
  status (draft|confirmed, bawaan confirmed). Index production_date, (production_date, product_id), department, reject_qty.
  *(Kolom lama `shift1_qty/shift2_qty` sudah di-rename ke `up_qty/bt_qty`; shift3 & tabel shifts dihapus.)*
- **production_targets**: product_id (unik), target_date?, target_qty, baseline_qty, notes?, reached_at?, created_by.
- **schedule_photos**: target_date (unik, Senin), file_path, uploaded_by?.
- **accessories**: product_id?, user_id, department?, operator_name?, accessory_date, name,
  serial_number?, qty, unit?, recipient?/purpose? (kolom lama, tak dipakai UI), keterangan?.
- **replacements**: product_id, user_id, department?, operator_name?, replacement_date, qty,
  recipient?, reason?, original_serial?, keterangan?, completed_at?.
- **gambar_kerja**: product_id?, judul, seri?, kva?, kategori_seri (pln|swasta|typetest),
  tahun?, file_path, file_type (image|pdf), keterangan?, uploaded_by, urutan,
  is_thumbnail, thumbnail_path? (sama untuk satu grup).
- **notes**: user_id, target_user_id?, is_broadcast, title, content? (HTML tersaring),
  due_date?, color, photo_path?, is_done, done_at?. **note_completions**: (note_id, user_id) unik, done_at.
- **messages**: sender_id, recipient_id?, message, reply?, replied_at?, is_read.
- **calendar_events**: event_date, title(150), description?, user_id?, created_by_name?.
- **activity_logs**: user_id?, action (create|update|delete|login|logout|…), model_type?,
  model_id?, description, ip_address, created_at. `ActivityLog::record()` juga memicu
  notifikasi bot setelah respons.
- **changelogs**: version?, type (feature|fix|improvement|security), title, description?.
- **bot_settings** (satu baris, `BotSetting::instance()` di-cache per request,
  `lupakan()` untuk tes): telegram_token?, telegram_chat_id?, telegram_report_chat_id?,
  telegram_enabled, discord_webhook?, discord_enabled, reject_threshold (5.00),
  report_enabled, tutorial_iframe_url?, disable_devtools, maintenance_mode,
  maintenance_message?, maintenance_until?.
- **role_menu_permissions** (role, menu_key) unik + allowed; **department_menu_permissions** (department, menu_key) unik + allowed.

---

## 10. Route penting

Semua route berlabel nama; React tidak pernah menulis URL literal (subfolder!) —
URL dikirim lewat props/shared props (`logoutUrl`, `profileUrl`, `notifUrl`, `chatUrl`,
`notesUrl`, `menu`, `routeName`, `flash`, `auth.user`, `disableDevtools`, `appName`).

- Auth: `GET/POST /login`, `POST /logout`, `/forgot-password`, `/reset-password/{token}`, `GET /csrf-token`.
- Dashboard: `/dashboard`, `/api/dashboard-live`, `/api/dashboard-calendar?month=`, `/api/dashboard-aktivitas`,
  `/api/calendar-events`, `POST /calendar-events`, `DELETE /calendar-events/{id}`.
- Produksi: `/production` (+create, store, show, edit, update, destroy), `/production/{id}/reject-unit`,
  `/api/production/last-serial`, `/api/production/poll`.
- Target: `/production/targets` (+store, destroy, schedule-photo store/destroy), `/api/production/targets-live`, `/api/production/actual-qty`.
- Laporan: `/reports`, `/reports/daily`, `/reports/export-pdf`, `/reports/export-excel`, `/reports/daily-pdf`.
- Gambar kerja: `/gambar-kerja`, `/gambar-kerja/create`, `/gambar-kerja/group` (GET/DELETE),
  `/gambar-kerja/group/{info,kategori,thumbnail}`, `/api/gambar-kerja/{poll,berkas}`.
- Berkas: `GET /file/{path}` (asli) & `GET /thumb/{path}` (turunan kecil) — keduanya
  wajib login, path disaring (`..`, absolut, byte nol → 404), izin per folder
  (`AksesBerkas`) dicek **sebelum** keberadaan berkas, cache `private, max-age=31536000, immutable`.
- Lainnya: `/accessories`, `/replacements`, `/products` (+ `/products/ukuran`, toggle-active),
  `/categories`, `/management`, resource per peran (`/operators`, `/admins`, …, tanpa index/show),
  `/departments`, `/permissions`, `/notes` (+ `/notes/list` JSON), `/chatting`, `/messages…`,
  `/api/notifications`, `/api/ping`, `/api/typing`, `/api/chat-contacts`, `/tutorial`,
  `/about`, `/developer/bot-settings…`, `/developer/system-check`, `/profile…`,
  `POST /telegram/webhook` (tanpa CSRF, wajib header secret).

---

## 11. Integrasi & jebakan teknis

**Bot Telegram** (`app/Services/Telegram`):
- Perintah: `/help`, `/info`, `/tutorial`, `/tautkan KODE`, `/produksi` (ringkasan hari ini),
  `/target`, `/reject`, `/pengganti`, `/jadwal [depan|lalu|tanggal]`,
  `/jadwal upload [minggu]` (unggah foto jadwal → tanggal dibulatkan ke **Senin**),
  `/gambar` (unggah gambar kerja bertahap dengan tombol), `/lapor <seri> <qty> [reject]`,
  `/batal`. Izin tiap perintah memakai key menu yang sama dengan web.
- Hanya akun tertaut peran developer/admin/supervisor yang dikenali untuk perintah
  yang mengubah data. Kode tautan sekali pakai.
- `/lapor` mencatat produksi atas nama akun tertaut, **dengan departemen akun itu**,
  dan menggabung ke entri hari ini di departemen yang sama.
- Webhook memverifikasi `X-Telegram-Bot-Api-Secret-Token` (`TELEGRAM_WEBHOOK_SECRET`),
  `allowed_updates` = message + callback_query. Klien HTTP terpusat
  `BotNotificationService::klien()`; verifikasi TLS lewat `BOT_VERIFY_TLS`.

**Libur nasional**: Google Calendar API (`services.google.calendar_api_key`), cache 24 jam
bila berhasil, **5 menit** bila gagal.

**Service worker** (`public/sw.js`): cache berkas gambar kerja; PDF diambil via `fetch`
lalu ditampilkan sebagai blob (penampil PDF Chrome melewati SW); unduhan bersamaan untuk
path yang sama dikunci supaya `cache.put` tidak bentrok.

**Jebakan yang pernah terjadi — wajib diingat**:
1. Request Inertia membawa `X-Requested-With: XMLHttpRequest` → `$request->ajax()` true.
   Jangan membalas JSON polos ke Inertia: gunakan
   `if (! $request->header('X-Inertia') && ($request->ajax() || ...))`. (`expectsJson()` aman.)
2. Upload berkas dengan progres XHR: kirim header `X-Inertia` + `X-Inertia-Version`
   lalu `router.push({component, url, props})` supaya error validasi tidak hilang.
3. Warna skriptabel Chart.js juga dipanggil tanpa `dataIndex` — selalu jaga indeksnya.
4. Cast `date` berserial UTC tengah malam → mundur sehari; pakai `date:Y-m-d` untuk tanggal murni.
5. `BotSetting`/`MenuAccess` punya cache statis — tes wajib mereset (`tests/TestCase.php` sudah).
6. PHPUnit 12: `@dataProvider` di docblock diabaikan → pakai atribut `#[DataProvider]`.
   Ganti user dalam satu tes → `flushSession()` (AuthenticateSession).
7. Jangan memakai `YEAR()/MONTH()/FIELD()` di query baru yang harus jalan di SQLite —
   pakai rentang tanggal.
8. Hapus `activity_logs` akun uji sebelum menghapus akunnya (kalau tidak muncul sebagai "Sistem").

**Keamanan yang sudah ada**: SecurityHeaders (nosniff, X-Frame-Options SAMEORIGIN,
Referrer-Policy, CSP frame-ancestors/object-src/base-uri, upgrade-insecure-requests hanya
di HTTPS, Permissions-Policy), handler 419 (JSON 419 / ke login dengan peringatan /
kembali dengan input), rate limit login, throttle kode Telegram (10/menit), foto catatan
lewat route berlogin.

**Mode maintenance** (`App\Http\Middleware\MaintenanceMode`, di grup web setelah
`HandleInertiaRequests` dan sebelum `EnforceMenuAccess`): aktif bila
`maintenance_mode` menyala dan `maintenance_until` kosong atau belum lewat. Untuk user
login selain developer: halaman apa pun → komponen `Pages/Maintenance.jsx` (503 untuk
muat penuh, 200 untuk kunjungan Inertia), request JSON/API/AJAX → 503
`{maintenance: true}`. Lolos: login, logout, csrf.token, password.*, telegram.webhook.
Halaman yang masih terbuka memuat ulang saat polling notifikasi menerima 503; layar
maintenance menghitung mundur lalu memuat ulang sendiri (tanpa waktu selesai: cek tiap
60 detik). Developer melihat spanduk "Mode maintenance aktif" (shared prop
`maintenanceAktif`). Tes: `tests/Feature/MaintenanceModeTest.php`. Panel Filament
`/admin` memakai tumpukan middleware sendiri, jadi tidak ikut terkunci.

**Jadwal (`routes/console.php`, butuh `* * * * * php artisan schedule:run`)**:
`jadwal:clear` (Senin 01:00), `catatan:clear-done` (tiap jam, selesai > 8 jam),
`target:clear-reached` (tiap 30 menit, tercapai > 2 jam), `chat:clear-old` (02:00, > 7 hari),
`laporan:harian` (22:00).

---

## 12. Celah yang diketahui / belum selesai

- **Panel Filament `/admin`**: widget dashboard sudah ada (Ringkasan, Tren, Produk per
  Tipe, Operator Teratas, Target, Produksi Terbaru, Catatan, Kalender), tetapi peralihan
  menu & redirect belum dikerjakan. `canAccessPanel` hanya mengecek `is_active`.
- Belum dicek terhadap acuan (tidak punya tes khusus): tampilan Barang Pengganti,
  Master Produk, Kategori, Target Produksi, Chatting.
- Rumus persen reject berbeda antara alert bot (`reject/total`) dan dashboard/laporan
  harian (`reject/(total+reject)`) — sama seperti aplikasi acuan; jangan diseragamkan
  tanpa keputusan pemilik.

---

## 13. Cara verifikasi

```
php artisan test                         # SQLite (phpunit.xml)
vendor/bin/phpunit -c phpunit.verify.xml # MySQL asataprdct_verify
npm run build                            # wajib setelah ubah JSX/CSS
```
Status terakhir: SQLite 537 lulus (4 dilewati), MySQL 499 lulus.
`tests/TestCase.php` menolak `RefreshDatabase` selain SQLite atau database berakhiran
`_verify`. Uji browser memakai puppeteer-core + Chrome (PC 1366px & HP 390px), akun
sementara developer yang dihapus setelah selesai. Jangan menekan **Simpan** di Settings
saat uji (mengirim notifikasi ke grup Telegram/Discord asli) dan jangan mencetak isi
`bot_settings` (berisi token).
