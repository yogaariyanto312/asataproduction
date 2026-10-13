<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\MenuAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login/logout aplikasi Android (token Sanctum). Aturannya sama dengan login
 * web: username, nama, atau email; akun harus aktif; maks 5 percobaan gagal
 * per identifier+IP lalu dikunci 60 detik.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
            'password'   => ['required', 'string'],
            'perangkat'  => ['nullable', 'string', 'max:100'],
        ], [
            'identifier.required' => 'Username atau email wajib diisi.',
            'password.required'   => 'Password wajib diisi.',
        ]);

        $identifier  = $request->input('identifier');
        $throttleKey = 'api|' . Str::lower($identifier) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $detik = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'identifier' => "Terlalu banyak percobaan login. Coba lagi dalam {$detik} detik.",
            ]);
        }

        $user = str_contains($identifier, '@')
            ? User::where('email', $identifier)->first()
            : User::where('username', $identifier)->orWhere('name', $identifier)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['identifier' => 'Username/email atau password salah.']);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['identifier' => 'Akun Anda tidak aktif. Hubungi administrator.']);
        }

        RateLimiter::clear($throttleKey);
        $token = $user->createToken($request->input('perangkat') ?: 'Android')->plainTextToken;

        Auth::setUser($user);
        ActivityLog::record('login', 'User login lewat aplikasi '.self::platform($request->input('perangkat')));

        return response()->json(['token' => $token, 'user' => $this->bentukUser($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->bentukUser($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        ActivityLog::record('logout', 'User logout dari aplikasi '.self::platform($request->user()->currentAccessToken()?->name));
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    /** Nama platform untuk Log Aktivitas: aplikasi Windows mengirim perangkat "Windows · <nama PC>", sisanya Android. */
    private static function platform(?string $perangkat): string
    {
        return str_starts_with((string) $perangkat, 'Windows') ? 'Windows' : 'Android';
    }

    public static function bentukUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'username'   => $user->username,
            'email'      => $user->email,
            'role'       => $user->role,
            'department' => $user->department,
            'avatar_url' => $user->avatarUrl(),
            // Semua kunci menu & aksi (config/menus.php): aplikasi memakai yang sama dengan sidebar web.
            'izin'       => collect(MenuAccess::items())
                ->flatMap(fn ($m) => [$m['key'], ...array_column($m['actions'] ?? [], 'key')])
                ->mapWithKeys(fn ($k) => [$k => $user->role === 'developer' || MenuAccess::can($user, $k)])
                ->all(),
        ];
    }
}
