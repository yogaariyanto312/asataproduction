<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Simpan hash password di sesi agar fitur "logout semua perangkat"
        // (Auth::logoutOtherDevices) bisa membatalkan sesi di perangkat lain.
        // EnforceMenuAccess: blokir URL menu yang dinonaktifkan untuk role user.
        // HandleInertiaRequests harus setelah StartSession/ShareErrors (bawaan
        // grup web) agar flash message & error validasi ikut terbagi ke props.
        $middleware->web(append: [
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            // Setelah HandleInertiaRequests (layar maintenance butuh root view &
            // versi aset Inertia) dan sebelum cek hak akses: selama jeda,
            // non-developer selalu melihat layar maintenance apa pun URL-nya.
            \App\Http\Middleware\MaintenanceMode::class,
            \App\Http\Middleware\EnforceMenuAccess::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/telegram/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tangani "Page Expired" (419 / CSRF token mismatch) dengan mulus:
        // arahkan user kembali daripada menampilkan halaman error mentah.
        //
        // PENTING — kenapa yang ditangkap HttpException dan bukan
        // TokenMismatchException: Handler::render() memanggil prepareException()
        // SEBELUM renderViaCallbacks(), dan prepareException() sudah menukar
        // TokenMismatchException menjadi HttpException(419). Callback bertipe
        // TokenMismatchException karena itu tidak pernah kena — persis yang
        // terjadi sebelum perbaikan ini, sehingga user tetap melihat halaman
        // 419 mentah meski penanganannya sudah ditulis.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            // Status lain (403, 404, 503, …) dibiarkan ditangani Laravel seperti
            // biasa; mengembalikan null berarti "lanjutkan ke penanganan bawaan".
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.',
                ], 419);
            }

            // Request logout, atau user sudah tidak login → kembalikan ke halaman login.
            if ($request->is('logout') || ! \Illuminate\Support\Facades\Auth::check()) {
                return redirect()->route('login')
                    ->with('warning', 'Sesi Anda telah berakhir. Silakan masuk kembali.');
            }

            // Masih login (mis. submit form biasa) → kembali ke halaman sebelumnya
            // dengan input dipertahankan supaya tidak kehilangan isian.
            return redirect()->back(fallback: route('dashboard'))
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', 'Halaman telah kedaluwarsa karena tidak aktif. Silakan coba lagi.');
        });
    })->create();
