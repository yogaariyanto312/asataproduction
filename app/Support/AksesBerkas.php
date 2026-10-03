<?php

namespace App\Support;

use App\Models\Note;
use App\Models\User;

/**
 * Siapa boleh mengambil berkas apa lewat /file/{path} dan /thumb/{path}.
 *
 * Sebelumnya kedua route itu hanya butuh login: siapa pun yang sudah masuk bisa
 * mengunduh berkas apa pun di disk `public` asal tahu path-nya — termasuk PDF
 * gambar kerja yang tombol unduhnya sengaja disembunyikan untuk role tertentu.
 *
 * Izinnya ditentukan dari FOLDER PERTAMA path-nya, karena satu route melayani
 * beberapa fitur sekaligus sehingga tidak bisa dipetakan ke satu permission key
 * lewat config/menus.php seperti route lain.
 *
 * Folder yang tidak dikenal DITOLAK, bukan dibiarkan lewat. Konsekuensinya:
 * menambah folder unggahan baru berarti wajib mendaftarkannya di sini — dijaga
 * oleh AksesBerkasTest::test_semua_folder_di_disk_public_sudah_dipetakan.
 */
final class AksesBerkas
{
    /**
     * Folder pertama => permission key menu yang mengaturnya.
     * null = cukup sudah login.
     */
    private const PETA = [
        'gambar-kerja'    => 'gambar-kerja',
        'schedule-photos' => 'targets',
        'notes'           => 'notes',

        // Foto profil muncul di mana-mana (sidebar, daftar kontak chat,
        // Manajemen, halaman Tentang). Menguncinya per menu hanya akan
        // membuat avatar hilang di layar orang yang berhak melihatnya.
        'avatars'         => null,

        // Tidak dirujuk kode mana pun lagi, tapi isinya bukan data produksi.
        'backgrounds'     => null,
    ];

    /** @return list<string> */
    public static function folderDikenal(): array
    {
        return array_keys(self::PETA);
    }

    public static function boleh(?User $user, string $path): bool
    {
        if (! $user) {
            return false;
        }

        // Developer selalu boleh, sama seperti di MenuAccess.
        if ($user->role === 'developer') {
            return true;
        }

        $folder = self::folder($path);

        if (! array_key_exists($folder, self::PETA)) {
            return false;
        }

        $key = self::PETA[$folder];

        if ($key === null) {
            return true;
        }

        if (MenuAccess::can($user, $key)) {
            return true;
        }

        // Kartu Catatan di Dashboard hanya menampilkan catatan milik atau yang
        // ditujukan ke orang itu, dan TIDAK dijaga izin menu Catatan. Visitor
        // misalnya boleh menerima catatan berfoto walau menu Catatan-nya
        // tertutup — tanpa jalan keluar ini, fotonya jadi 403 di dashboardnya.
        if ($folder === 'notes') {
            return self::terlibatDiCatatan($user, $path);
        }

        return false;
    }

    /** Folder pertama dari path, tanpa memedulikan sisanya. */
    private static function folder(string $path): string
    {
        $path = ltrim(str_replace(chr(92), '/', $path), '/');
        $potong = strpos($path, '/');

        return $potong === false ? $path : substr($path, 0, $potong);
    }

    private static function terlibatDiCatatan(User $user, string $path): bool
    {
        return Note::query()
            ->where('photo_path', $path)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('target_user_id', $user->id);
            })
            ->exists();
    }
}
