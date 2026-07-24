<?php

use App\Http\Controllers\AccessoryController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BotSettingController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\MandorController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\GambarKerjaController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductionTargetController;
use App\Http\Controllers\ReplacementController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Telegram webhook — no auth, no CSRF (exempt via bootstrap/app.php)
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])->name('telegram.webhook');

// Auth routes (guest only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'showForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendLink'])->name('password.email');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])->name('password.update');
});

// Logout
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Root redirect
Route::get('/', fn() => redirect()->route('dashboard'));

// File serve — bypass junction/symlink issue pada local dev
Route::get('/file/{path}', [GambarKerjaController::class, 'serveFile'])
    ->where('path', '.+')->middleware('auth')->name('storage.file');

// Protected routes (authenticated)
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/dashboard-live', [DashboardController::class, 'liveStats'])->name('api.dashboard.live');

    // Tutorial
    Route::get('/tutorial', function () {
        $iframeUrl = \App\Models\BotSetting::instance()->tutorial_iframe_url;
        return view('tutorial', compact('iframeUrl'));
    })->name('tutorial');
    Route::post('/tutorial/embed', function (\Illuminate\Http\Request $request) {
        abort_unless(auth()->user()->isDeveloper(), 403);
        $request->validate(['iframe_url' => ['nullable', 'string', 'max:1000']]);
        \App\Models\BotSetting::instance()->update(['tutorial_iframe_url' => $request->iframe_url ?: null]);
        return back()->with('success', 'URL embed berhasil diperbarui.');
    })->name('tutorial.embed.update')->middleware('role:developer');

    // About — load developer pertama yang aktif + changelog
    Route::get('/about', function () {
        $developer  = \App\Models\User::where('role', 'developer')->where('is_active', true)->first();
        $changelogs = \App\Models\Changelog::latest()->get();
        return view('about', compact('developer', 'changelogs'));
    })->name('about');

    // Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::post('/profile/logout-others', [ProfileController::class, 'logoutOtherDevices'])->name('profile.logout-others');
    Route::post('/profile/about-avatar', [ProfileController::class, 'updateAboutAvatar'])->name('profile.about-avatar');
    Route::post('/profile/about-info',   [ProfileController::class, 'updateAboutInfo'])->name('profile.about-info');

    // Riwayat Produksi — semua role bisa lihat
    Route::get('/production', [ProductionLogController::class, 'index'])->name('production.index');

    // Input / Edit Produksi — akses diatur via Hak Akses Menu (EnforceMenuAccess)
    Route::group([], function () {
        Route::get('/production/create', [ProductionLogController::class, 'create'])->name('production.create');
        Route::post('/production', [ProductionLogController::class, 'store'])->name('production.store');
        Route::get('/production/{productionLog}/edit', [ProductionLogController::class, 'edit'])->name('production.edit');
        Route::put('/production/{productionLog}', [ProductionLogController::class, 'update'])->name('production.update');
        Route::delete('/production/{productionLog}', [ProductionLogController::class, 'destroy'])->name('production.destroy');
    });

    // Target Produksi — akses diatur via Hak Akses Menu
    Route::get('/production/targets', [ProductionTargetController::class, 'index'])->name('production.targets.index');

    // Target Produksi — set/hapus diatur via Hak Akses Menu (aksi targets.edit / targets.delete)
    Route::group([], function () {
        Route::post('/production/targets',                      [ProductionTargetController::class, 'store'])->name('production.targets.store');
        // Literal routes HARUS sebelum wildcard {productionTarget}
        Route::post('/production/targets/schedule-photo',       [ProductionTargetController::class, 'uploadSchedulePhoto'])->name('production.targets.schedule-photo.store');
        Route::delete('/production/targets/schedule-photo',     [ProductionTargetController::class, 'deleteSchedulePhoto'])->name('production.targets.schedule-photo.destroy');
        Route::delete('/production/targets/{productionTarget}', [ProductionTargetController::class, 'destroy'])->name('production.targets.destroy');
    });

    // Show detail — setelah literal routes supaya tidak di-capture duluan oleh wildcard
    Route::get('/production/{productionLog}', [ProductionLogController::class, 'show'])->name('production.show');

    // API untuk dropdown product (AJAX)
    Route::get('/api/products', [ProductController::class, 'apiList'])->name('api.products');

    // API — nomor urut terakhir per produk (untuk hint di form input)
    Route::get('/api/production/last-serial', [ProductionLogController::class, 'lastSerial'])->name('api.production.last-serial');
    // API — polling realtime: deteksi perubahan data produksi
    Route::get('/api/production/poll', [ProductionLogController::class, 'poll'])->name('api.production.poll');
    // API — live data target produksi (untuk auto-refresh)
    Route::get('/api/production/targets-live', [ProductionTargetController::class, 'liveData'])->name('api.targets.live');
    // API — aktual produksi per produk per tanggal (untuk hint no. urut di form target)
    Route::get('/api/production/actual-qty', [ProductionTargetController::class, 'actualQty'])->name('api.targets.actual-qty');

    // Ukuran Produk (semua user bisa lihat)
    Route::get('/products/ukuran', [ProductController::class, 'ukuranIndex'])->name('products.ukuran');

    // Gambar Kerja — literal routes HARUS sebelum wildcard
    // Gambar Kerja — aksi (upload/edit/hapus) diatur via Hak Akses Menu
    Route::get('/gambar-kerja',           [GambarKerjaController::class, 'index'])->name('gambar-kerja.index');
    Route::get('/gambar-kerja/create',    [GambarKerjaController::class, 'create'])->name('gambar-kerja.create');
    Route::post('/gambar-kerja',          [GambarKerjaController::class, 'store'])->name('gambar-kerja.store');
    Route::get('/gambar-kerja/group',     [GambarKerjaController::class, 'byGroup'])->name('gambar-kerja.by-group');
    Route::delete('/gambar-kerja/group',  [GambarKerjaController::class, 'destroyByGroup'])->name('gambar-kerja.destroy-by-group');
    Route::post('/gambar-kerja/group/thumbnail',          [GambarKerjaController::class, 'uploadThumbnail'])->name('gambar-kerja.upload-thumbnail');
    Route::delete('/gambar-kerja/group/thumbnail',       [GambarKerjaController::class, 'deleteThumbnail'])->name('gambar-kerja.delete-thumbnail');
    Route::patch('/gambar-kerja/group/kategori',         [GambarKerjaController::class, 'updateKategori'])->name('gambar-kerja.update-kategori');
    Route::patch('/gambar-kerja/group/info',             [GambarKerjaController::class, 'updateInfo'])->name('gambar-kerja.update-info');
    Route::delete('/gambar-kerja/{gambarKerja}', [GambarKerjaController::class, 'destroy'])->name('gambar-kerja.destroy');

    // Barang Pengganti / Repair — akses diatur via Hak Akses Menu
    Route::get('/replacements', [ReplacementController::class, 'index'])->name('replacements.index');
    Route::post('/replacements', [ReplacementController::class, 'store'])->name('replacements.store');
    Route::patch('/replacements/{replacement}/toggle-complete', [ReplacementController::class, 'toggleComplete'])->name('replacements.toggle-complete');
    Route::delete('/replacements/{replacement}', [ReplacementController::class, 'destroy'])->name('replacements.destroy');

    // Aksesoris Keluar — akses diatur via Hak Akses Menu
    Route::get('/accessories', [AccessoryController::class, 'index'])->name('accessories.index');
    Route::post('/accessories', [AccessoryController::class, 'store'])->name('accessories.store');
    Route::delete('/accessories/{accessory}', [AccessoryController::class, 'destroy'])->name('accessories.destroy');

    // Notes
    Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
    Route::get('/notes/list', [NoteController::class, 'list'])->name('notes.list');
    Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    // Agenda / Event Kalender
    Route::get('/api/calendar-events', [\App\Http\Controllers\CalendarEventController::class, 'byMonth'])->name('calendar.events');
    Route::post('/calendar-events', [\App\Http\Controllers\CalendarEventController::class, 'store'])->name('calendar.events.store');
    Route::delete('/calendar-events/{calendarEvent}', [\App\Http\Controllers\CalendarEventController::class, 'destroy'])->name('calendar.events.destroy');

    // Chat / Pesan
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/my', [MessageController::class, 'myMessages'])->name('messages.my');
    Route::get('/api/messages/since', [MessageController::class, 'since'])->name('messages.since');
    Route::get('/api/notifications', [MessageController::class, 'notifications'])->name('api.notifications');
    Route::get('/chatting', [MessageController::class, 'chatUnified'])->name('chatting');
    Route::get('/api/chat-contacts', [MessageController::class, 'contacts'])->name('chat.contacts');
    Route::patch('/api/ping', [MessageController::class, 'ping'])->name('api.ping');
    Route::patch('/api/typing', [MessageController::class, 'typing'])->name('api.typing');
    Route::get('/chat', [MessageController::class, 'chat'])->name('chat'); // legacy
    // Semua user bisa hapus percakapan mereka sendiri
    Route::delete('/messages/conversation/{partnerId}', [MessageController::class, 'destroyConversation'])->name('messages.destroy-conversation');
    // Semua user bisa menandai pesan dari satu lawan bicara sebagai dibaca (read receipt dua arah)
    Route::patch('/api/messages/read-conversation/{partnerId}', [MessageController::class, 'markConversationRead'])->name('messages.read-conversation');

    // Kategori (lihat) & Laporan — akses diatur via Hak Akses Menu
    Route::group([], function () {

        // Kategori — hanya bisa lihat (index), create/edit/delete hanya untuk developer
        Route::get('/categories', [\App\Http\Controllers\CategoryController::class, 'index'])->name('categories.index');

        // Laporan
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/',             [ReportController::class, 'index'])->name('index');
            Route::get('/daily',        [ReportController::class, 'daily'])->name('daily');
            Route::get('/export-pdf',   [ReportController::class, 'exportPdf'])->name('export-pdf');
            Route::get('/export-excel', [ReportController::class, 'exportExcel'])->name('export-excel');
            Route::get('/daily-pdf',    [ReportController::class, 'exportDailyPdf'])->name('daily-pdf');
        });
    });

    // Admin + Supervisor + Mandor: Chat inbox
    Route::middleware('role:developer,admin,supervisor,mandor')->group(function () {
        Route::get('/messages', [MessageController::class, 'adminMessages'])->name('messages.index');
        Route::post('/messages/{message}/reply', [MessageController::class, 'reply'])->name('messages.reply');
        Route::patch('/messages/{message}/read', [MessageController::class, 'markRead'])->name('messages.read');
        Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
    });

    // Halaman Manajemen — akses diatur via Hak Akses Menu
    Route::get('/management', [ManagementController::class, 'index'])->name('management.index');

    // Master Produk — akses (lihat/tambah/edit/hapus) diatur via Hak Akses Menu
    Route::resource('products', ProductController::class);
    Route::patch('products/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggle-active');

    Route::middleware('role:developer,admin')->group(function () {
        // Manajemen Operator (admin boleh kelola operator)
        Route::resource('operators', OperatorController::class)->except(['show']);
        Route::patch('/operators/{operator}/toggle-active', [OperatorController::class, 'toggleActive'])->name('operators.toggle-active');

        // Manajemen Visitor (developer + admin bisa kelola)
        Route::resource('visitors', \App\Http\Controllers\VisitorController::class)->except(['show', 'index']);
    });

    // Developer only routes (permission tertinggi — hanya developer yg bisa kelola admin, supervisor, mandor, developer, departemen, kategori)
    Route::middleware('role:developer')->group(function () {

        // Hak Akses Menu per role
        Route::get('/permissions',  [\App\Http\Controllers\PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions', [\App\Http\Controllers\PermissionController::class, 'update'])->name('permissions.update');

        Route::resource('developers', \App\Http\Controllers\DeveloperController::class)->except(['show']);
        Route::resource('admins', AdminController::class)->except(['show']);
        Route::resource('supervisors', SupervisorController::class)->except(['show']);
        Route::resource('mandors', MandorController::class)->except(['show']);
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::post('/departments/quick-store', [DepartmentController::class, 'quickStore'])->name('departments.quick-store');
        Route::resource('categories', CategoryController::class)->except(['show']);

        // Changelog (riwayat update aplikasi)
        Route::post('/about/changelog', function (\Illuminate\Http\Request $request) {
            $request->validate([
                'type'        => ['required', 'in:feature,fix,improvement,security'],
                'version'     => ['nullable', 'string', 'max:20'],
                'title'       => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
            ]);
            \App\Models\Changelog::create($request->only('type', 'version', 'title', 'description'));
            return back()->with('success', 'Entry changelog berhasil ditambahkan.');
        })->name('about.changelog.store');

        Route::delete('/about/changelog/{changelog}', function (\App\Models\Changelog $changelog) {
            $changelog->delete();
            return back()->with('success', 'Entry changelog berhasil dihapus.');
        })->name('about.changelog.destroy');

        // Developer Tools — Bot Notifikasi
        Route::get('/developer/bot-settings',                  [BotSettingController::class, 'index'])->name('developer.bot-settings');
        Route::post('/developer/bot-settings',                 [BotSettingController::class, 'update'])->name('developer.bot-settings.update');
        Route::post('/developer/bot-settings/test',            [BotSettingController::class, 'test'])->name('developer.bot-settings.test');
        Route::post('/developer/bot-settings/webhook-register',[BotSettingController::class, 'registerWebhook'])->name('developer.bot-settings.webhook-register');
        Route::post('/developer/bot-settings/webhook-info',    [BotSettingController::class, 'webhookInfo'])->name('developer.bot-settings.webhook-info');
        Route::post('/developer/bot-settings/send-daily-report', [BotSettingController::class, 'sendDailyReport'])->name('developer.bot-settings.send-daily-report');
    });
});
