<?php

namespace App\Providers;

use App\Support\MenuAccess;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // @allowedTo('permission-key') ... @endallowedTo — sembunyikan tombol/aksi sesuai
        // izin efektif (role DAN departemen keduanya harus mengizinkan).
        Blade::if('allowedTo', function (string $key) {
            return MenuAccess::can(auth()->user(), $key);
        });
    }
}
