<?php

use App\Models\Note;
use App\Models\SchedulePhoto;
use App\Services\BotNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hapus semua foto jadwal setiap Senin pukul 01:00 pagi
// Jalankan cron: * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
Artisan::command('jadwal:clear', function () {
    $photos = SchedulePhoto::all();
    $count  = $photos->count();

    foreach ($photos as $photo) {
        Storage::disk('public')->delete($photo->file_path);
        // Hapus folder tanggal jika sudah kosong
        Storage::disk('public')->deleteDirectory(dirname($photo->file_path));
    }

    SchedulePhoto::truncate();

    $this->info("Selesai: {$count} foto jadwal dihapus dari server.");
})->purpose('Hapus semua foto jadwal dari server (dijadwalkan tiap Senin)');

Schedule::command('jadwal:clear')->weekly()->mondays()->at('01:00')->timezone('Asia/Jakarta');

// Hapus catatan yang sudah ditandai selesai lebih dari 8 jam
Artisan::command('catatan:clear-done', function () {
    $count = Note::where('is_done', true)
        ->whereNotNull('done_at')
        ->where('done_at', '<=', now()->subHours(8))
        ->delete();

    $this->info("Selesai: {$count} catatan yang sudah selesai dihapus.");
})->purpose('Hapus catatan yang sudah ditandai selesai lebih dari 8 jam');

Schedule::command('catatan:clear-done')->hourly();

// Hapus target produksi 2 jam setelah tercapai (reached_at di-set saat tercapai)
Artisan::command('target:clear-reached', function () {
    $targets = \App\Models\ProductionTarget::with('product')
        ->whereNotNull('reached_at')
        ->where('reached_at', '<=', now()->subHours(2))
        ->get();

    foreach ($targets as $t) {
        $name = $t->product->name ?? ('produk #' . $t->product_id);
        $t->delete();
        \App\Models\ActivityLog::record('delete', "Target tercapai dihapus otomatis: {$name}");
    }

    $this->info("Selesai: {$targets->count()} target tercapai dihapus.");
})->purpose('Hapus target produksi 2 jam setelah tercapai');

Schedule::command('target:clear-reached')->everyThirtyMinutes();

// Hapus pesan chat lebih dari 7 hari agar DB tidak penuh (auto-reset)
Artisan::command('chat:clear-old', function () {
    $count = \App\Models\Message::where('created_at', '<=', now()->subDays(7))->delete();
    $this->info("Selesai: {$count} pesan chat lebih dari 7 hari dihapus.");
})->purpose('Hapus pesan chat yang lebih dari 7 hari');

Schedule::command('chat:clear-old')->dailyAt('02:00')->timezone('Asia/Jakarta');

// Kirim laporan harian setiap jam 10 malam
Artisan::command('laporan:harian', function () {
    $setting = \App\Models\BotSetting::instance();
    if (!$setting->report_enabled) {
        $this->info('Laporan harian dinonaktifkan — dilewati.');
        return;
    }
    $result = BotNotificationService::sendDailyReport();
    if ($result['ok']) {
        $this->info($result['message']);
    } else {
        $this->warn($result['message']);
    }
})->purpose('Kirim laporan harian produksi ke Telegram/Discord setiap jam 10 malam');

Schedule::command('laporan:harian')->dailyAt('22:00')->timezone('Asia/Jakarta');

// Sinkron Gambar Kerja dari qc.yogaariyanto.com (PRD 15): satu arah, hanya menambah, qc hanya dibaca.
Artisan::command('gambar:sinkron-qc {--cek : Hanya hitung, tanpa menyalin atau menulis} {--otomatis : Hormati saklar otomatis}', function () {
    $layanan = app(\App\Services\SinkronGambarQc::class);
    if ($this->option('otomatis') && ! $layanan::otomatis()) {
        return 0;
    }
    $cek = (bool) $this->option('cek');
    $disk = \Illuminate\Support\Facades\Storage::disk('local');
    $disk->makeDirectory('.');
    $kunci = fopen($disk->path('sinkron-qc.lock'), 'c');
    if (! $kunci || ! flock($kunci, LOCK_EX | LOCK_NB)) {
        if ($kunci) { fclose($kunci); }
        $this->error('Sinkron sedang berjalan. Tunggu hingga selesai.');
        return 1;
    }
    try {
        $akar = $layanan::akarQc();
        if (! $akar) {
            throw new \RuntimeException('Sumber qc belum tersedia.');
        }
        $hasil = $layanan->jalankan($layanan::sambunganQc($akar), $akar . '/storage/app/public', $cek);
        $layanan::simpanHasil($hasil);
        if (! $cek && ($hasil['baru'] > 0 || $hasil['berkas_disalin'] > 0)) {
            \App\Support\Realtime::bump('gambar');
        }
        if (! $cek && ($hasil['jadwal_baru'] > 0 || $hasil['berkas_disalin'] > 0)) {
            \App\Support\Realtime::bump('target');
        }
    } catch (\Throwable $e) {
        $hasil = ['waktu' => now()->toDateTimeString(), 'galat' => 'Sinkron gagal. Periksa ketersediaan sumber dan izin berkas server.', 'cek' => $cek];
        $layanan::simpanHasil($hasil);
        $this->error($hasil['galat']);
        return 1;
    } finally {
        flock($kunci, LOCK_UN);
        fclose($kunci);
    }
    $this->info(sprintf('sumber %d, sudah ada %d, %s %d, berkas %s %d, konflik %d, berkas hilang %d, foto jadwal %s %d',
        $hasil['sumber'], $hasil['sudah_ada'], $cek ? 'akan ditambah' : 'ditambah', $hasil['baru'], $cek ? 'akan disalin' : 'disalin', $hasil['berkas_disalin'], count($hasil['konflik']), count($hasil['berkas_hilang']), $cek ? 'akan ditambah' : 'ditambah', $hasil['jadwal_baru']));
    foreach ($hasil['konflik'] as $k) {
        $this->warn('  konflik: ' . $k);
    }

    return 0;
})->purpose('Sinkron Gambar Kerja dari qc (satu arah, hanya menambah)');

Schedule::command('gambar:sinkron-qc --otomatis')->everyMinute()->withoutOverlapping();
