<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Pembuat versi kecil sebuah gambar, dipakai untuk kartu di daftar gambar kerja.
 *
 * Thumbnail yang diunggah operator adalah foto apa adanya dari kamera/scan —
 * pernah terukur 3011x2095 piksel (854 KB) padahal kartunya hanya setinggi
 * 144 px. Satu halaman daftar bisa menarik puluhan MB, dan itu paling terasa
 * di HP. Turunan dibuat sekali lalu disimpan, jadi permintaan berikutnya hanya
 * membaca berkas kecil.
 */
class ImageThumbnail
{
    /** Lebar maksimum turunan; cukup tajam untuk kartu maupun layar beresolusi tinggi. */
    public const LEBAR = 640;

    /** Untuk foto yang memang dilihat isinya (mis. foto catatan), bukan sekadar kartu. */
    public const LEBAR_FOTO = 1280;

    private const DIR = 'gambar-kerja/derived';

    /** Ekstensi yang bisa diproses GD. */
    private const DIDUKUNG = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Kembalikan path turunan (relatif disk `public`), membuatnya bila belum ada.
     * Mengembalikan null bila sumbernya tidak bisa diproses — pemanggil lalu
     * memakai berkas aslinya, sehingga gambar tetap tampil.
     */
    public static function untuk(string $sourcePath, ?int $lebar = null): ?string
    {
        $lebar = $lebar ?: self::LEBAR;
        $disk  = Storage::disk('public');

        if (! $disk->exists($sourcePath)) {
            return null;
        }

        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (! in_array($ext, self::DIDUKUNG, true) || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $absolut = $disk->path($sourcePath);

        // Nama turunan ikut waktu ubah berkas sumber: kalau sumbernya diganti,
        // nama turunannya ikut berubah sehingga cache lama tidak pernah tersaji.
        $kunci  = sha1($sourcePath . '|' . @filemtime($absolut) . '|' . $lebar);
        $tujuan = self::DIR . '/' . $kunci . '.jpg';

        if ($disk->exists($tujuan)) {
            return $tujuan;
        }

        $hasil = self::kecilkan($absolut, $ext, $lebar);
        if ($hasil === null) {
            return null;
        }

        $disk->put($tujuan, $hasil);

        return $tujuan;
    }

    /**
     * Buang turunan milik sebuah berkas. Dipanggil SEBELUM berkas sumber dihapus,
     * karena nama turunan ikut waktu-ubah sumber — setelah sumbernya hilang,
     * namanya tidak bisa dihitung lagi dan berkas turunan akan menumpuk diam-diam.
     */
    public static function hapusTurunan(?string $sourcePath, ?int $lebar = null): void
    {
        if (! $sourcePath) {
            return;
        }

        $lebar = $lebar ?: self::LEBAR;

        $disk = Storage::disk('public');
        if (! $disk->exists($sourcePath)) {
            return;
        }

        $kunci  = sha1($sourcePath . '|' . @filemtime($disk->path($sourcePath)) . '|' . $lebar);
        $tujuan = self::DIR . '/' . $kunci . '.jpg';

        if ($disk->exists($tujuan)) {
            $disk->delete($tujuan);
        }
    }

    /** Ubah gambar jadi JPEG kecil. Null bila GD gagal membacanya. */
    private static function kecilkan(string $absolut, string $ext, int $lebar): ?string
    {
        $info = @getimagesize($absolut);
        if (! $info) {
            return null;
        }

        [$lebarAsli, $tinggi] = $info;
        if ($lebarAsli < 1 || $tinggi < 1) {
            return null;
        }

        $sumber = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($absolut),
            'png'         => @imagecreatefrompng($absolut),
            'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolut) : false,
            default       => false,
        };

        if (! $sumber) {
            return null;
        }

        // Gambar yang sudah kecil tidak diperbesar — hanya dikonversi & dikompres.
        $skala      = min(1, $lebar / $lebarAsli);
        $lebarBaru  = max(1, (int) round($lebarAsli * $skala));
        $tinggiBaru = max(1, (int) round($tinggi * $skala));

        $tujuan = imagecreatetruecolor($lebarBaru, $tinggiBaru);

        // PNG/WebP bisa transparan; ratakan ke putih supaya tidak jadi bidang hitam.
        $putih = imagecolorallocate($tujuan, 255, 255, 255);
        imagefilledrectangle($tujuan, 0, 0, $lebarBaru, $tinggiBaru, $putih);
        imagecopyresampled($tujuan, $sumber, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebarAsli, $tinggi);

        ob_start();
        imagejpeg($tujuan, null, 75);
        $biner = ob_get_clean();

        imagedestroy($sumber);
        imagedestroy($tujuan);

        return $biner ?: null;
    }
}
