<?php

namespace App\Services\Telegram;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Menautkan akun Telegram seseorang ke penggunanya di aplikasi.
 *
 * Alurnya sengaja dibalik — kode dibuat DI APLIKASI lalu dikirim lewat Telegram,
 * bukan sebaliknya. Dengan begitu yang membuktikan diri adalah orang yang sudah
 * bisa login; bot tidak perlu menerima username/password sama sekali.
 */
class PenautanAkun
{
    /** Kode hanya berlaku sebentar: cukup untuk menyalinnya ke Telegram. */
    public const BERLAKU_MENIT = 10;

    private const AWALAN = 'tg_tautkan_';

    /**
     * Peran yang boleh menautkan Telegram & memakai bot secara pribadi.
     * Diminta Yoga 2026-09-30: operator, mandor, dan visitor tidak memakai bot.
     * Grup yang terdaftar di Settings tetap bisa memakai perintah baca.
     */
    public const PERAN = ['developer', 'admin', 'supervisor'];

    /** Untuk ditulis di pesan bot & layar: "developer, admin, dan supervisor". */
    public static function daftarPeran(): string
    {
        $peran = self::PERAN;
        $akhir = array_pop($peran);

        return implode(', ', $peran) . ', dan ' . $akhir;
    }

    public static function boleh(?User $user): bool
    {
        return $user !== null && in_array($user->role, self::PERAN, true);
    }

    /** Buat (atau perbarui) kode penautan untuk seorang pengguna. */
    public static function buatKode(User $user): string
    {
        // Huruf yang mudah tertukar dibuang: 0/O dan 1/I/L. Kodenya dibacakan
        // atau diketik ulang orang, bukan disalin mesin.
        $kode = strtoupper(Str::password(6, true, true, false, false));
        $kode = str_replace(['0', 'O', '1', 'I', 'L'], ['2', 'P', '3', 'J', 'K'], $kode);

        Cache::put(self::AWALAN . $kode, $user->id, now()->addMinutes(self::BERLAKU_MENIT));

        return $kode;
    }

    /**
     * Tukarkan kode dengan penautan. Mengembalikan pengguna yang tertaut, atau
     * null kalau kodenya salah/kedaluwarsa.
     */
    public static function tukarkan(string $kode, string $telegramUserId): ?User
    {
        $kunci  = self::AWALAN . strtoupper(trim($kode));
        $userId = Cache::get($kunci);

        if (! $userId) {
            return null;
        }

        $user = User::find($userId);

        // Diperiksa lagi saat ditukar: perannya bisa saja diubah dalam 10 menit
        // antara kode dibuat dan dikirim ke bot.
        if (! $user || ! $user->is_active || ! self::boleh($user)) {
            return null;
        }

        // Satu akun Telegram hanya boleh menempel pada satu pengguna. Kalau id
        // ini sudah dipakai orang lain, tautan lamanya dilepas — orang yang sama
        // berpindah akun aplikasi, bukan dua orang memakai satu Telegram.
        User::where('telegram_user_id', $telegramUserId)
            ->where('id', '!=', $user->id)
            ->update(['telegram_user_id' => null, 'telegram_linked_at' => null]);

        $user->forceFill([
            'telegram_user_id'   => $telegramUserId,
            'telegram_linked_at' => now(),
        ])->save();

        Cache::forget($kunci);

        return $user;
    }

    /** Pengguna aktif yang memiliki id Telegram ini, kalau ada. */
    public static function pengguna(?string $telegramUserId): ?User
    {
        if (! $telegramUserId) {
            return null;
        }

        // Tautan lama milik peran yang kini tidak diizinkan dianggap tidak ada —
        // bot tidak mengenali orangnya, jadi tidak bisa mengubah data apa pun.
        return User::where('telegram_user_id', $telegramUserId)
            ->where('is_active', true)
            ->whereIn('role', self::PERAN)
            ->first();
    }

    public static function putuskan(User $user): void
    {
        $user->forceFill([
            'telegram_user_id'   => null,
            'telegram_linked_at' => null,
        ])->save();
    }
}
