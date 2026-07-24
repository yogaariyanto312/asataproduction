<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
use App\Models\SchedulePhoto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Verifikasi secret token Telegram (jika dikonfigurasi) — cegah request palsu
        // dari pihak yang tahu URL webhook. Endpoint ini publik & CSRF-exempt.
        $expectedSecret = config('services.telegram.webhook_secret');
        if ($expectedSecret) {
            $providedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (!is_string($providedSecret) || !hash_equals($expectedSecret, $providedSecret)) {
                abort(403);
            }
        }

        $setting = BotSetting::instance();
        if (!$setting->telegram_token) {
            return response()->json(['ok' => true]);
        }

        $token   = $setting->telegram_token;
        $update  = $request->all();
        $message = $update['message'] ?? null;

        if (!$message) {
            return response()->json(['ok' => true]);
        }

        $chatId   = $message['chat']['id'];
        $text     = $message['text']     ?? null;
        $photos   = $message['photo']    ?? null;
        $document = $message['document'] ?? null;

        if ($text && str_starts_with($text, '/jadwal')) {
            $this->handleJadwal($token, $chatId, $text);
        } elseif ($text && str_starts_with($text, '/batal')) {
            $this->handleBatal($token, $chatId);
        } elseif ($photos) {
            $this->handlePhoto($token, $chatId, $photos);
        } elseif ($document) {
            $this->handleDocument($token, $chatId, $document);
        }

        return response()->json(['ok' => true]);
    }

    private function handleJadwal(string $token, int|string $chatId, string $text): void
    {
        $parts   = preg_split('/\s+/', trim($text), 2);
        $rawDate = $parts[1] ?? null;

        $date = null;
        if ($rawDate) {
            // normalise common separators so Carbon can parse DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD
            $normalised = preg_replace('/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/', '$3-$2-$1', $rawDate);
            try {
                $parsed = Carbon::parse($normalised);
                $date   = $parsed->toDateString();
            } catch (\Throwable) {
                $date = null;
            }
        }

        $date ??= now()->toDateString();

        // Store state: chat is awaiting a photo for this date (TTL 10 min)
        Cache::put("tg_jadwal_{$chatId}", $date, now()->addMinutes(10));

        $formatted = Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMMM YYYY');
        $this->send($token, $chatId,
            "📅 *Upload Jadwal*\n\n" .
            "Tanggal: *{$formatted}*\n\n" .
            "Silakan kirim *foto* atau *file PDF* jadwalnya sekarang.\n" .
            "Ketik /batal untuk membatalkan.\n\n" .
            "_Sesi berlaku 10 menit._",
            ['parse_mode' => 'Markdown']
        );
    }

    private function handleBatal(string $token, int|string $chatId): void
    {
        Cache::forget("tg_jadwal_{$chatId}");
        $this->send($token, $chatId, "❌ Upload dibatalkan.");
    }

    private function handlePhoto(string $token, int|string $chatId, array $photos): void
    {
        $date = Cache::get("tg_jadwal_{$chatId}");
        if (!$date) {
            return; // no active session — ignore
        }

        // Telegram gives photos in multiple sizes; pick the largest (last)
        $fileId = end($photos)['file_id'];

        $fileInfo = Http::withoutVerifying()
            ->get("https://api.telegram.org/bot{$token}/getFile", ['file_id' => $fileId])
            ->json();

        if (!($fileInfo['ok'] ?? false)) {
            $this->send($token, $chatId, "❌ Gagal mengambil file. Coba lagi.");
            return;
        }

        $tgFilePath = $fileInfo['result']['file_path'];
        $contents   = Http::withoutVerifying()
            ->get("https://api.telegram.org/file/bot{$token}/{$tgFilePath}")
            ->body();

        if (!$contents) {
            $this->send($token, $chatId, "❌ Gagal mengunduh foto.");
            return;
        }

        $ext      = pathinfo($tgFilePath, PATHINFO_EXTENSION) ?: 'jpg';
        $savePath = "schedule-photos/{$date}/tg_" . time() . ".{$ext}";

        // Delete old photo for this date if any
        $old = SchedulePhoto::where('target_date', $date)->first();
        if ($old) {
            Storage::disk('public')->delete($old->file_path);
        }

        Storage::disk('public')->put($savePath, $contents);

        SchedulePhoto::updateOrCreate(
            ['target_date' => $date],
            ['file_path' => $savePath, 'uploaded_by' => null]
        );

        Cache::forget("tg_jadwal_{$chatId}");

        $formatted = Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMMM YYYY');
        $this->send($token, $chatId,
            "✅ *Foto jadwal berhasil disimpan!*\n\n" .
            "Tanggal: *{$formatted}*\n\n" .
            "Foto dapat dilihat di halaman _Target Produksi_ pada tanggal tersebut.",
            ['parse_mode' => 'Markdown']
        );
    }

    private function handleDocument(string $token, int|string $chatId, array $document): void
    {
        $date = Cache::get("tg_jadwal_{$chatId}");
        if (!$date) {
            return; // no active session — ignore
        }

        $mimeType = $document['mime_type'] ?? '';
        if ($mimeType !== 'application/pdf') {
            $this->send($token, $chatId,
                "❌ File tidak didukung.\n\nHanya *foto* atau *file PDF* yang diterima.",
                ['parse_mode' => 'Markdown']
            );
            return;
        }

        // Cek ukuran file — Telegram Bot API max 20 MB
        $fileSize = $document['file_size'] ?? 0;
        if ($fileSize > 20 * 1024 * 1024) {
            $this->send($token, $chatId, "❌ File terlalu besar. Maksimal 20 MB.");
            return;
        }

        $fileId = $document['file_id'];

        $fileInfo = Http::withoutVerifying()
            ->get("https://api.telegram.org/bot{$token}/getFile", ['file_id' => $fileId])
            ->json();

        if (!($fileInfo['ok'] ?? false)) {
            $this->send($token, $chatId, "❌ Gagal mengambil file. Coba lagi.");
            return;
        }

        $tgFilePath = $fileInfo['result']['file_path'];
        $contents   = Http::withoutVerifying()
            ->get("https://api.telegram.org/file/bot{$token}/{$tgFilePath}")
            ->body();

        if (!$contents) {
            $this->send($token, $chatId, "❌ Gagal mengunduh file.");
            return;
        }

        $savePath = "schedule-photos/{$date}/tg_" . time() . ".pdf";

        // Hapus file lama untuk tanggal ini jika ada
        $old = SchedulePhoto::where('target_date', $date)->first();
        if ($old) {
            Storage::disk('public')->delete($old->file_path);
        }

        Storage::disk('public')->put($savePath, $contents);

        SchedulePhoto::updateOrCreate(
            ['target_date' => $date],
            ['file_path' => $savePath, 'uploaded_by' => null]
        );

        Cache::forget("tg_jadwal_{$chatId}");

        $formatted = Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMMM YYYY');
        $this->send($token, $chatId,
            "✅ *File PDF jadwal berhasil disimpan!*\n\n" .
            "Tanggal: *{$formatted}*\n\n" .
            "Dapat dilihat di halaman _Target Produksi_ pada tanggal tersebut.",
            ['parse_mode' => 'Markdown']
        );
    }

    private function send(string $token, int|string $chatId, string $text, array $extra = []): void
    {
        Http::withoutVerifying()->post(
            "https://api.telegram.org/bot{$token}/sendMessage",
            array_merge(['chat_id' => $chatId, 'text' => $text], $extra)
        );
    }
}
