<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengaman satu pintu: request Inertia (header X-Inertia) yang terlanjur
 * dibalas JSON polos oleh controller lama/endpoint AJAX diubah jadi redirect
 * kembali. Tanpa ini Inertia menampilkan modal putih "plain JSON response".
 * Request fetch/axios biasa (tanpa X-Inertia) tetap menerima JSON-nya.
 */
class InertiaTanpaJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->header('X-Inertia') || ! $response instanceof JsonResponse || $response->headers->has('X-Inertia')) {
            return $response;
        }

        $kembali = back(303);
        if ($response->getStatusCode() >= 400) {
            $pesan = $response->getData(true)['message'] ?? 'Permintaan gagal diproses.';
            $kembali->with('error', $pesan);
        }

        return $kembali;
    }
}
