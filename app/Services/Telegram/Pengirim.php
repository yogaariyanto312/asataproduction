<?php

namespace App\Services\Telegram;

use App\Services\BotNotificationService;

/**
 * Semua percakapan keluar-masuk dengan Telegram lewat sini.
 *
 * Dipisah dari logika perintah supaya perintahnya bisa diuji tanpa jaringan:
 * BotPerintah hanya mengembalikan "apa yang harus dikirim", kelas ini yang
 * benar-benar mengirimkannya.
 */
class Pengirim
{
    public function __construct(private string $token) {}

    private function url(string $metode): string
    {
        return "https://api.telegram.org/bot{$this->token}/{$metode}";
    }

    /** Kirim satu balasan hasil BotPerintah. */
    public function kirimBalasan(int|string $chatId, array $balasan): void
    {
        match ($balasan['jenis'] ?? 'teks') {
            'foto'     => $this->kirimFoto($chatId, $balasan),
            'dokumen'  => $this->kirimDokumen($chatId, $balasan),
            default    => $this->kirimTeks($chatId, $balasan['teks'] ?? '', $balasan['tombol'] ?? null),
        };
    }

    public function kirimTeks(int|string $chatId, string $teks, ?array $tombol = null): void
    {
        $data = [
            'chat_id'    => $chatId,
            'text'       => $teks,
            'parse_mode' => 'HTML',
            // Pratinjau tautan membuat notifikasi jadi panjang dan mengalihkan
            // perhatian dari isinya.
            'link_preview_options' => json_encode(['is_disabled' => true]),
        ];

        if ($tombol) {
            $data['reply_markup'] = json_encode(['inline_keyboard' => $tombol]);
        }

        BotNotificationService::klien(8)->post($this->url('sendMessage'), $data);
    }

    private function kirimFoto(int|string $chatId, array $balasan): void
    {
        BotNotificationService::klien(20)
            ->attach('photo', $balasan['isi'], $balasan['nama'] ?? 'foto.jpg')
            ->post($this->url('sendPhoto'), array_filter([
                'chat_id'    => $chatId,
                'caption'    => $balasan['teks'] ?? null,
                'parse_mode' => 'HTML',
            ]));
    }

    private function kirimDokumen(int|string $chatId, array $balasan): void
    {
        BotNotificationService::klien(20)
            ->attach('document', $balasan['isi'], $balasan['nama'] ?? 'berkas.pdf')
            ->post($this->url('sendDocument'), array_filter([
                'chat_id'    => $chatId,
                'caption'    => $balasan['teks'] ?? null,
                'parse_mode' => 'HTML',
            ]));
    }

    /**
     * Jawab penekanan tombol.
     *
     * Telegram MEWAJIBKAN answerCallbackQuery: tanpa ini tombolnya terus
     * berputar di layar orang seolah botnya menggantung, padahal pekerjaannya
     * sudah selesai.
     */
    public function jawabTombol(string $callbackId, string $pesan = ''): void
    {
        BotNotificationService::klien(8)->post($this->url('answerCallbackQuery'), array_filter([
            'callback_query_id' => $callbackId,
            'text'              => $pesan ?: null,
        ]));
    }

    /** Buang tombol dari pesan yang sudah ditekan, supaya tidak bisa ditekan dua kali. */
    public function hapusTombol(int|string $chatId, int $messageId): void
    {
        BotNotificationService::klien(8)->post($this->url('editMessageReplyMarkup'), [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Unduh berkas yang dikirim ke bot. Mengembalikan [isi, ekstensi] atau null.
     *
     * Telegram memberi berkas dalam dua langkah: getFile untuk menukar file_id
     * dengan path, lalu mengunduh path itu.
     */
    public function unduhBerkas(string $fileId): ?array
    {
        $info = BotNotificationService::klien(15)
            ->get($this->url('getFile'), ['file_id' => $fileId])
            ->json();

        if (! ($info['ok'] ?? false)) {
            return null;
        }

        $path = $info['result']['file_path'] ?? null;
        if (! $path) {
            return null;
        }

        $isi = BotNotificationService::klien(30)
            ->get("https://api.telegram.org/file/bot{$this->token}/{$path}")
            ->body();

        if (! $isi) {
            return null;
        }

        return [$isi, strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'bin')];
    }
}
