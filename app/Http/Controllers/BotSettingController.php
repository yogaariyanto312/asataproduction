<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
use App\Services\BotNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class BotSettingController extends Controller
{
    public function index()
    {
        $setting = BotSetting::instance();
        return view('developer.bot-settings', compact('setting'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'telegram_token'          => ['nullable', 'string', 'max:255'],
            'telegram_chat_id'        => ['nullable', 'string', 'max:100'],
            'telegram_report_chat_id' => ['nullable', 'string', 'max:100'],
            'telegram_enabled'        => ['nullable', 'boolean'],
            'discord_webhook'      => ['nullable', 'url', 'max:500'],
            'discord_enabled'      => ['nullable', 'boolean'],
            'reject_threshold'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'report_enabled'       => ['nullable', 'boolean'],
            'disable_devtools'     => ['nullable', 'boolean'],
            'maintenance_mode'     => ['nullable', 'boolean'],
            'maintenance_message'  => ['nullable', 'string', 'max:500'],
            'maintenance_until'    => ['nullable', 'date'],
        ], [
            'discord_webhook.url'      => 'Webhook URL harus berupa URL yang valid (dimulai dengan https://).',
            'reject_threshold.numeric' => 'Batas reject harus berupa angka.',
            'reject_threshold.min'     => 'Batas reject minimal 0%.',
            'reject_threshold.max'     => 'Batas reject maksimal 100%.',
        ]);

        BotSetting::instance()->update([
            'telegram_token'          => $request->telegram_token ?: null,
            'telegram_chat_id'        => $request->telegram_chat_id ?: null,
            'telegram_report_chat_id' => $request->telegram_report_chat_id ?: null,
            'telegram_enabled'        => $request->boolean('telegram_enabled'),
            'discord_webhook'     => $request->discord_webhook ?: null,
            'discord_enabled'     => $request->boolean('discord_enabled'),
            'reject_threshold'    => $request->input('reject_threshold', 5.0),
            'report_enabled'      => $request->boolean('report_enabled'),
            'disable_devtools'    => $request->boolean('disable_devtools'),
            'maintenance_mode'    => $request->boolean('maintenance_mode'),
            'maintenance_message' => $request->maintenance_message ?: null,
            'maintenance_until'   => $request->maintenance_until ?: null,
        ]);

        return back()->with('success', 'Pengaturan bot berhasil disimpan.');
    }

    public function registerWebhook(Request $request)
    {
        $setting = BotSetting::instance();
        if (!$setting->telegram_token) {
            return back()->with('error', 'Token bot belum diisi. Isi dan simpan Bot Token Telegram terlebih dahulu.');
        }

        $webhookUrl = url('/telegram/webhook');

        try {
            $res = Http::withoutVerifying()
                ->timeout(15)
                ->post("https://api.telegram.org/bot{$setting->telegram_token}/setWebhook", [
                    'url'             => $webhookUrl,
                    'allowed_updates' => ['message'],
                ])->json();
        } catch (\Throwable $e) {
            return back()->with('error', 'Koneksi ke Telegram gagal: ' . $e->getMessage());
        }

        if ($res['ok'] ?? false) {
            return back()->with('success', "Webhook berhasil didaftarkan → {$webhookUrl}");
        }

        $desc = $res['description'] ?? 'Unknown error';
        if (str_contains($desc, 'HTTPS')) {
            $desc = 'URL webhook harus HTTPS. Pastikan aplikasi berjalan di server dengan SSL.';
        }
        return back()->with('error', "Gagal mendaftarkan webhook: {$desc}");
    }

    public function webhookInfo(Request $request)
    {
        $setting = BotSetting::instance();
        if (!$setting->telegram_token) {
            return back()->with('error', 'Token bot belum diisi.');
        }

        try {
            $res = Http::withoutVerifying()
                ->timeout(15)
                ->get("https://api.telegram.org/bot{$setting->telegram_token}/getWebhookInfo")
                ->json();
        } catch (\Throwable $e) {
            return back()->with('error', 'Koneksi ke Telegram gagal: ' . $e->getMessage());
        }

        if (!($res['ok'] ?? false)) {
            return back()->with('error', 'Token tidak valid atau koneksi gagal.');
        }

        $info    = $res['result'] ?? [];
        $url     = $info['url'] ?: '(belum terdaftar)';
        $pending = $info['pending_update_count'] ?? 0;
        $lastErr = $info['last_error_message'] ?? null;

        $msg = "URL: {$url} · Pending: {$pending}";
        if ($lastErr) $msg .= " · Error: {$lastErr}";

        return back()->with('webhook_info', $msg);
    }

    public function sendDailyReport()
    {
        $result = BotNotificationService::sendDailyReport();
        $key    = $result['ok'] ? 'success' : 'error';
        return back()->with($key, $result['message']);
    }

    public function test(Request $request)
    {
        $type    = $request->input('type');
        $setting = BotSetting::instance();
        $result  = BotNotificationService::test($type, $setting);

        $key = $result['ok'] ? 'success' : 'error';
        return back()->with($key, $result['message']);
    }
}
