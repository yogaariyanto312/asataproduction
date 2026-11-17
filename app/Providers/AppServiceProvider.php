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
        // Realtime ringan (PRD 14): perubahan data menaikkan nomor versi topiknya (lihat App\Support\Realtime).
        foreach ([
            \App\Models\ProductionLog::class => ['produksi'],
            \App\Models\ProductionTarget::class => ['target'],
            \App\Models\Note::class => ['catatan', 'notif'],
            \App\Models\GambarKerja::class => ['gambar'],
        ] as $model => $topik) {
            $bump = fn () => \App\Support\Realtime::bump(...$topik);
            $model::saved($bump);
            $model::deleted($bump);
        }
        \App\Models\Message::saved(function ($m) {
            \App\Support\Realtime::bumpPengguna('chat', $m->recipient_id);
            \App\Support\Realtime::bumpPengguna('notif', $m->recipient_id);
            \App\Support\Realtime::bumpPengguna('chat', $m->sender_id);
        });
        \App\Models\Message::deleted(function ($m) {
            \App\Support\Realtime::bumpPengguna('chat', $m->recipient_id);
            \App\Support\Realtime::bumpPengguna('chat', $m->sender_id);
        });

        // @allowedTo('permission-key') ... @endallowedTo — sembunyikan tombol/aksi sesuai
        // izin efektif (role DAN departemen keduanya harus mengizinkan).
        Blade::if('allowedTo', function (string $key) {
            return MenuAccess::can(auth()->user(), $key);
        });
    }
}
