<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
use App\Models\User;
use App\Services\Telegram\BotPerintah;
use App\Services\Telegram\Penyimpanan;
use App\Services\Telegram\Pengirim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Pintu masuk pesan Telegram.
 *
 * Sengaja tipis: hanya memastikan permintaannya sah, menyerahkan isinya ke
 * BotPerintah, lalu mengirimkan balasannya. Seluruh aturan perintah ada di
 * BotPerintah supaya bisa diuji tanpa jaringan.
 */
class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Endpoint ini publik dan dikecualikan dari CSRF, jadi tanpa secret
        // siapa pun yang menebak URL-nya bisa mengirim update palsu seolah dari
        // Telegram. Secret-nya didaftarkan bersamaan saat menekan "Daftarkan
        // Webhook" di menu Settings.
        $rahasia = config('services.telegram.webhook_secret');

        if ($rahasia) {
            $dikirim = $request->header('X-Telegram-Bot-Api-Secret-Token');

            if (! is_string($dikirim) || ! hash_equals($rahasia, $dikirim)) {
                abort(403);
            }
        }

        $setting = BotSetting::instance();

        if (! $setting->telegram_token) {
            return response()->json(['ok' => true]);
        }

        $pengirim = new Pengirim($setting->telegram_token);
        $perintah = new BotPerintah($setting);

        // Penekanan tombol datang sebagai callback_query, bukan message.
        if (is_array($tombol = $request->input('callback_query'))) {
            $chatId = $tombol['message']['chat']['id'] ?? null;

            // WAJIB dijawab, kalau tidak tombolnya terus berputar di layar
            // orang seolah botnya menggantung.
            $pengirim->jawabTombol((string) ($tombol['id'] ?? ''));

            if ($chatId !== null) {
                if ($pesanId = $tombol['message']['message_id'] ?? null) {
                    $pengirim->hapusTombol($chatId, (int) $pesanId);
                }

                foreach ($perintah->tanganiTombol($tombol) as $balasan) {
                    $this->kirim($pengirim, $chatId, $balasan);
                }
            }

            return response()->json(['ok' => true]);
        }

        $message = $request->input('message');

        if (! is_array($message)) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'] ?? null;

        if ($chatId === null) {
            return response()->json(['ok' => true]);
        }

        foreach ($perintah->tangani($message) as $balasan) {
            $this->kirim($pengirim, $chatId, $balasan);
        }

        // Telegram mengulang pengiriman kalau jawabannya bukan 2xx, jadi jawaban
        // selalu ok — kegagalan di dalam sudah disampaikan lewat pesan balasan.
        return response()->json(['ok' => true]);
    }

    private function kirim(Pengirim $pengirim, int|string $chatId, array $balasan): void
    {
        if (($balasan['jenis'] ?? null) !== 'unduh') {
            $pengirim->kirimBalasan($chatId, $balasan);
            return;
        }

        $this->simpanBerkas($pengirim, $chatId, $balasan);
    }

    /**
     * Mengunduh berkas dari Telegram lalu menyimpannya sesuai sesi yang sedang
     * berjalan. Dipisah ke sini karena butuh jaringan — BotPerintah sengaja
     * tidak menyentuh jaringan sama sekali.
     */
    private function simpanBerkas(Pengirim $pengirim, int|string $chatId, array $balasan): void
    {
        $unduhan = $pengirim->unduhBerkas($balasan['berkas']['file_id']);

        if ($unduhan === null) {
            $pengirim->kirimTeks($chatId, '❌ Gagal mengunduh berkas dari Telegram. Coba kirim ulang.');
            return;
        }

        [$isi, $ekstensiAsli] = $unduhan;

        $sesi      = $balasan['sesi'];
        $pengguna  = User::find($sesi['user_id'] ?? null);

        if (! $pengguna || ! $pengguna->is_active) {
            Cache::forget($balasan['kunci']);
            $pengirim->kirimTeks($chatId, '❌ Akun pengunggah sudah tidak aktif.');
            return;
        }

        // Ekstensi diambil dari hasil pengenalan jenis, bukan dari nama berkas
        // yang dikirim orang — nama berkas bisa saja menyesatkan.
        $ekstensi = $balasan['berkas']['ekstensi'] ?: $ekstensiAsli;

        $pesan = $sesi['jenis'] === 'jadwal'
            ? Penyimpanan::jadwal($isi, $ekstensi, $sesi['tanggal'], $pengguna)
            : Penyimpanan::gambarKerja($isi, $ekstensi, $sesi, $pengguna);

        // Sesi ditutup SETELAH berhasil disimpan, supaya kegagalan di tengah
        // jalan tidak memaksa orang mengetik perintahnya dari awal. Gambar kerja
        // sengaja dibiarkan terbuka: satu judul sering terdiri dari beberapa
        // lembar, jadi orangnya bisa langsung mengirim berkas berikutnya.
        if ($balasan['tutup'] ?? true) {
            Cache::forget($balasan['kunci']);
        } else {
            // Diperpanjang supaya tidak kedaluwarsa saat mengirim banyak berkas.
            Cache::put($balasan['kunci'], $sesi, now()->addMinutes(10));
        }

        $pengirim->kirimTeks($chatId, $pesan);
    }
}
