<?php

namespace App\Support;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Berkas rilis aplikasi native yang diunggah lewat skrip push ke storage/app/<platform>/
 * (di luar public & git, jadi tidak tersentuh deploy):
 *  - android : APK, dari android-update/push_update.py
 *  - windows : installer MSI, dari windows-update/push_update.py
 *
 * versi.json: {versionCode, versionName, berkas, ukuran, sha256, catatan, wajib, tanggal}
 */
class AplikasiAndroid
{
    /** Platform yang dikenal → nama route unduhnya. */
    public const PLATFORM = ['android' => 'unduh.aplikasi', 'windows' => 'unduh.aplikasi.windows'];

    public static function folder(string $platform = 'android'): string
    {
        return storage_path('app/'.self::platform($platform));
    }

    /** Platform tak dikenal dianggap android (perilaku lama endpoint versi). */
    public static function platform(?string $platform): string
    {
        return array_key_exists((string) $platform, self::PLATFORM) ? $platform : 'android';
    }

    /** Info rilis terbaru, atau null bila belum ada berkas yang diunggah. */
    public static function info(string $platform = 'android'): ?array
    {
        $platform = self::platform($platform);
        $json = self::folder($platform).'/versi.json';
        if (! is_file($json)) {
            return null;
        }
        $v = json_decode((string) file_get_contents($json), true);
        if (! is_array($v) || empty($v['berkas']) || ! is_file(self::berkas($v['berkas'], $platform))) {
            return null;
        }

        return [
            'platform'    => $platform,
            'versionCode' => (int) ($v['versionCode'] ?? 0),
            'versionName' => (string) ($v['versionName'] ?? ''),
            'ukuran'      => (int) ($v['ukuran'] ?? filesize(self::berkas($v['berkas'], $platform))),
            'sha256'      => (string) ($v['sha256'] ?? ''),
            'catatan'     => (string) ($v['catatan'] ?? ''),
            'wajib'       => (bool) ($v['wajib'] ?? false),
            'tanggal'     => (string) ($v['tanggal'] ?? ''),
            'berkas'      => basename($v['berkas']),
            'url'         => route(self::PLATFORM[$platform]),
        ];
    }

    /** Path berkas; basename() supaya isi versi.json tidak bisa menunjuk ke luar folder. */
    public static function berkas(string $nama, string $platform = 'android'): string
    {
        return self::folder($platform).'/'.basename($nama);
    }

    /** Modul QR sebagai baris "0101…" — dirender sendiri oleh web (SVG) & Android (Canvas). */
    public static function qr(string $teks): array
    {
        $qr = new QRCode(new QROptions(['addQuietzone' => false]));
        $qr->addByteSegment($teks);

        return array_map(
            fn (array $baris) => implode('', array_map(fn ($m) => $m ? '1' : '0', $baris)),
            $qr->getQRMatrix()->getMatrix(true),
        );
    }

    /** Data untuk kartu "Unduh Aplikasi" di halaman Tentang. */
    public static function kartu(): ?array
    {
        $info = self::info();

        return $info ? $info + ['qr' => self::qr($info['url'])] : null;
    }
}
