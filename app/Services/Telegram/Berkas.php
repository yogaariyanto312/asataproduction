<?php

namespace App\Services\Telegram;

/** Mengenali berkas yang dikirim ke bot. */
class Berkas
{
    /** Telegram Bot API tidak melayani berkas di atas 20 MB. */
    public const MAKS_BYTE = 20 * 1024 * 1024;

    /**
     * @return array{file_id:string,jenis:string,ekstensi:string}|null
     *         null kalau bukan foto maupun PDF.
     */
    public static function dariPesan(array $message): ?array
    {
        // Foto dikirim dalam beberapa ukuran; yang terakhir paling besar.
        if (! empty($message['photo'])) {
            $terbesar = end($message['photo']);

            return [
                'file_id'  => $terbesar['file_id'],
                'jenis'    => 'foto',
                'ekstensi' => 'jpg',
            ];
        }

        $dokumen = $message['document'] ?? null;

        if (! $dokumen) {
            return null;
        }

        // Berkas besar ditolak di sini, sebelum sempat diunduh.
        if (($dokumen['file_size'] ?? 0) > self::MAKS_BYTE) {
            return null;
        }

        $mime = $dokumen['mime_type'] ?? '';

        if ($mime === 'application/pdf') {
            return [
                'file_id'  => $dokumen['file_id'],
                'jenis'    => 'pdf',
                'ekstensi' => 'pdf',
            ];
        }

        // Gambar yang dikirim "sebagai berkas" (tanpa kompresi) tetap diterima.
        if (str_starts_with($mime, 'image/')) {
            return [
                'file_id'  => $dokumen['file_id'],
                'jenis'    => 'foto',
                'ekstensi' => match ($mime) {
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    default      => 'jpg',
                },
            ];
        }

        return null;
    }
}
