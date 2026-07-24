<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    public function showForm()
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['identifier' => ['required', 'string']], [
            'identifier.required' => 'Username atau email wajib diisi.',
        ]);

        $identifier = $request->input('identifier');
        $isEmail    = str_contains($identifier, '@');

        if ($isEmail) {
            $user = User::where('email', $identifier)->first();
        } else {
            $user = User::where('username', $identifier)->first();
        }

        // Selalu tampilkan pesan sukses untuk menghindari enumerasi akun
        if (!$user || !$user->email) {
            return back()->with('success', 'Jika akun terdaftar, link reset password akan dikirim ke email yang terhubung.');
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', 'Link reset password telah dikirim ke email. Periksa kotak masuk atau folder spam.');
        }

        throw ValidationException::withMessages(['identifier' => __($status)]);
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'                 => ['required'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'min:8', 'confirmed'],
        ], [
            'password.required'  => 'Password wajib diisi.',
            'password.min'       => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password berhasil direset. Silakan login dengan password baru.');
        }

        throw ValidationException::withMessages([
            'email' => __($status),
        ]);
    }
}
