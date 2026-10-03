<?php

namespace App\Services\Telegram;

/**
 * Perapi teks untuk pesan Telegram.
 *
 * Seluruh pesan bot memakai parse_mode HTML, bukan Markdown. Alasannya nyata:
 * nama produk dan seri di aplikasi ini bisa memuat `_`, `*`, atau `[` — di
 * Markdown, satu underscore saja membuat Telegram menolak pesannya dengan
 * galat 400, dan notifikasinya hilang tanpa jejak. HTML bisa di-escape dengan
 * pasti.
 */
class Teks
{
    /** Bersihkan nilai dinamis agar aman disisipkan ke pesan HTML. */
    public static function aman(?string $nilai): string
    {
        return htmlspecialchars((string) $nilai, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function tebal(?string $nilai): string
    {
        return '<b>' . self::aman($nilai) . '</b>';
    }

    public static function kode(?string $nilai): string
    {
        return '<code>' . self::aman($nilai) . '</code>';
    }

    /** Angka dengan pemisah ribuan gaya Indonesia. */
    public static function angka(int|float $nilai): string
    {
        return number_format((float) $nilai, 0, ',', '.');
    }

    /** Lampu status untuk reject rate, sama seperti di laporan harian. */
    public static function lampu(float $persen): string
    {
        return $persen > 5 ? '🔴' : ($persen > 2 ? '🟡' : '🟢');
    }
}
