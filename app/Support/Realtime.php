<?php

namespace App\Support;

use App\Models\User;

/**
 * Realtime ringan (PRD 14): setiap topik punya "nomor versi" di sebuah berkas kecil; setiap perubahan data
 * menaikkan nomornya. Browser bertanya ke public/rt.php (tanpa boot Laravel, ~1-3 ms) dan hanya mengambil data
 * lewat endpoint biasa saat nomor berubah. Berjalan di shared hosting tanpa proses tambahan atau layanan luar.
 *
 * Topik global: produksi, target, catatan, gambar. Topik per pengguna: chat, notif (berkas "<topik>.<id>").
 * Berkas ada di storage/app/rt (jangan dihapus saat deploy; bila hilang nomornya mulai dari nol dan klien
 * menyesuaikan sendiri, sementara cadangan polling 60 detik menutup selisihnya).
 */
class Realtime
{
    public const TOPIK_GLOBAL = ['produksi', 'target', 'catatan', 'gambar'];
    public const TOPIK_PENGGUNA = ['chat', 'notif'];
    private const MASA_TOKEN = 21600; // 6 jam; diperbarui tiap navigasi (props Inertia)

    public static function dir(): string
    {
        return storage_path('app/rt');
    }

    /** Naikkan versi. $topik: 'produksi' (global) atau 'chat.12' (per pengguna). Tidak pernah melempar galat. */
    public static function bump(string ...$topik): void
    {
        try {
            if (! is_dir(self::dir())) {
                @mkdir(self::dir(), 0775, true);
            }
            foreach ($topik as $t) {
                // hrtime (ns): dua perubahan dalam milidetik yang sama tetap menghasilkan nomor berbeda.
                @file_put_contents(self::dir() . '/' . preg_replace('/[^a-z0-9._-]/', '', strtolower($t)) . '.v', (string) hrtime(true), LOCK_EX);
            }
        } catch (\Throwable $e) {
            // realtime hanyalah pemercepat: polling cadangan tetap berjalan
        }
    }

    public static function bumpPengguna(string $topik, ?int $userId): void
    {
        if ($userId) {
            self::bump($topik . '.' . $userId);
        }
    }

    private static function kunci(): string
    {
        $berkas = self::dir() . '/kunci';
        $isi = is_file($berkas) ? trim((string) @file_get_contents($berkas)) : '';
        if ($isi === '') {
            if (! is_dir(self::dir())) {
                @mkdir(self::dir(), 0775, true);
            }
            $isi = bin2hex(random_bytes(32));
            @file_put_contents($berkas, $isi, LOCK_EX);
        }

        return $isi;
    }

    /** Token bertanda tangan untuk rt.php: "<id>.<kedaluwarsa>.<hmac>". */
    public static function token(User $user): string
    {
        $exp = time() + self::MASA_TOKEN;

        return $user->id . '.' . $exp . '.' . hash_hmac('sha256', $user->id . '.' . $exp, self::kunci());
    }
}
