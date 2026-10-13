<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProduksiController;
use App\Http\Controllers\Api\ProfilController;
use App\Http\Controllers\Api\TargetController;
use App\Http\Controllers\AccessoryController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BotSettingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DeveloperController;
use App\Http\Controllers\GambarKerjaController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\MandorController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReplacementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\VisitorController;
use Illuminate\Support\Facades\Route;

/*
 * API aplikasi Android (token Sanctum). Prefix: /api/v1
 *
 * Hak akses memakai kunci menu yang sama dengan web (izin:...), jadi
 * pengaturan Hak Akses Menu berlaku juga di aplikasi. Maintenance dipasang
 * setelah auth:sanctum supaya user token terbaca (developer tetap lolos).
 */
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login');
    // Cek update aplikasi (tanpa login: layar login juga perlu tahu ada versi baru).
    Route::get('aplikasi/versi', [InfoController::class, 'versiAplikasi'])->middleware('throttle:60,1')->name('aplikasi.versi');

    Route::middleware(['auth:sanctum', 'penjaga.api', 'maintenance'])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::put('profil', [ProfilController::class, 'perbarui'])->name('profil.update');
        Route::post('profil/keluar-lain', [ProfilController::class, 'keluarPerangkatLain'])->middleware('throttle:10,1')->name('profil.keluar-lain');
        Route::post('profil/telegram/kode', [ProfilController::class, 'telegramKode'])->middleware('throttle:10,1')->name('profil.telegram.kode');

        Route::get('dashboard', [ProduksiController::class, 'dashboard'])->middleware('izin:dashboard')->name('dashboard');

        Route::get('produksi/opsi', [ProduksiController::class, 'opsi'])->middleware('izin:input-produksi')->name('produksi.opsi');
        Route::get('produksi/nomor-terakhir', [ProduksiController::class, 'lastSerial'])->middleware('izin:input-produksi')->name('produksi.nomor');
        Route::post('produksi', [ProduksiController::class, 'simpan'])->middleware('izin:input-produksi.create')->name('produksi.store');

        Route::get('produksi', [ProduksiController::class, 'riwayat'])->middleware('izin:riwayat-produksi')->name('produksi.index');
        Route::get('produksi/{productionLog}', [ProduksiController::class, 'detail'])->middleware('izin:riwayat-produksi')->name('produksi.show');
        Route::put('produksi/{productionLog}', [ProduksiController::class, 'perbarui'])->middleware('izin:riwayat-produksi.edit')->name('produksi.update');
        Route::delete('produksi/{productionLog}', [ProduksiController::class, 'hapus'])->middleware('izin:riwayat-produksi.delete')->name('produksi.destroy');
        Route::post('produksi/{productionLog}/reject', [ProduksiController::class, 'reject'])->middleware('izin:riwayat-produksi.reject')->name('produksi.reject');

        Route::get('target', [TargetController::class, 'index'])->middleware('izin:targets')->name('target.index');
        Route::post('target', [TargetController::class, 'simpan'])->middleware('izin:targets.edit')->name('target.store');
        Route::delete('target/{productionTarget}', [TargetController::class, 'hapus'])->whereNumber('productionTarget')->middleware('izin:targets.delete')->name('target.destroy');

        // Menu lain: langsung controller web lewat 'jembatan' (props Inertia → JSON).
        Route::middleware('jembatan')->group(function () {
            // Profil (Profile/Edit.jsx): avatar, profil publik (developer), putus Telegram.
            Route::get('profil/web', [ProfileController::class, 'edit'])->name('profil.web');
            Route::post('profil/avatar', [ProfileController::class, 'updateAvatar'])->name('profil.avatar');
            Route::post('profil/tentang', [ProfileController::class, 'updateAboutInfo'])->name('profil.tentang');
            Route::post('profil/tentang/foto', [ProfileController::class, 'updateAboutAvatar'])->name('profil.tentang.foto');
            Route::delete('profil/telegram', [ProfileController::class, 'telegramPutus'])->name('profil.telegram.putus');
            // Detail Produksi (Production/Show.jsx).
            Route::get('produksi/{productionLog}/web', [\App\Http\Controllers\ProductionLogController::class, 'show'])->middleware('izin:riwayat-produksi')->name('produksi.web');

            Route::get('catatan/opsi', [NoteController::class, 'index'])->middleware('izin:notes')->name('catatan.opsi');
            Route::get('catatan', [NoteController::class, 'list'])->middleware('izin:notes')->name('catatan.index');
            Route::post('catatan', [NoteController::class, 'store'])->middleware('izin:notes.create')->name('catatan.store');
            Route::put('catatan/{note}', [NoteController::class, 'update'])->middleware('izin:notes.edit')->name('catatan.update');
            Route::delete('catatan/{note}', [NoteController::class, 'destroy'])->middleware('izin:notes.delete')->name('catatan.destroy');

            Route::get('pengganti', [ReplacementController::class, 'index'])->middleware('izin:barang-pengganti')->name('pengganti.index');
            Route::post('pengganti', [ReplacementController::class, 'store'])->middleware('izin:barang-pengganti.create')->name('pengganti.store');
            Route::patch('pengganti/{replacement}/selesai', [ReplacementController::class, 'toggleComplete'])->middleware('izin:barang-pengganti.edit')->name('pengganti.selesai');
            Route::delete('pengganti/{replacement}', [ReplacementController::class, 'destroy'])->middleware('izin:barang-pengganti.delete')->name('pengganti.destroy');

            Route::get('aksesoris', [AccessoryController::class, 'index'])->middleware('izin:aksesoris')->name('aksesoris.index');
            Route::post('aksesoris', [AccessoryController::class, 'store'])->middleware('izin:aksesoris.create')->name('aksesoris.store');
            Route::put('aksesoris/{accessory}', [AccessoryController::class, 'update'])->middleware('izin:aksesoris.edit')->name('aksesoris.update');
            Route::patch('aksesoris/kategori/{category}', [AccessoryController::class, 'updateCategory'])->middleware('izin:aksesoris.edit')->name('aksesoris.kategori.update');
            Route::delete('aksesoris/kategori/{category}', [AccessoryController::class, 'destroyCategory'])->middleware('izin:aksesoris.delete')->name('aksesoris.kategori.destroy');
            Route::delete('aksesoris/{accessory}', [AccessoryController::class, 'destroy'])->middleware('izin:aksesoris.delete')->name('aksesoris.destroy');
            Route::get('aksesoris/export', [AccessoryController::class, 'exportExcel'])->middleware('izin:aksesoris.export')->name('aksesoris.export');

            // Dashboard persis web: data utama + statistik live + kalender + log aktivitas + agenda.
            Route::get('beranda', [\App\Http\Controllers\DashboardController::class, 'index'])->middleware('izin:dashboard')->name('beranda');
            Route::get('beranda/live', [\App\Http\Controllers\DashboardController::class, 'liveStats'])->middleware('izin:dashboard')->name('beranda.live');
            Route::get('beranda/kalender', [\App\Http\Controllers\DashboardController::class, 'calendar'])->middleware('izin:dashboard')->name('beranda.kalender');
            Route::get('beranda/aktivitas', [\App\Http\Controllers\DashboardController::class, 'aktivitas'])->middleware('izin:dashboard')->name('beranda.aktivitas');
            Route::post('agenda', [\App\Http\Controllers\CalendarEventController::class, 'store'])->name('agenda.store');
            Route::delete('agenda/{calendarEvent}', [\App\Http\Controllers\CalendarEventController::class, 'destroy'])->name('agenda.destroy');

            // Target Produksi persis web: foto jadwal mingguan, set target (jumlah / sampai no. urut), progres live.
            Route::get('target/web', [\App\Http\Controllers\ProductionTargetController::class, 'index'])->middleware('izin:targets')->name('target.web');
            Route::get('target/live', [\App\Http\Controllers\ProductionTargetController::class, 'liveData'])->middleware('izin:targets')->name('target.live');
            Route::get('target/nomor', [\App\Http\Controllers\ProductionTargetController::class, 'actualQty'])->middleware('izin:targets.edit')->name('target.nomor');
            Route::post('target/simpan', [\App\Http\Controllers\ProductionTargetController::class, 'store'])->middleware('izin:targets.edit')->name('target.simpan');
            Route::post('target/jadwal', [\App\Http\Controllers\ProductionTargetController::class, 'uploadSchedulePhoto'])->middleware('izin:targets.edit')->name('target.jadwal.store');
            Route::delete('target/jadwal', [\App\Http\Controllers\ProductionTargetController::class, 'deleteSchedulePhoto'])->middleware('izin:targets.delete')->name('target.jadwal.destroy');
            Route::delete('target/web/{productionTarget}', [\App\Http\Controllers\ProductionTargetController::class, 'destroy'])->middleware('izin:targets.delete')->name('target.web.destroy');

            // Riwayat Produksi persis web (hari → kategori → baris, ringkasan, filter lengkap).
            Route::get('riwayat', [\App\Http\Controllers\ProductionLogController::class, 'index'])->middleware('izin:riwayat-produksi')->name('riwayat');

            // Badge chat + pengingat catatan di aplikasi (sama dengan polling /api/notifications web: cukup login).
            Route::get('notifikasi', [MessageController::class, 'notifications'])->name('notifikasi');
            Route::get('chat/kontak', [MessageController::class, 'contacts'])->middleware('izin:chatting')->name('chat.kontak');
            Route::get('chat', [MessageController::class, 'myMessages'])->middleware('izin:chatting')->name('chat.index');
            Route::get('chat/baru', [MessageController::class, 'since'])->middleware('izin:chatting')->name('chat.baru');
            Route::post('chat', [MessageController::class, 'store'])->middleware('izin:chatting.send')->name('chat.store');
            Route::patch('chat/ping', [MessageController::class, 'ping'])->middleware('izin:chatting')->name('chat.ping');
            Route::patch('chat/ketik', [MessageController::class, 'typing'])->middleware('izin:chatting')->name('chat.ketik');
            Route::patch('chat/{partnerId}/baca', [MessageController::class, 'markConversationRead'])->middleware('izin:chatting')->whereNumber('partnerId')->name('chat.baca');
            Route::delete('chat/{partnerId}', [MessageController::class, 'destroyConversation'])->middleware('izin:chatting.delete')->whereNumber('partnerId')->name('chat.destroy');

            Route::get('gambar-kerja', [GambarKerjaController::class, 'index'])->middleware('izin:gambar-kerja')->name('gambar.index');
            Route::get('gambar-kerja/berkas', [GambarKerjaController::class, 'daftarBerkas'])->middleware('izin:gambar-kerja')->name('gambar.berkas');
            Route::get('gambar-kerja/grup', [GambarKerjaController::class, 'byGroup'])->middleware('izin:gambar-kerja')->name('gambar.grup');
            Route::post('gambar-kerja', [GambarKerjaController::class, 'store'])->middleware('izin:gambar-kerja.upload')->name('gambar.store');
            Route::patch('gambar-kerja/grup/info', [GambarKerjaController::class, 'updateInfo'])->middleware('izin:gambar-kerja.edit')->name('gambar.info');
            Route::post('gambar-kerja/grup/thumbnail', [GambarKerjaController::class, 'uploadThumbnail'])->middleware('izin:gambar-kerja.edit')->name('gambar.thumb');
            Route::delete('gambar-kerja/grup/thumbnail', [GambarKerjaController::class, 'deleteThumbnail'])->middleware('izin:gambar-kerja.edit')->name('gambar.thumb.destroy');
            Route::delete('gambar-kerja/grup', [GambarKerjaController::class, 'destroyByGroup'])->middleware('izin:gambar-kerja.delete')->name('gambar.grup.destroy');
            Route::patch('gambar-kerja/{gambarKerja}/catatan', [GambarKerjaController::class, 'updateCatatan'])->middleware('izin:gambar-kerja.edit')->name('gambar.catatan');
            Route::delete('gambar-kerja/{gambarKerja}', [GambarKerjaController::class, 'destroy'])->middleware('izin:gambar-kerja.delete')->name('gambar.destroy');

            Route::get('produk', [ProductController::class, 'index'])->middleware('izin:master-produk')->name('produk.index');
            Route::get('produk/ukuran', [ProductController::class, 'ukuranIndex'])->middleware('izin:master-produk')->name('produk.ukuran');
            Route::get('produk/form', [ProductController::class, 'create'])->middleware('izin:master-produk.create')->name('produk.create');
            Route::post('produk', [ProductController::class, 'store'])->middleware('izin:master-produk.create')->name('produk.store');
            Route::get('produk/{product}', [ProductController::class, 'show'])->middleware('izin:master-produk')->name('produk.show');
            Route::get('produk/{product}/edit', [ProductController::class, 'edit'])->middleware('izin:master-produk.edit')->name('produk.edit');
            Route::put('produk/{product}', [ProductController::class, 'update'])->middleware('izin:master-produk.edit')->name('produk.update');
            Route::patch('produk/{product}/aktif', [ProductController::class, 'toggleActive'])->middleware('izin:master-produk.edit')->name('produk.aktif');
            Route::delete('produk/{product}', [ProductController::class, 'destroy'])->middleware('izin:master-produk.delete')->name('produk.destroy');

            Route::get('kategori', [CategoryController::class, 'index'])->middleware('izin:kategori')->name('kategori.index');
            Route::post('kategori', [CategoryController::class, 'store'])->middleware('izin:kategori.create')->name('kategori.store');
            Route::get('kategori/{category}/edit', [CategoryController::class, 'edit'])->middleware('izin:kategori.edit')->name('kategori.edit');
            Route::put('kategori/{category}', [CategoryController::class, 'update'])->middleware('izin:kategori.edit')->name('kategori.update');
            Route::delete('kategori/{category}', [CategoryController::class, 'destroy'])->middleware('izin:kategori.delete')->name('kategori.destroy');

            Route::get('laporan', [ReportController::class, 'index'])->middleware('izin:laporan')->name('laporan.index');
            Route::get('laporan/harian', [ReportController::class, 'daily'])->middleware('izin:laporan')->name('laporan.harian');
            Route::get('laporan/pdf', [ReportController::class, 'exportPdf'])->middleware('izin:laporan.export')->name('laporan.pdf');
            Route::get('laporan/excel', [ReportController::class, 'exportExcel'])->middleware('izin:laporan.export')->name('laporan.excel');
            Route::get('laporan/harian/pdf', [ReportController::class, 'exportDailyPdf'])->middleware('izin:laporan.export')->name('laporan.harian.pdf');

            Route::get('manajemen', [ManagementController::class, 'index'])->middleware('izin:manajemen')->name('manajemen.index');
            // Siapa boleh mengelola peran apa dijaga role: seperti di routes/web.php.
            foreach ([
                'operators'   => [OperatorController::class, 'operator', 'developer,admin'],
                'visitors'    => [VisitorController::class, 'visitor', 'developer,admin'],
                'supervisors' => [SupervisorController::class, 'supervisor', 'developer,admin'],
                'mandors'     => [MandorController::class, 'mandor', 'developer,admin'],
                'admins'      => [AdminController::class, 'admin', 'developer'],
                'developers'  => [DeveloperController::class, 'developer', 'developer'],
                'departments' => [DepartmentController::class, 'department', 'developer'],
            ] as $sumber => [$kelas, $param, $peran]) {
                Route::middleware("role:$peran")->name("manajemen.$sumber.")->group(function () use ($sumber, $kelas, $param) {
                    Route::get("manajemen/$sumber/form", [$kelas, 'create'])->middleware('izin:manajemen.create')->name('create');
                    Route::post("manajemen/$sumber", [$kelas, 'store'])->middleware('izin:manajemen.create')->name('store');
                    Route::get("manajemen/$sumber/{{$param}}/edit", [$kelas, 'edit'])->middleware('izin:manajemen.edit')->name('edit');
                    Route::put("manajemen/$sumber/{{$param}}", [$kelas, 'update'])->middleware('izin:manajemen.edit')->name('update');
                    Route::delete("manajemen/$sumber/{{$param}}", [$kelas, 'destroy'])->middleware('izin:manajemen.delete')->name('destroy');
                });
            }
            Route::patch('manajemen/operators/{operator}/aktif', [OperatorController::class, 'toggleActive'])
                ->middleware(['role:developer,admin', 'izin:manajemen.edit'])->name('manajemen.operators.aktif');

            Route::middleware('role:developer')->group(function () {
                Route::get('hak-akses', [PermissionController::class, 'index'])->middleware('izin:permissions')->name('hak-akses.index');
                Route::post('hak-akses', [PermissionController::class, 'update'])->middleware('izin:permissions')->name('hak-akses.update');

                Route::get('settings', [BotSettingController::class, 'index'])->middleware('izin:settings')->name('settings.index');
                Route::post('settings', [BotSettingController::class, 'update'])->middleware('izin:settings')->name('settings.update');
                Route::post('settings/test', [BotSettingController::class, 'test'])->middleware('izin:settings')->name('settings.test');
                Route::post('settings/laporan-harian', [BotSettingController::class, 'sendDailyReport'])->middleware('izin:settings')->name('settings.laporan');
                Route::post('settings/webhook-daftar', [BotSettingController::class, 'registerWebhook'])->middleware('izin:settings')->name('settings.webhook');
                Route::post('settings/webhook-info', [BotSettingController::class, 'webhookInfo'])->middleware('izin:settings')->name('settings.webhook-info');
                // Kartu "Profil di Halaman Tentang" di Settings.
                Route::post('settings/profil-tentang', [ProfileController::class, 'updateAboutInfo'])->middleware('izin:settings')->name('settings.profil-tentang');
                Route::post('settings/foto-tentang', [ProfileController::class, 'updateAboutAvatar'])->middleware('izin:settings')->name('settings.foto-tentang');
            });

            Route::get('tutorial', [InfoController::class, 'tutorial'])->middleware('izin:tutorial')->name('tutorial');
            Route::post('tutorial', [InfoController::class, 'tutorialEmbed'])->middleware('izin:tutorial.edit')->name('tutorial.update');
            Route::get('tentang', [InfoController::class, 'about'])->middleware('izin:about')->name('tentang');
            Route::post('tentang/changelog', [InfoController::class, 'changelogStore'])->middleware('izin:about.edit')->name('tentang.store');
            Route::delete('tentang/changelog/{changelog}', [InfoController::class, 'changelogDestroy'])->middleware('izin:about.edit')->name('tentang.destroy');
        });

        // Berkas (gambar kerja, foto catatan/jadwal): izinnya per folder di AksesBerkas.
        Route::get('file/{path}', [GambarKerjaController::class, 'serveFile'])->where('path', '.+')->name('file');
        Route::get('thumb/{path}', [GambarKerjaController::class, 'serveThumb'])->where('path', '.+')->name('thumb');
    });
});
