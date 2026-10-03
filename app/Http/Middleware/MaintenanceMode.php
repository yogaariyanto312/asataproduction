<?php

namespace App\Http\Middleware;

use App\Models\BotSetting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mode maintenance dari menu Settings.
 *
 * Dulu hanya ditegakkan di layout Blade lama (sebuah lapisan penutup layar),
 * sehingga halaman React tidak terkunci sama sekali — dan bahkan di Blade data
 * tetap bisa diambil lewat URL/API karena yang ditutup cuma tampilannya.
 * Sekarang dijaga di server untuk SEMUA request pengguna yang login:
 *  - developer selalu lolos (yang menyalakan maintenance);
 *  - halaman → layar Maintenance (Inertia), request lain → 503;
 *  - berakhir sendiri saat maintenance_until lewat.
 */
class MaintenanceMode
{
    /** Route yang tetap boleh dipakai selama maintenance. */
    private const LOLOS = ['login', 'logout', 'csrf.token', 'password.*', 'telegram.webhook'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role === 'developer' || $request->routeIs(...self::LOLOS)) {
            return $next($request);
        }

        $setting = BotSetting::instance();

        if (! self::aktif($setting)) {
            return $next($request);
        }

        $pesan = $setting->maintenance_message
            ?: 'Kami sedang melakukan pemeliharaan sistem. Harap bersabar, layanan akan kembali normal secepatnya.';

        $inertia = (bool) $request->header('X-Inertia');

        // Polling & API (fetch biasa) cukup dijawab 503 — halaman yang masih
        // terbuka mendeteksinya lalu memuat ulang ke layar maintenance.
        if (! $inertia && ($request->expectsJson() || $request->ajax() || $request->is('api/*'))) {
            return response()->json(['message' => $pesan, 'maintenance' => true], 503);
        }

        $halaman = Inertia::render('Maintenance', [
            'message'    => $pesan,
            'until'      => $setting->maintenance_until?->toIso8601String(),
            'untilLabel' => $setting->maintenance_until
                ? $setting->maintenance_until->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB'
                : null,
            'user'       => ['name' => $user->name, 'role' => $user->role],
            'logoutUrl'  => route('logout'),
        ])->toResponse($request);

        // Kunjungan Inertia harus berstatus sukses supaya halaman ditukar
        // dengan mulus; muat penuh dari browser boleh 503 (tanda layanan jeda).
        return $inertia ? $halaman : $halaman->setStatusCode(503);
    }

    /** Maintenance menyala dan belum lewat batas waktunya. */
    public static function aktif(BotSetting $setting): bool
    {
        return (bool) $setting->maintenance_mode
            && (! $setting->maintenance_until || now()->lt($setting->maintenance_until));
    }
}
