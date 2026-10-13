<?php

namespace App\Http\Middleware;

use App\Support\MenuAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hak akses menu/aksi untuk route API: `->middleware('izin:input-produksi.create')`.
 * Memakai MenuAccess yang sama dengan web, jadi pengaturan Hak Akses Menu
 * berlaku juga di aplikasi Android. Developer selalu lolos.
 */
class IzinMenu
{
    public function handle(Request $request, Closure $next, string $kunci): Response
    {
        $user = $request->user();

        if ($user && $user->role !== 'developer' && ! MenuAccess::can($user, $kunci)) {
            return response()->json(['message' => 'Akses ditolak. Anda tidak memiliki izin untuk aksi/menu ini.'], 403);
        }

        return $next($request);
    }
}
