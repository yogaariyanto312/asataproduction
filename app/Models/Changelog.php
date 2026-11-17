<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Changelog extends Model
{
    protected $fillable = ['version', 'type', 'title', 'description'];

    /** Dipakai kalau belum ada satu pun entri yang mencantumkan versi. */
    public const VERSI_AWAL = 'v1.0';

    /**
     * Versi aplikasi = versi entri Riwayat Update paling baru yang mencantumkannya.
     *
     * Dulu angka versinya ditulis langsung di halaman About ("Versi 1.0") dan
     * tidak pernah ikut berubah saat riwayat bertambah. Sekarang satu sumber.
     *
     * Kolom `version` boleh kosong, jadi entri tanpa versi dilewati — bukan
     * dianggap "tidak ada versi". Urutannya disamakan dengan daftar di layar
     * (created_at lalu id), karena beberapa entri bisa lahir di detik yang sama.
     */
    public static function versiAplikasi(): string
    {
        $versi = static::query()
            ->whereNotNull('version')
            ->where('version', '!=', '')
            ->latest()
            ->latest('id')
            ->value('version');

        $versi = trim((string) $versi);

        if ($versi === '') {
            return self::VERSI_AWAL;
        }

        // Disimpan sebagai teks bebas: "1.4" dan "v1.4" sama-sama mungkin.
        return str_starts_with(strtolower($versi), 'v') ? $versi : 'v' . $versi;
    }
}
