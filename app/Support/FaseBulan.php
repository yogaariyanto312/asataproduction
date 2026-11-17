<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Tanggal bulan baru & bulan purnama untuk sebuah bulan kalender.
 *
 * Dihitung sendiri (algoritma Meeus, "Astronomical Algorithms" bab 49) supaya
 * tidak bergantung layanan luar: kalender tetap menampilkan keterangannya walau
 * jaringan pabrik sedang tidak bisa keluar. Ketelitiannya beberapa menit —
 * jauh lebih dari cukup untuk menandai tanggal.
 *
 * Waktu dihitung dalam UTC lalu digeser ke zona waktu aplikasi, karena purnama
 * yang jatuh malam hari di UTC bisa berarti tanggal berikutnya di Indonesia.
 */
class FaseBulan
{
    /** Rata-rata panjang satu siklus bulan (sinodis), dalam hari. */
    private const SIKLUS = 29.530588861;

    /**
     * @return array<string,string> tanggal (Y-m-d) => 'purnama' | 'baru'
     */
    public static function untukBulan(int $tahun, int $bulan): array
    {
        $awal  = Carbon::create($tahun, $bulan, 1, 0, 0, 0, config('app.timezone'))->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();

        // k menomori siklus bulan sejak tahun 2000; dilebihkan sedikit di kedua
        // ujung supaya fase di awal/akhir bulan tidak terlewat.
        $kAwal = (int) floor(self::kUntuk($awal) - 1);
        $kHingga = (int) ceil(self::kUntuk($akhir) + 1);

        $hasil = [];

        for ($k = $kAwal; $k <= $kHingga; $k++) {
            foreach (['baru' => 0.0, 'purnama' => 0.5] as $nama => $offset) {
                $waktu = self::waktuFase($k + $offset);

                if ($waktu->lt($awal) || $waktu->gt($akhir)) {
                    continue;
                }

                $hasil[$waktu->toDateString()] = $nama;
            }
        }

        ksort($hasil);

        return $hasil;
    }

    /** Perkiraan nomor siklus untuk sebuah tanggal. */
    private static function kUntuk(Carbon $tanggal): float
    {
        $tahunDesimal = $tanggal->year + ($tanggal->dayOfYear / 365.25);

        return ($tahunDesimal - 2000) * 12.3685;
    }

    /** Waktu terjadinya fase (k bulat = bulan baru, k+0.5 = purnama). */
    private static function waktuFase(float $k): Carbon
    {
        $t = $k / 1236.85; // abad Julian sejak epoch 2000

        // Waktu rata-rata fase (JDE)
        $jde = 2451550.09766
            + self::SIKLUS * $k
            + 0.00015437 * $t ** 2
            - 0.000000150 * $t ** 3
            + 0.00000000073 * $t ** 4;

        $m      = deg2rad(2.5534 + 29.10535670 * $k - 0.0000014 * $t ** 2);        // anomali matahari
        $mAksen = deg2rad(201.5643 + 385.81693528 * $k + 0.0107582 * $t ** 2);     // anomali bulan
        $f      = deg2rad(160.7108 + 390.67050284 * $k - 0.0016118 * $t ** 2);     // argumen lintang
        $omega  = deg2rad(124.7746 - 1.56375588 * $k + 0.0020672 * $t ** 2);

        $purnama = abs($k - floor($k) - 0.5) < 0.01;

        // Koreksi suku-suku terbesar; sudah cukup untuk ketelitian menit.
        if ($purnama) {
            $koreksi = -0.40614 * sin($mAksen)
                + 0.17302 * sin($m)
                + 0.01614 * sin(2 * $mAksen)
                + 0.01043 * sin(2 * $f)
                + 0.00734 * sin($mAksen - $m)
                - 0.00515 * sin($mAksen + $m)
                + 0.00209 * sin(2 * $m)
                - 0.00111 * sin($mAksen - 2 * $f)
                - 0.00057 * sin($mAksen + 2 * $f);
        } else {
            $koreksi = -0.40720 * sin($mAksen)
                + 0.17241 * sin($m)
                + 0.01608 * sin(2 * $mAksen)
                + 0.01039 * sin(2 * $f)
                + 0.00739 * sin($mAksen - $m)
                - 0.00514 * sin($mAksen + $m)
                + 0.00208 * sin(2 * $m)
                - 0.00111 * sin($mAksen - 2 * $f)
                - 0.00057 * sin($mAksen + 2 * $f);
        }

        $koreksi += 0.000325 * sin(deg2rad(299.77 + 0.107408 * $k - 0.009173 * $t ** 2))
            + 0.000165 * sin(deg2rad(251.88 + 0.016321 * $k))
            + 0.000164 * sin(deg2rad(251.83 + 26.651886 * $k))
            + 0.000126 * sin(deg2rad(349.42 + 36.412478 * $k))
            + 0.000110 * sin(deg2rad(84.66 + 18.206239 * $k))
            - 0.000062 * sin(deg2rad(141.74 + 53.303771 * $k))
            + 0.000060 * sin(deg2rad(207.14 + 2.453732 * $k))
            + 0.000056 * sin(deg2rad(154.84 + 7.306860 * $k))
            + 0.000047 * sin(deg2rad(34.52 + 27.261239 * $k));

        $jde += $koreksi + 0.000325 * sin($omega) * 0; // omega dipakai lewat suku di atas

        return self::dariJulian($jde)->setTimezone(config('app.timezone'));
    }

    /** Ubah Julian Day ke waktu UTC. */
    private static function dariJulian(float $jd): Carbon
    {
        // 2440587.5 = Julian Day untuk 1970-01-01 00:00 UTC
        $detik = ($jd - 2440587.5) * 86400;

        return Carbon::createFromTimestampUTC((int) round($detik));
    }
}
