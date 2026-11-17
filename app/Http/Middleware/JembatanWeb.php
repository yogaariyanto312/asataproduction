<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route API yang langsung memakai controller WEB (aturan & validasi identik).
 * - Halaman Inertia → JSON berisi props halaman itu.
 * - Redirect + flash  → {success, message}; flash 'error' → 422 {message}.
 * Sesi sementara dipasang supaya back()/with()/session() di controller web jalan
 * tanpa cookie; tidak pernah disimpan.
 */
class JembatanWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        // Store baru tiap request: flash request sebelumnya tidak boleh terbawa.
        $sesi = new Store('api', new ArraySessionHandler(1));
        $request->setLaravelSession($sesi);
        app('redirect')->setSession($sesi);
        $request->headers->set('X-Inertia', 'true');

        $res = $next($request);

        if ($res instanceof RedirectResponse) {
            if ($galat = $sesi->get('error')) {
                return response()->json(['success' => false, 'message' => $galat], 422);
            }

            return response()->json(['success' => true, 'message' => $sesi->get('success') ?? $sesi->get('webhook_info') ?? 'Berhasil.']);
        }

        if ($res instanceof JsonResponse && $res->headers->get('X-Inertia')) {
            return response()->json($res->getData(true)['props'] ?? []);
        }

        return $res;
    }
}
