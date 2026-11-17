<?php

namespace App\Support;

/**
 * Rentang nomor urut untuk rekap bulanan.
 *
 * Tiap catatan produksi menyimpan nomor urut hariannya, misalnya
 * "UP NO.893-895" dan "BT NO.915-927". Rekap bulanan dulu cuma menampilkan
 * catatan hari terakhir, jadi angkanya terlihat tidak nyambung dengan total
 * sebulan — produk yang dikerjakan lima hari tetap menampilkan rentang satu
 * hari saja.
 *
 * Di sini semua catatan sebulan dibaca, lalu diambil nomor terkecil dan
 * terbesar per awalan (UP / BT / tanpa awalan), sehingga yang tampil adalah
 * rentang sejak awal bulan sampai catatan terakhir.
 */
class NomorUrut
{
    /** Urutan tampil awalan. Sisanya menyusul sesuai abjad. */
    private const URUTAN = ['' => 0, 'UP' => 1, 'BT' => 2];

    /** Lebar minimal angka, mengikuti format yang dipakai form input. */
    private const LEBAR_MIN = 3;

    /**
     * @param  iterable<string|null>  $catatan  isi kolom notes sepanjang bulan
     */
    public static function rentang(iterable $catatan): string
    {
        $rentang = [];   // awalan => ['awal' => int, 'akhir' => int, 'lebar' => int]
        $lainnya = [];   // baris yang bukan rentang nomor

        foreach ($catatan as $isi) {
            foreach (self::baris($isi) as $baris) {
                if (! preg_match('/^(.*?)NO\.\s*(\d+)\s*(?:-\s*(\d+))?$/i', $baris, $cocok)) {
                    $lainnya[$baris] = true;

                    continue;
                }

                $awalan = strtoupper(trim($cocok[1]));
                $awal   = (int) $cocok[2];
                $akhir  = (int) ($cocok[3] ?? $cocok[2]);
                $lebar  = max(strlen($cocok[2]), strlen($cocok[3] ?? ''));

                if (! isset($rentang[$awalan])) {
                    $rentang[$awalan] = ['awal' => $awal, 'akhir' => $akhir, 'lebar' => $lebar];

                    continue;
                }

                $rentang[$awalan]['awal']  = min($rentang[$awalan]['awal'], $awal);
                $rentang[$awalan]['akhir'] = max($rentang[$awalan]['akhir'], $akhir);
                $rentang[$awalan]['lebar'] = max($rentang[$awalan]['lebar'], $lebar);
            }
        }

        if ($rentang === []) {
            // Tidak ada satu pun yang berformat nomor urut — tampilkan apa adanya.
            return implode("\n", array_keys($lainnya));
        }

        uksort($rentang, fn ($a, $b) => [self::prioritas($a), $a] <=> [self::prioritas($b), $b]);

        $keluar = [];
        foreach ($rentang as $awalan => $nilai) {
            $lebar = max(self::LEBAR_MIN, $nilai['lebar']);
            $teks  = 'NO.' . str_pad((string) $nilai['awal'], $lebar, '0', STR_PAD_LEFT)
                   . '-' . str_pad((string) $nilai['akhir'], $lebar, '0', STR_PAD_LEFT);

            $keluar[] = $awalan === '' ? $teks : $awalan . ' ' . $teks;
        }

        return implode("\n", $keluar);
    }

    /** Pecah isi catatan jadi baris-baris bersih. */
    private static function baris(?string $isi): array
    {
        if ($isi === null || trim($isi) === '') {
            return [];
        }

        $baris = preg_split('/\r\n|\r|\n/', $isi) ?: [];

        return array_values(array_filter(array_map('trim', $baris), fn ($b) => $b !== ''));
    }

    private static function prioritas(string $awalan): int
    {
        return self::URUTAN[$awalan] ?? 9;
    }
}
