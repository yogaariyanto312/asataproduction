<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga API aplikasi Android (setelah auth:sanctum).
 *
 * Token Sanctum tidak tahu status akun, jadi akun yang dinonaktifkan admin
 * ditolak di sini dan tokennya dicabut — menonaktifkan akun di web otomatis
 * mengeluarkannya dari aplikasi.
 */
class PenjagaApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Akun Anda tidak aktif. Hubungi administrator.'], 401);
        }

        return $next($request);
    }
}
