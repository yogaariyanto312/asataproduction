<?php

namespace App\Services\Telegram;

/**
 * Tombol "Buka di aplikasi" pada pesan bot.
 *
 * Notifikasi tanpa tautan memaksa orang mencari sendiri halamannya — dan
 * biasanya tidak jadi dibuka sama sekali.
 */
class Tautan
{
    /**
     * Susunan inline_keyboard Telegram untuk satu tombol.
     *
     * Mengembalikan null kalau alamatnya tidak bisa dipakai Telegram (mis.
     * APP_URL masih localhost saat pengembangan). Telegram menolak SELURUH
     * pesan kalau ada tombol dengan URL tak sah — jadi lebih baik pesannya
     * terkirim tanpa tombol daripada tidak terkirim sama sekali.
     */
    public static function tombol(string $label, string $namaRoute, array $parameter = []): ?array
    {
        try {
            $url = route($namaRoute, $parameter);
        } catch (\Throwable) {
            return null;
        }

        if (! self::bisaDibuka($url)) {
            return null;
        }

        return [[['text' => $label, 'url' => $url]]];
    }

    private static function bisaDibuka(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return false;
        }

        // Telegram menolak localhost dan nama host tanpa titik.
        return $host !== 'localhost'
            && $host !== '127.0.0.1'
            && str_contains($host, '.');
    }
}
