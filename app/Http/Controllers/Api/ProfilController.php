<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\JsonResponse;
use App\Models\ActivityLog;
use App\Services\Telegram\PenautanAkun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** API Profil untuk aplikasi Android: ubah nama/email & ganti password (logika dari web). */
class ProfilController extends Controller
{
    public function perbarui(Request $request, ProfileController $web): JsonResponse
    {
        // Avatar tidak diubah dari aplikasi; jangan ikut terhapus.
        $request->request->remove('avatar');
        $web->simpanProfil($request);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user'    => AuthController::bentukUser($request->user()->fresh()),
        ]);
    }

    /**
     * "Logout Perangkat Lain" versi aplikasi: cabut token lain milik user ini dan
     * putus sesi web lain (hash password diperbarui → AuthenticateSession menolak sesi lama).
     */
    public function keluarPerangkatLain(Request $request): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'string']], [
            'current_password.required' => 'Password saat ini wajib diisi.',
        ]);
        $user = $request->user();
        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Password saat ini tidak sesuai.', 'errors' => ['current_password' => ['Password saat ini tidak sesuai.']]], 422);
        }

        $user->tokens()->whereKeyNot($user->currentAccessToken()?->getKey())->delete();
        $user->forceFill(['password' => Hash::make($request->current_password)])->save();
        ActivityLog::record('update', "Logout dari semua perangkat lain: {$user->name}");

        return response()->json(['message' => 'Berhasil keluar dari semua perangkat lain.']);
    }

    /** Kode penautan Telegram (web: flash 'telegram_kode' — di API langsung dikembalikan). */
    public function telegramKode(Request $request): JsonResponse
    {
        abort_unless(PenautanAkun::boleh($request->user()), 403, 'Fitur Telegram belum diaktifkan untuk peran Anda. Minta developer mengaktifkannya di Hak Akses Menu (Bot Telegram).');

        return response()->json([
            'kode'         => PenautanAkun::buatKode($request->user()),
            'berlakuMenit' => PenautanAkun::BERLAKU_MENIT,
        ]);
    }
}
