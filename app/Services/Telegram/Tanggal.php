<?php

namespace App\Services\Telegram;

use Carbon\Carbon;

/** Penerjemah tanggal yang diketik orang di Telegram. */
class Tanggal
{
    /**
     * Mengembalikan "YYYY-MM-DD", atau null kalau tidak dikenali.
     *
     * Orang mengetik tanggal dengan cara yang berbeda-beda, jadi yang didukung:
     * 2026-06-01, 01/06/2026, 01-06-2026, serta kata "hari ini" dan "kemarin".
     * Kalau tidak dikenali, sengaja mengembalikan null — pemanggilnya yang
     * memutuskan memakai hari ini atau menolak, bukan menebak diam-diam.
     */
    public static function urai(?string $mentah): ?string
    {
        $mentah = strtolower(trim((string) $mentah));

        if ($mentah === '') {
            return null;
        }

        if (in_array($mentah, ['hari ini', 'hariini', 'today'], true)) {
            return now()->toDateString();
        }

        if (in_array($mentah, ['kemarin', 'yesterday'], true)) {
            return now()->subDay()->toDateString();
        }

        // DD/MM/YYYY atau DD-MM-YYYY → YYYY-MM-DD.
        // Dibalik lebih dulu karena Carbon membaca 01/06/2026 sebagai Januari.
        $rapi = preg_replace('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', '$3-$2-$1', $mentah);

        try {
            return Carbon::parse($rapi)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Awal minggu (Senin) yang memuat sebuah tanggal.
     *
     * PENTING: foto jadwal disimpan dengan kunci tanggal SENIN, bukan tanggal
     * unggahnya — halaman Target Produksi mencarinya begitu
     * (`$scheduleDate = now()->startOfWeek(MONDAY)`), dan `jadwal:clear`
     * membersihkannya tiap Senin 01:00. Kalau bot memakai tanggal hari ini,
     * fotonya tersimpan di tanggal yang tidak pernah dicari siapa pun — terlihat
     * seperti "hilang besoknya".
     */
    public static function awalMinggu(?string $tanggal = null): string
    {
        $acuan = $tanggal ? Carbon::parse($tanggal) : now();

        return $acuan->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    /** "22 - 28 September 2026" */
    public static function rentangMinggu(string $awal): string
    {
        $mulai = Carbon::parse($awal)->locale('id');
        $akhir = $mulai->copy()->endOfWeek(Carbon::SUNDAY);

        return $mulai->isoFormat('D MMM') . ' – ' . $akhir->isoFormat('D MMM YYYY');
    }

    /** "Sabtu, 20 September 2026" */
    public static function panjang(string $tanggal): string
    {
        return Carbon::parse($tanggal)->locale('id')->isoFormat('dddd, D MMMM YYYY');
    }

    /** "20 Sep 2026" */
    public static function pendek(string $tanggal): string
    {
        return Carbon::parse($tanggal)->locale('id')->isoFormat('D MMM YYYY');
    }
}
