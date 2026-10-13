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
        'gk_popup_enabled',
        'gk_popup_detik',
        'gk_popup_pesan',
        'fitur_departemen',
    ];

    /** Bawaan sama dengan kolom DB; tanpa ini baris yang baru dibuat membaca null (= nonaktif) di Settings. */
    protected $attributes = [
        'gk_popup_enabled' => true,
        'gk_popup_detik'   => 3,
        'fitur_departemen' => true,
    ];

    protected $casts = [
        'telegram_enabled'  => 'boolean',
        'discord_enabled'   => 'boolean',
        'report_enabled'    => 'boolean',
        'reject_threshold'  => 'decimal:2',
        'disable_devtools'  => 'boolean',
        'maintenance_mode'  => 'boolean',
        'maintenance_until' => 'datetime',
        'gk_popup_enabled'  => 'boolean',
        'gk_popup_detik'    => 'integer',
        'fitur_departemen'  => 'boolean',
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

    /** Pesan bawaan popup Gambar Kerja bila kolom pesan di Settings dikosongkan. */
    public const PESAN_PENGINGAT_GK = 'Gambar kerja di aplikasi ini hanya sebagai referensi, jangan dijadikan patokan. '
        . 'Sebelum bekerja, cari dan pastikan dengan gambar kerja aslinya terlebih dahulu.';

    /** Data popup pengingat untuk halaman Gambar Kerja (web & API Android). */
    public function pengingatGambarKerja(): array
    {
        return [
            'aktif' => (bool) ($this->gk_popup_enabled ?? true),
            'detik' => (int) ($this->gk_popup_detik ?: 3),
            'pesan' => $this->gk_popup_pesan ?: self::PESAN_PENGINGAT_GK,
        ];
    }

    /**
     * Pilihan & saring departemen di Input/Riwayat Produksi hanya untuk developer,
     * dan bisa dimatikan di Settings (data tetap tercatat dengan departemen pembuatnya).
     */
    public static function departemenUntuk(?User $user): bool
    {
        return $user?->role === 'developer' && (bool) (static::instance()->fitur_departemen ?? true);
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
