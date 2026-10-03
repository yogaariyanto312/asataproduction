<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris pengaturan untuk seluruh aplikasi (notifikasi bot, maintenance,
 * keamanan). Selalu diambil lewat instance().
 */
class BotSetting extends Model
{
    protected $fillable = [
        'telegram_token',
        'telegram_chat_id',
        'telegram_report_chat_id',
        'telegram_enabled',
        'discord_webhook',
        'discord_enabled',
        'reject_threshold',
        'report_enabled',
        'tutorial_iframe_url',
        'disable_devtools',
        'maintenance_mode',
        'maintenance_message',
        'maintenance_until',
    ];

    protected $casts = [
        'telegram_enabled'  => 'boolean',
        'discord_enabled'   => 'boolean',
        'report_enabled'    => 'boolean',
        'reject_threshold'  => 'decimal:2',
        'disable_devtools'  => 'boolean',
        'maintenance_mode'  => 'boolean',
        'maintenance_until' => 'datetime',
    ];

    /**
     * Ingatan sepanjang satu request.
     *
     * layouts/app.blade.php memanggil instance() dua kali (spanduk maintenance
     * dan penjaga DevTools), jadi SETIAP halaman aplikasi — bukan cuma halaman
     * Settings — membayar dua query ke tabel ini. Padahal isinya satu baris yang
     * sama. Dengan diingat, jadi satu query per request.
     */
    protected static ?self $ingatan = null;

    protected static function booted(): void
    {
        // Setelah disimpan, yang diingat adalah baris yang baru — bukan yang
        // lama, supaya halaman setelah "Simpan" tidak menampilkan nilai basi.
        static::saved(function (self $baris) {
            static::$ingatan = $baris;
        });

        static::deleted(function () {
            static::$ingatan = null;
        });
    }

    /** Dipanggil di antara test: properti statis hidup terus dalam satu proses PHP. */
    public static function lupakan(): void
    {
        static::$ingatan = null;
    }

    public static function instance(): static
    {
        if (static::$ingatan === null) {
            static::$ingatan = static::first() ?? static::create([
                'telegram_enabled' => false,
                'discord_enabled'  => false,
            ]);
        }

        return static::$ingatan;
    }
}
