<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Inertia\Inertia;
use App\Models\BotSetting;
use App\Services\BotNotificationService;
use App\Services\Telegram\BotPerintah;
use Illuminate\Http\Request;

class BotSettingController extends Controller
{
    /** Field teks: dikosongkan menjadi null, bukan string kosong. */
    private const TEKS = [
        'telegram_token',
        'telegram_chat_id',
        'telegram_report_chat_id',
        'discord_webhook',
        'maintenance_message',
    ];

    /** Field saklar. */
    private const SAKLAR = [
        'telegram_enabled',
        'discord_enabled',
        'report_enabled',
        'disable_devtools',
        'maintenance_mode',
    ];

    public function index()
    {
        $setting = BotSetting::instance();

        return Inertia::render('Settings/Bot', [
            'action'      => route('developer.bot-settings.update'),
            'testUrl'     => route('developer.bot-settings.test'),
            'webhookRegisterUrl' => route('developer.bot-settings.webhook-register'),
            'webhookInfoUrl'     => route('developer.bot-settings.webhook-info'),
            'dailyReportUrl'     => route('developer.bot-settings.send-daily-report'),
            'setting'     => [
                'telegram_token'          => $setting->telegram_token,
                'telegram_chat_id'        => $setting->telegram_chat_id,
                'telegram_report_chat_id' => $setting->telegram_report_chat_id,
                'telegram_enabled'        => (bool) $setting->telegram_enabled,
                'discord_webhook'         => $setting->discord_webhook,
                'discord_enabled'         => (bool) $setting->discord_enabled,
                'reject_threshold'        => (float) $setting->reject_threshold,
                'report_enabled'          => (bool) $setting->report_enabled,
                'disable_devtools'        => (bool) $setting->disable_devtools,
                'maintenance_mode'        => (bool) $setting->maintenance_mode,
                'maintenance_message'     => $setting->maintenance_message,
                // Format isian datetime-local, seperti referensi.
                'maintenance_until'       => $setting->maintenance_until?->format('Y-m-d\TH:i'),
            ],
            'maintenanceUntilLabel' => $setting->maintenance_until
                ? $setting->maintenance_until->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB'
                : null,
            'webhookUrl'  => url('/telegram/webhook'),
            // Daftar perintah diambil dari BotPerintah supaya tidak ada dua daftar
            // yang bisa berbeda diam-diam.
            'perintah'    => collect(BotPerintah::daftar())->map(fn ($p) => ['arti' => $p[0], 'contoh' => $p[2]])->values(),
            'profil'      => [
                'handle'         => auth()->user()->handle,
                'bio'            => auth()->user()->bio,
                'link_instagram' => auth()->user()->link_instagram,
                'link_github'    => auth()->user()->link_github,
                'link_portfolio' => auth()->user()->link_portfolio,
                'link_email'     => auth()->user()->link_email,
                'foto'           => auth()->user()->about_avatar ? route('storage.file', ['path' => auth()->user()->about_avatar]) : null,
            ],
            'aboutInfoUrl'   => route('profile.about-info'),
            'aboutAvatarUrl' => route('profile.about-avatar'),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'telegram_token'          => ['nullable', 'string', 'max:255', 'regex:/^\d+:[A-Za-z0-9_-]{20,}$/'],
            'telegram_chat_id'        => ['nullable', 'string', 'max:100', 'regex:/^(-?\d+|@[A-Za-z0-9_]{4,})$/'],
            'telegram_report_chat_id' => ['nullable', 'string', 'max:100', 'regex:/^(-?\d+|@[A-Za-z0-9_]{4,})$/'],
            'telegram_enabled'        => ['nullable', 'boolean'],
            'discord_webhook'         => ['nullable', 'url', 'max:500'],
            'discord_enabled'         => ['nullable', 'boolean'],
            'reject_threshold'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'report_enabled'          => ['nullable', 'boolean'],
            'disable_devtools'        => ['nullable', 'boolean'],
            'maintenance_mode'        => ['nullable', 'boolean'],
            'maintenance_message'     => ['nullable', 'string', 'max:500'],
            'maintenance_until'       => ['nullable', 'date'],
        ], [
            'telegram_token.regex'           => 'Format Bot Token tidak sesuai. Contoh dari BotFather: 1234567890:AAF-xxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'telegram_chat_id.regex'         => 'Chat ID harus berupa angka (boleh diawali -) atau @namachannel.',
            'telegram_report_chat_id.regex'  => 'Chat ID laporan harus berupa angka (boleh diawali -) atau @namachannel.',
            'discord_webhook.url'            => 'Webhook URL harus berupa URL yang valid (dimulai dengan https://).',
            'reject_threshold.numeric'       => 'Batas reject harus berupa angka.',
            'reject_threshold.min'           => 'Batas reject minimal 0%.',
            'reject_threshold.max'           => 'Batas reject maksimal 100%.',
        ]);

        $setting = BotSetting::instance();
        $isian   = $this->isianYangDikirim($request);

        // Hanya field yang benar-benar dikirim yang disentuh.
        //
        // Dulu semua field ditulis ulang tiap kali menyimpan, dengan
        // `$request->boolean(...)` yang bernilai false untuk field yang tidak
        // ada. Akibatnya menyimpan satu bagian saja DIAM-DIAM mematikan bagian
        // lain: menyimpan Telegram mematikan Laporan Harian, Maintenance, dan
        // DevTools sekaligus mereset batas reject ke 5. Halamannya menambal itu
        // dengan menyalin semua field sebagai <input hidden> di tiap form —
        // tambalan yang bocor karena form Telegram tidak punya salinan itu.
        $setting->update($isian);

        ActivityLog::record('update', $this->ringkasan($isian));

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }

    /** @return array<string,mixed> */
    private function isianYangDikirim(Request $request): array
    {
        $isian = [];

        foreach (self::TEKS as $kolom) {
            if ($request->has($kolom)) {
                $isian[$kolom] = $request->input($kolom) ?: null;
            }
        }

        foreach (self::SAKLAR as $kolom) {
            if ($request->has($kolom)) {
                $isian[$kolom] = $request->boolean($kolom);
            }
        }

        if ($request->has('reject_threshold')) {
            $nilai = $request->input('reject_threshold');
            $isian['reject_threshold'] = ($nilai === null || $nilai === '') ? 5.0 : $nilai;
        }

        if ($request->has('maintenance_until')) {
            $isian['maintenance_until'] = $request->input('maintenance_until') ?: null;
        }

        return $isian;
    }

    /**
     * Catatan aktivitas menyebut apa yang diubah, tanpa pernah memuat token atau
     * URL webhook — keduanya rahasia dan catatan aktivitas ikut terkirim ke bot.
     */
    private function ringkasan(array $isian): string
    {
        $rahasia = ['telegram_token', 'discord_webhook'];
        $bagian  = [];

        foreach ($isian as $kolom => $nilai) {
            if (in_array($kolom, $rahasia, true)) {
                $bagian[] = $kolom . ' (disembunyikan)';
                continue;
            }

            $bagian[] = $kolom . '=' . match (true) {
                is_bool($nilai) => $nilai ? 'aktif' : 'nonaktif',
                $nilai === null => 'kosong',
                default         => (string) $nilai,
            };
        }

        return 'Ubah pengaturan aplikasi: ' . implode(', ', $bagian);
    }

    public function registerWebhook()
    {
        $setting = BotSetting::instance();

        if (! $setting->telegram_token) {
            return back()->with('error', 'Token bot belum diisi. Isi dan simpan Bot Token Telegram terlebih dahulu.');
        }

        $webhookUrl = url('/telegram/webhook');

        // secret_token WAJIB ikut didaftarkan. Sebelumnya tidak dikirim sama
        // sekali, sehingga muncul dua keadaan yang sama-sama buruk:
        //  - TELEGRAM_WEBHOOK_SECRET kosong  -> endpoint webhook terbuka, siapa
        //    pun yang menebak URL-nya bisa mengirim perintah palsu;
        //  - TELEGRAM_WEBHOOK_SECRET terisi  -> Telegram tidak pernah mengirim
        //    header itu, semua perintah ditolak 403, bot mati tanpa penjelasan.
        $rahasia = config('services.telegram.webhook_secret');

        try {
            $res = BotNotificationService::klien(15)
                ->post("https://api.telegram.org/bot{$setting->telegram_token}/setWebhook", array_filter([
                    'url'             => $webhookUrl,
                    // callback_query WAJIB ada, kalau tidak penekanan tombol
                    // (mis. pilihan kategori gambar kerja) tidak pernah sampai ke server.
                    'allowed_updates' => ['message', 'callback_query'],
                    'secret_token'    => $rahasia,
                ]))->json();
        } catch (\Throwable $e) {
            return back()->with('error', 'Koneksi ke Telegram gagal: ' . $e->getMessage());
        }

        if ($res['ok'] ?? false) {
            $catatan = $rahasia
                ? 'Webhook terdaftar dan dilindungi secret token.'
                : 'Webhook terdaftar, TAPI tanpa secret token — isi TELEGRAM_WEBHOOK_SECRET di .env lalu daftarkan ulang.';

            return back()->with('success', "{$catatan} → {$webhookUrl}");
        }

        $desc = $res['description'] ?? 'Unknown error';
        if (str_contains($desc, 'HTTPS')) {
            $desc = 'URL webhook harus HTTPS. Pastikan aplikasi berjalan di server dengan SSL.';
        }

        return back()->with('error', "Gagal mendaftarkan webhook: {$desc}");
    }

    public function webhookInfo()
    {
        $setting = BotSetting::instance();

        if (! $setting->telegram_token) {
            return back()->with('error', 'Token bot belum diisi.');
        }

        try {
            $res = BotNotificationService::klien(15)
                ->get("https://api.telegram.org/bot{$setting->telegram_token}/getWebhookInfo")
                ->json();
        } catch (\Throwable $e) {
            return back()->with('error', 'Koneksi ke Telegram gagal: ' . $e->getMessage());
        }

        if (! ($res['ok'] ?? false)) {
            return back()->with('error', 'Token tidak valid atau koneksi gagal.');
        }

        $info    = $res['result'] ?? [];
        $url     = $info['url'] ?: '(belum terdaftar)';
        $pending = $info['pending_update_count'] ?? 0;
        $lastErr = $info['last_error_message'] ?? null;

        $msg = "URL: {$url} · Pending: {$pending}";
        if ($lastErr) {
            $msg .= " · Error: {$lastErr}";
        }

        return back()->with('webhook_info', $msg);
    }

    public function sendDailyReport()
    {
        $result = BotNotificationService::sendDailyReport();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function test(Request $request)
    {
        // Dulu tipe apa pun diteruskan ke service dan dijawab "Tipe bot tidak
        // dikenali" — divalidasi di sini supaya pesannya jelas dan tidak ada
        // masukan bebas yang menembus ke service.
        $request->validate([
            'type' => ['required', 'in:telegram,discord'],
        ], [
            'type.in' => 'Tipe bot hanya boleh telegram atau discord.',
        ]);

        $result = BotNotificationService::test($request->input('type'), BotSetting::instance());

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
