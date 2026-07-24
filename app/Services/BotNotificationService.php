<?php

namespace App\Services;

use App\Models\BotSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class BotNotificationService
{
    private static array $icons = [
        'login'  => '🔐',
        'logout' => '🚪',
        'create' => '➕',
        'update' => '✏️',
        'delete' => '🗑️',
        'upload' => '📤',
        'export' => '📊',
        'test'   => '🤖',
    ];

    private static array $discordColors = [
        'login'  => 0x3498db,
        'logout' => 0x95a5a6,
        'create' => 0x2ecc71,
        'update' => 0xf39c12,
        'delete' => 0xe74c3c,
        'upload' => 0x9b59b6,
        'export' => 0x1abc9c,
        'test'   => 0x2ecc71,
    ];

    public static function checkAndAlertRejectRate(\App\Models\Product $product, string $date): void
    {
        try {
            $setting   = BotSetting::instance();
            $threshold = (float) ($setting->reject_threshold ?? 0);
            if ($threshold <= 0) return;
            if (!$setting->telegram_enabled && !$setting->discord_enabled) return;

            $cacheKey = "reject_alert_{$product->id}_{$date}";
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) return;

            $row = \App\Models\ProductionLog::withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)
                ->where('product_id', $product->id)
                ->whereDate('production_date', $date)
                ->selectRaw('SUM(total_qty) as total, SUM(reject_qty) as reject')
                ->first();

            if (!$row || $row->total <= 0) return;

            $rate = ($row->reject / $row->total) * 100;
            if ($rate < $threshold) return;

            \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->endOfDay());

            $rejectQty = (int) $row->reject;
            $totalQty  = (int) $row->total;
            $rateFmt   = number_format($rate, 1);
            $threshFmt = number_format($threshold, 1);
            $dateFmt   = \Carbon\Carbon::parse($date)->translatedFormat('d F Y');
            $time      = now()->timezone('Asia/Jakarta')->format('H:i') . ' WIB';

            // Alert produksi → kirim ke chat laporan produksi (fallback ke chat utama)
            $reportChatId = $setting->telegram_report_chat_id ?: $setting->telegram_chat_id;
            if ($setting->telegram_enabled && $setting->telegram_token && $reportChatId) {
                $text = "⚠️ <b>ALERT: Reject Rate Tinggi!</b>\n"
                      . "📦 Produk: <b>" . htmlspecialchars($product->name) . "</b>\n"
                      . "📉 Reject Rate: <b>{$rateFmt}%</b> (batas: {$threshFmt}%)\n"
                      . "✕ Reject: <b>{$rejectQty}</b> dari {$totalQty} unit\n"
                      . "📅 {$dateFmt} · 🕒 {$time}";

                Http::withoutVerifying()->timeout(5)->post(
                    "https://api.telegram.org/bot{$setting->telegram_token}/sendMessage",
                    ['chat_id' => $reportChatId, 'text' => $text, 'parse_mode' => 'HTML']
                );
            }

            if ($setting->discord_enabled && $setting->discord_webhook) {
                Http::withoutVerifying()->timeout(5)->post($setting->discord_webhook, [
                    'embeds' => [[
                        'title'       => '⚠️ Alert: Reject Rate Tinggi!',
                        'description' => "**" . $product->name . "** — Reject rate **{$rateFmt}%** melebihi batas {$threshFmt}%",
                        'color'       => 0xe74c3c,
                        'fields'      => [
                            ['name' => '📦 Produk',      'value' => $product->name,                    'inline' => true],
                            ['name' => '📉 Reject Rate', 'value' => "{$rateFmt}%",                     'inline' => true],
                            ['name' => '✕ Reject',       'value' => "{$rejectQty} / {$totalQty} unit", 'inline' => true],
                        ],
                        'footer'    => ['text' => 'Asata Production System'],
                        'timestamp' => now()->toIso8601String(),
                    ]],
                ]);
            }
        } catch (\Throwable) {}
    }

    public static function checkAndNotifyTargetReached(int $productId): void
    {
        try {
            // Target aktif (tanpa batas waktu) untuk produk ini
            $target = \App\Models\ProductionTarget::where('product_id', $productId)->first();
            if (!$target || $target->target_qty <= 0) return;
            if ($target->reached_at) return; // quick pre-check

            // Progres = produksi kumulatif sekarang − baseline saat target dibuat
            $cumulative = (int) \App\Models\ProductionLog::withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)
                ->where('product_id', $productId)->sum('total_qty');
            $actual     = max(0, $cumulative - (int) $target->baseline_qty);

            if ($actual < $target->target_qty) return;

            // Tandai tercapai secara atomik — cegah notifikasi ganda dari request concurrent
            $marked = \App\Models\ProductionTarget::where('id', $target->id)
                ->whereNull('reached_at')
                ->update(['reached_at' => now()]);
            if ($marked === 0) return; // request lain sudah tandai duluan

            $setting = BotSetting::instance();
            if (!$setting->telegram_enabled && !$setting->discord_enabled) return;

            $product   = \App\Models\Product::find($productId);
            $time      = now()->timezone('Asia/Jakarta')->format('H:i') . ' WIB';
            $actualFmt = number_format($actual);
            $targetFmt = number_format($target->target_qty);

            $reportChatId = $setting->telegram_report_chat_id ?: $setting->telegram_chat_id;
            if ($setting->telegram_enabled && $setting->telegram_token && $reportChatId) {
                $text = "🎯 <b>TARGET TERCAPAI!</b>\n"
                      . "📦 Produk: <b>" . htmlspecialchars($product->name ?? '-') . "</b>\n"
                      . "✅ Produksi: <b>{$actualFmt}</b> / {$targetFmt} unit (100%)\n"
                      . "🕒 {$time}\n\n"
                      . "<i>Asata Production System</i>";

                Http::withoutVerifying()->timeout(5)->post(
                    "https://api.telegram.org/bot{$setting->telegram_token}/sendMessage",
                    ['chat_id' => $reportChatId, 'text' => $text, 'parse_mode' => 'HTML']
                );
            }

            if ($setting->discord_enabled && $setting->discord_webhook) {
                Http::withoutVerifying()->timeout(5)->post($setting->discord_webhook, [
                    'embeds' => [[
                        'title'       => '🎯 Target Tercapai!',
                        'description' => "Produksi **" . ($product->name ?? '-') . "** telah mencapai target!",
                        'color'       => 0x2ecc71,
                        'fields'      => [
                            ['name' => '📦 Produk',   'value' => $product->name ?? '-', 'inline' => true],
                            ['name' => '✅ Produksi', 'value' => "{$actualFmt} unit",   'inline' => true],
                            ['name' => '🎯 Target',   'value' => "{$targetFmt} unit",   'inline' => true],
                        ],
                        'footer'    => ['text' => 'Asata Production System'],
                        'timestamp' => now()->toIso8601String(),
                    ]],
                ]);
            }
        } catch (\Throwable) {}
    }

    public static function sendDailyReport(?string $date = null): array
    {
        try {
            $date    = $date ?? today()->toDateString();
            $setting = BotSetting::instance();

            if (!$setting->telegram_enabled && !$setting->discord_enabled) {
                return ['ok' => false, 'message' => 'Semua bot nonaktif.'];
            }

            $logs = \App\Models\ProductionLog::withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)
                ->with('product.category')
                ->whereDate('production_date', $date)
                ->orderBy('product_id')
                ->get();

            if ($logs->isEmpty()) {
                $d = \Carbon\Carbon::parse($date)->translatedFormat('d F Y');
                return ['ok' => false, 'message' => "Tidak ada data produksi untuk {$d}."];
            }

            $totalQty    = (float) $logs->sum('total_qty');
            $totalReject = (int)   $logs->sum('reject_qty');
            $rejectRate  = $totalQty > 0 ? round(($totalReject / ($totalQty + $totalReject)) * 100, 1) : 0;
            $dateFmt     = \Carbon\Carbon::parse($date)->translatedFormat('d F Y');
            $time        = now()->timezone('Asia/Jakarta')->format('H:i') . ' WIB';
            $rateEmoji   = $rejectRate > 5 ? '🔴' : ($rejectRate > 2 ? '🟡' : '🟢');

            // Helper: label seri produk (pakai manual override jika ada)
            $seriesLabel = function ($log) {
                $series = $log->manual_series ?: ($log->product?->series ?? null);
                $kva    = $log->manual_kva    ?: ($log->product?->kva    ?? null);
                if ($series && $kva) return "{$series}-{$kva}KVA";
                if ($series)         return $series;
                return $log->product?->name ?? '-';
            };

            // Kelompokkan per kategori, lalu per produk (merge baris produk yang sama)
            $byCategory = $logs->groupBy(fn($l) => $l->product?->category?->name ?? 'Lainnya');

            // ── Telegram text ────────────────────────────────────────────
            $text = "📊 <b>Laporan Harian Produksi</b>\n"
                  . "📅 <b>{$dateFmt}</b> · 🕕 {$time}\n"
                  . "━━━━━━━━━━━━━━━━━━\n";

            $fields = [];   // Discord embed fields

            foreach ($byCategory as $catName => $catLogs) {
                $catQty    = (float) $catLogs->sum('total_qty');
                $catReject = (int)   $catLogs->sum('reject_qty');

                $text .= "\n📦 <b>{$catName}</b>";
                if ($catReject > 0) $text .= " <i>(✕ {$catReject} reject)</i>";
                $text .= "\n";

                // Merge entries dengan seri yang sama dalam kategori yang sama
                $byProduct = $catLogs->groupBy(fn($l) => $seriesLabel($l));
                foreach ($byProduct as $label => $productLogs) {
                    $qty    = (float) $productLogs->sum('total_qty');
                    $reject = (int)   $productLogs->sum('reject_qty');
                    $text  .= "  · {$label} : " . number_format($qty) . " unit";
                    if ($reject > 0) $text .= " ✕{$reject}";
                    $text  .= "\n";
                }

                $fields[] = [
                    'name'   => "📦 {$catName}",
                    'value'  => number_format($catQty) . " unit" . ($catReject > 0 ? " (✕ {$catReject})" : ''),
                    'inline' => true,
                ];
            }

            // ── Reject detail ────────────────────────────────────────────
            $rejectLogs = $logs->filter(fn($l) => $l->reject_qty > 0);
            $text .= "\n━━━━━━━━━━━━━━━━━━\n"
                   . "📈 Total: <b>" . number_format($totalQty) . " unit</b>\n"
                   . "✕ Reject: <b>{$totalReject} unit</b>\n";

            if ($rejectLogs->isNotEmpty()) {
                $text .= "<i>Detail reject:</i>\n";
                foreach ($rejectLogs as $log) {
                    $catName = $log->product?->category?->name ?? 'Lainnya';
                    $label   = $seriesLabel($log);
                    $reason  = $log->reject_category
                        ? (\App\Models\ProductionLog::$rejectCategories[$log->reject_category] ?? $log->reject_category)
                        : '';
                    $text .= "  ▸ <b>{$catName}</b> · {$label} : {$log->reject_qty} unit";
                    if ($reason)            $text .= " [{$reason}]";
                    if ($log->reject_notes) $text .= " — {$log->reject_notes}";
                    $text .= "\n";
                }
            }

            $text .= "{$rateEmoji} Reject Rate: <b>{$rejectRate}%</b>\n"
                   . "\n<i>Asata Production System</i>";

            $sent = 0;

            // Laporan harian → kirim ke chat laporan produksi (fallback ke chat utama)
            $reportChatId = $setting->telegram_report_chat_id ?: $setting->telegram_chat_id;
            if ($setting->telegram_enabled && $setting->telegram_token && $reportChatId) {
                $res = Http::withoutVerifying()->timeout(8)->post(
                    "https://api.telegram.org/bot{$setting->telegram_token}/sendMessage",
                    ['chat_id' => $reportChatId, 'text' => $text, 'parse_mode' => 'HTML']
                );
                if ($res->successful()) $sent++;
            }

            if ($setting->discord_enabled && $setting->discord_webhook) {
                $color  = $rejectRate > 5 ? 0xe74c3c : ($rejectRate > 2 ? 0xf39c12 : 0x2ecc71);
                $rejectDetail = '';
                if ($rejectLogs->isNotEmpty()) {
                    foreach ($rejectLogs as $log) {
                        $catName = $log->product?->category?->name ?? 'Lainnya';
                        $label   = $seriesLabel($log);
                        $reason  = $log->reject_category
                            ? (\App\Models\ProductionLog::$rejectCategories[$log->reject_category] ?? $log->reject_category)
                            : '';
                        $rejectDetail .= "▸ {$catName} · {$label} : {$log->reject_qty} unit";
                        if ($reason) $rejectDetail .= " [{$reason}]";
                        $rejectDetail .= "\n";
                    }
                }
                $fields = array_merge($fields, [
                    ['name' => '📈 Total Produksi', 'value' => number_format($totalQty) . ' unit', 'inline' => true],
                    ['name' => '✕ Total Reject',   'value' => $totalReject > 0 ? "{$totalReject} unit\n{$rejectDetail}" : '0 unit', 'inline' => true],
                    ['name' => "{$rateEmoji} Reject Rate", 'value' => "{$rejectRate}%",            'inline' => true],
                ]);
                $res = Http::withoutVerifying()->timeout(8)->post($setting->discord_webhook, [
                    'embeds' => [[
                        'title'       => '📊 Laporan Harian Produksi',
                        'description' => "**{$dateFmt}** · Dikirim {$time}",
                        'color'       => $color,
                        'fields'      => $fields,
                        'footer'      => ['text' => 'Asata Production System'],
                        'timestamp'   => now()->toIso8601String(),
                    ]],
                ]);
                if ($res->successful()) $sent++;
            }

            return $sent > 0
                ? ['ok' => true,  'message' => "Laporan berhasil dikirim ke {$sent} bot. ({$totalQty} unit, {$totalReject} reject)"]
                : ['ok' => false, 'message' => 'Gagal mengirim laporan. Cek koneksi dan pengaturan bot.'];

        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public static function notify(string $action, string $description, ?User $user): void
    {
        try {
            $setting = BotSetting::instance();

            if ($setting->telegram_enabled && $setting->telegram_token && $setting->telegram_chat_id) {
                static::sendTelegram($setting->telegram_token, $setting->telegram_chat_id, $action, $description, $user);
            }

            if ($setting->discord_enabled && $setting->discord_webhook) {
                static::sendDiscord($setting->discord_webhook, $action, $description, $user);
            }
        } catch (\Throwable) {}
    }

    public static function test(string $type, BotSetting $setting): array
    {
        try {
            if ($type === 'telegram') {
                if (!$setting->telegram_token || !$setting->telegram_chat_id) {
                    return ['ok' => false, 'message' => 'Token dan Chat ID belum diisi.'];
                }
                $res = Http::withoutVerifying()->timeout(8)->post(
                    "https://api.telegram.org/bot{$setting->telegram_token}/sendMessage",
                    [
                        'chat_id'    => $setting->telegram_chat_id,
                        'text'       => "🤖 <b>Test Notifikasi</b>\n✅ Konfigurasi Telegram berhasil!\n🕒 " . now()->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') . " WIB\n\n<i>Asata Production System</i>",
                        'parse_mode' => 'HTML',
                    ]
                );
                return $res->successful()
                    ? ['ok' => true,  'message' => 'Test Telegram berhasil! Cek chat Anda.']
                    : ['ok' => false, 'message' => 'Gagal: ' . ($res->json('description') ?? 'Token atau Chat ID salah.')];
            }

            if ($type === 'discord') {
                if (!$setting->discord_webhook) {
                    return ['ok' => false, 'message' => 'Webhook URL belum diisi.'];
                }
                $res = Http::withoutVerifying()->timeout(8)->post($setting->discord_webhook, [
                    'embeds' => [[
                        'title'       => '🤖 Test Notifikasi',
                        'description' => '✅ Konfigurasi Discord berhasil!',
                        'color'       => 0x2ecc71,
                        'footer'      => ['text' => 'Asata Production System'],
                        'timestamp'   => now()->toIso8601String(),
                    ]],
                ]);
                return $res->successful()
                    ? ['ok' => true,  'message' => 'Test Discord berhasil! Cek channel Anda.']
                    : ['ok' => false, 'message' => 'Gagal: Webhook URL tidak valid atau tidak dapat dijangkau.'];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Error: ' . $e->getMessage()];
        }

        return ['ok' => false, 'message' => 'Tipe bot tidak dikenali.'];
    }

    private static function getLocation(string $ip): string
    {
        if (in_array($ip, ['127.0.0.1', '::1'])
            || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '10.')
            || str_starts_with($ip, '172.')) {
            return 'Local Network';
        }

        $cacheKey = 'ip_location_' . md5($ip);
        $cached   = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        try {
            $res = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,city,regionName,country,isp',
                'lang'   => 'id',
            ]);
            if ($res->successful() && $res->json('status') === 'success') {
                $city    = $res->json('city') ?? '';
                $country = $res->json('country') ?? '';
                $isp     = $res->json('isp') ?? '';
                $parts   = array_filter([$city, $country]);
                $location = implode(', ', $parts) . ($isp ? " · {$isp}" : '');
                \Illuminate\Support\Facades\Cache::put($cacheKey, $location, now()->addHours(6));
                return $location;
            }
        } catch (\Throwable) {}

        \Illuminate\Support\Facades\Cache::put($cacheKey, '', now()->addHours(1));
        return '';
    }

    private static function parseDevice(?string $ua): string
    {
        if (!$ua) return 'Unknown';

        if (str_contains($ua, 'Edg/'))         $browser = 'Edge';
        elseif (str_contains($ua, 'OPR/'))     $browser = 'Opera';
        elseif (str_contains($ua, 'Chrome/'))  $browser = 'Chrome';
        elseif (str_contains($ua, 'Firefox/')) $browser = 'Firefox';
        elseif (str_contains($ua, 'Safari/'))  $browser = 'Safari';
        else                                   $browser = 'Browser';

        if (preg_match('/iPhone|iPad/i', $ua))      $os = 'iOS';
        elseif (preg_match('/Android/i', $ua))      $os = 'Android';
        elseif (preg_match('/Windows/i', $ua))      $os = 'Windows';
        elseif (preg_match('/Macintosh/i', $ua))    $os = 'Mac';
        elseif (preg_match('/Linux/i', $ua))        $os = 'Linux';
        else                                        $os = 'Unknown OS';

        $type = preg_match('/Mobile|Android|iPhone|iPad/i', $ua) ? '📱' : '💻';

        return "{$type} {$browser} · {$os}";
    }

    private static function sendTelegram(
        string $token, string $chatId,
        string $action, string $description, ?User $user
    ): void {
        $icon     = static::$icons[$action] ?? '📋';
        $time     = now()->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') . ' WIB';
        $name     = $user ? htmlspecialchars($user->name) : 'System';
        $role     = $user ? $user->role : '';
        $actLabel = strtoupper($action);
        $ip       = request()->ip() ?? '-';
        $device   = static::parseDevice(request()->userAgent());
        $location = static::getLocation($ip);

        $text = "{$icon} <b>{$actLabel}</b>\n"
              . "👤 <b>{$name}</b>" . ($role ? " <i>({$role})</i>" : '') . "\n"
              . "📝 " . htmlspecialchars($description) . "\n"
              . "🌐 <code>{$ip}</code> · {$device}\n"
              . ($location ? "📍 {$location}\n" : '')
              . "🕒 {$time}";

        Http::withoutVerifying()->timeout(5)->post(
            "https://api.telegram.org/bot{$token}/sendMessage",
            ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML']
        );
    }

    private static function sendDiscord(
        string $webhook,
        string $action, string $description, ?User $user
    ): void {
        $icon   = static::$icons[$action] ?? '📋';
        $color  = static::$discordColors[$action] ?? 0x7289da;
        $ip       = request()->ip() ?? '-';
        $device   = static::parseDevice(request()->userAgent());
        $location = static::getLocation($ip);

        $fields = [
            ['name' => '👤 User', 'value' => $user?->name ?? 'System', 'inline' => true],
            ['name' => '🎭 Role', 'value' => $user?->role ?? '-',      'inline' => true],
            ['name' => '🌐 IP · Device', 'value' => "`{$ip}` · {$device}", 'inline' => false],
        ];
        if ($location) {
            $fields[] = ['name' => '📍 Lokasi', 'value' => $location, 'inline' => false];
        }

        Http::withoutVerifying()->timeout(5)->post($webhook, [
            'embeds' => [[
                'title'       => "{$icon} " . ucfirst($action),
                'description' => $description,
                'color'       => $color,
                'fields'      => $fields,
                'footer'    => ['text' => 'Asata Production System'],
                'timestamp' => now()->toIso8601String(),
            ]],
        ]);
    }
}
