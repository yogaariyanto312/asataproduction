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
        $middleware->web(append: [
            \Illuminate\Session\Middleware\AuthenticateSession::class,
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
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            // Request logout, atau user sudah tidak login → kembalikan ke halaman login.
            if ($request->is('logout') || ! \Illuminate\Support\Facades\Auth::check()) {
                return redirect()->route('login')
                    ->with('success', 'Sesi Anda telah berakhir. Silakan login kembali.');
            }

            // Masih login (mis. submit form biasa) → kembali ke halaman sebelumnya
            // dengan input dipertahankan supaya tidak kehilangan isian.
            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', 'Halaman telah kedaluwarsa karena tidak aktif. Silakan coba lagi.');
        });
    })->create();
