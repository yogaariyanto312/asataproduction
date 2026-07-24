<?php

namespace App\Http\Middleware;

use App\Support\MenuAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blokir akses URL untuk menu yang tidak diizinkan bagi role user.
 * Hanya berlaku untuk route yang terpetakan ke sebuah menu (lihat config/menus.php).
 * Route lain (login, profile, api, dll) dilewati. Developer selalu bypass.
 */
class EnforceMenuAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role !== 'developer') {
            $routeName = optional($request->route())->getName();
            $key = MenuAccess::permissionKeyForRoute($routeName);

            if ($key !== null && !MenuAccess::can($user, $key)) {
                abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk aksi/menu ini.');
            }
        }

        return $next($request);
    }
}
