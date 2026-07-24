<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return response(view('auth.login'))
            ->header('Permissions-Policy', 'publickey-credentials-get=(), publickey-credentials-create=()')
            // Cegah browser menyajikan halaman login basi (bfcache / tombol Back)
            // yang token CSRF-nya sudah kedaluwarsa → penyebab 419 "Page Expired".
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function login(Request $request)
    {
        $request->validate([
            'identifier' => ['required', 'string'],
            'password'   => ['required'],
        ], [
            'identifier.required' => 'Username atau email wajib diisi.',
            'password.required'   => 'Password wajib diisi.',
        ]);

        $identifier = $request->input('identifier');

        // Rate limiting: maks 5 percobaan gagal per identifier+IP, blokir 60 detik.
        $throttleKey = Str::lower($identifier) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'identifier' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $isEmail = str_contains($identifier, '@');

        if ($isEmail) {
            $user = \App\Models\User::where('email', $identifier)->first();
        } else {
            $user = \App\Models\User::where('username', $identifier)
                ->orWhere('name', $identifier)
                ->first();
        }

        if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60); // catat percobaan gagal, decay 60 detik
            return back()->withErrors(['identifier' => 'Username/email atau password salah.'])->onlyInput('identifier');
        }

        if (!$user->is_active) {
            return back()->withErrors(['identifier' => 'Akun Anda tidak aktif. Hubungi administrator.'])->onlyInput('identifier');
        }

        RateLimiter::clear($throttleKey); // reset counter setelah login sukses
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        ActivityLog::record('login', 'User login ke sistem');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        ActivityLog::record('logout', 'User logout dari sistem');
        Auth::user()?->update(['last_seen_at' => null]);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Anda berhasil logout.');
    }
}
