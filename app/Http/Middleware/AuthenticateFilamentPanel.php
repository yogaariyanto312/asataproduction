<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

/**
 * Penjaga panel Filament (/admin).
 *
 * Mewarisi Authenticate milik Filament — itulah yang memanggil
 * User::canAccessPanel(), sehingga role dan status is_active tetap ditegakkan.
 * Yang diubah hanya tujuan redirect untuk tamu: panel ini sengaja tidak
 * memanggil ->login(), karena aplikasi hanya boleh punya SATU pintu login
 * (/login) yang sudah memiliki rate limiter, pengecekan is_active, activity
 * log, dan notifikasi login admin.
 *
 * Catatan: memakai Authenticate milik Laravel secara langsung TIDAK cukup —
 * middleware itu hanya memeriksa "sudah login atau belum", sehingga setiap user
 * yang login (termasuk operator dan admin nonaktif) bisa masuk panel.
 */
class AuthenticateFilamentPanel extends FilamentAuthenticate
{
    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
