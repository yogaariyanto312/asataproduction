<?php

namespace App\Http\Controllers;

use App\Mail\ProfileUpdatedMail;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $rules = [
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar'   => ['nullable', 'url', 'max:1000'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'current_password' => ['required_with:password', 'nullable', 'string'],
        ];

        $data = $request->validate($rules, [
            'name.required'            => 'Nama wajib diisi.',
            'email.required'           => 'Email wajib diisi.',
            'email.email'              => 'Format email tidak valid.',
            'email.unique'             => 'Email sudah digunakan akun lain.',
            'avatar.url'               => 'Link foto harus berupa URL yang valid.',
            'avatar.max'               => 'URL terlalu panjang.',
            'password.min'             => 'Password baru minimal 6 karakter.',
            'password.confirmed'       => 'Konfirmasi password tidak cocok.',
            'current_password.required_with' => 'Password saat ini wajib diisi untuk mengubah password.',
        ]);

        // Catat nilai lama sebelum diubah (untuk deteksi perubahan & notifikasi)
        $oldName     = $user->name;
        $oldEmail    = $user->email;
        $passwordChanged = false;

        if (!empty($data['password'])) {
            if (!Hash::check($data['current_password'], $user->password)) {
                return back()
                    ->withErrors(['current_password' => 'Password saat ini tidak sesuai.'])
                    ->withInput();
            }
            $user->password  = Hash::make($data['password']);
            $passwordChanged = true;
        }

        if ($request->has('avatar')) {
            $newUrl = $data['avatar'] ?? null;
            // Hapus file lama jika avatar sebelumnya adalah file lokal (bukan URL)
            if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $newUrl;
        }

        $user->name  = $data['name'];
        $user->email = $data['email'] ?? null;

        $user->save();

        ActivityLog::record('update', "Update profil: {$user->name}");

        // Kirim notifikasi email jika nama, email, atau password berubah
        $changes = [];
        if ($oldName !== $user->name)   $changes[] = 'Nama';
        if ($oldEmail !== $user->email) $changes[] = 'Email';
        if ($passwordChanged)            $changes[] = 'Password';

        if (!empty($changes)) {
            // Kirim ke email lama dan/atau baru (keduanya bila email berubah)
            $notifyTo = array_unique(array_filter([$oldEmail, $user->email]));
            $updatedAt = now()->timezone('Asia/Jakarta')->format('d M Y, H:i:s') . ' WIB';
            foreach ($notifyTo as $email) {
                try {
                    Mail::to($email)->send(new ProfileUpdatedMail(
                        user: $user,
                        changes: $changes,
                        updatedAt: $updatedAt,
                    ));
                } catch (\Exception $e) {}
            }
        }

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function logoutOtherDevices(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['logout_others' => 'Password saat ini tidak sesuai.']);
        }

        // Batalkan sesi di semua perangkat lain; perangkat ini tetap login.
        auth()->logoutOtherDevices($request->current_password);

        ActivityLog::record('update', "Logout dari semua perangkat lain: {$user->name}");

        return back()->with('success', 'Berhasil keluar dari semua perangkat lain.');
    }

    public function updateAvatar(Request $request)
    {
        $user = auth()->user();
        $request->validate(['avatar' => ['nullable', 'url', 'max:1000']]);

        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = $request->avatar ?: null;
        $user->save();

        ActivityLog::record('update', "Update avatar profil: {$user->name}");

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    public function updateAboutInfo(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->isDeveloper(), 403);

        $request->validate([
            'handle'         => ['nullable', 'string', 'max:80'],
            'bio'            => ['nullable', 'string', 'max:500'],
            'link_instagram' => ['nullable', 'url', 'max:255'],
            'link_github'    => ['nullable', 'url', 'max:255'],
            'link_portfolio' => ['nullable', 'url', 'max:255'],
            'link_email'     => ['nullable', 'email', 'max:150'],
        ]);

        $user->handle         = $request->handle ?: null;
        $user->bio            = $request->bio ?: null;
        $user->link_instagram = $request->link_instagram ?: null;
        $user->link_github    = $request->link_github ?: null;
        $user->link_portfolio = $request->link_portfolio ?: null;
        $user->link_email     = $request->link_email ?: null;
        $user->save();

        ActivityLog::record('update', 'Update social links & bio halaman About');

        return back()->with('success', 'Social links & bio berhasil diperbarui.');
    }

    public function updateAboutAvatar(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->isDeveloper(), 403);

        $request->validate([
            'about_avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ], [
            'about_avatar.required' => 'Pilih file foto terlebih dahulu.',
            'about_avatar.image'    => 'File harus berupa gambar.',
            'about_avatar.mimes'    => 'Format: JPG, PNG, WebP, atau GIF.',
            'about_avatar.max'      => 'Ukuran maksimal 5 MB.',
        ]);

        $file      = $request->file('about_avatar');
        $ext       = $file->getClientOriginalExtension() ?: 'jpg';
        $filename  = 'about_' . $user->id . '.' . $ext;
        $path      = 'avatars/about/' . $filename;

        // Hapus file lama (semua ekstensi) jika berbeda
        if ($user->about_avatar && $user->about_avatar !== $path) {
            Storage::disk('public')->delete($user->about_avatar);
        }

        $file->storeAs('avatars/about', $filename, 'public');
        $user->about_avatar = $path;
        $user->save();

        return back()->with('success', 'Foto halaman About berhasil diperbarui.');
    }
}
