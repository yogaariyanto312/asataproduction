<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminController extends Controller
{

    public function create()
    {
        return Inertia::render('Users/Form', [
            'mode'   => 'create',
            'action' => route('admins.store'),
            'resource' => [
                'label'            => 'Admin',
                'role'             => 'admin',
                'emailRequired'    => true,
                'usernameOptional' => true,
                'withDepartment'   => false,
                'minPassword'      => 8,
                'indexUrl'         => route('management.index', ['tab' => 'admin']),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required'      => 'Nama lengkap wajib diisi.',
            'email.required'     => 'Email wajib diisi.',
            'email.email'        => 'Format email tidak valid.',
            'email.unique'       => 'Email sudah digunakan.',
            'username.unique'    => 'Username sudah digunakan.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'password.required'  => 'Password wajib diisi.',
            'password.min'       => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $data['role']      = 'admin';
        $data['is_active'] = true;
        $data['password']  = Hash::make($data['password']);

        $admin = User::create($data);
        ActivityLog::record('create', "Tambah admin: {$admin->name}", $admin);

        return redirect()->route('management.index', ['tab' => 'admin'])
            ->with('success', "Admin '{$admin->name}' berhasil ditambahkan.");
    }

    public function edit(User $admin)
    {
        abort_if($admin->role !== 'admin', 404);
        return Inertia::render('Users/Form', [
            'mode'   => 'edit',
            'action' => route('admins.update', $admin->id),
            'user'   => [
                'id'         => $admin->id,
                'name'       => $admin->name,
                'username'   => $admin->username,
                'email'      => $admin->email,
                'department' => $admin->department,
                'is_active'  => (bool) $admin->is_active,
            ],
            'resource' => [
                'label'            => 'Admin',
                'role'             => 'admin',
                'emailRequired'    => true,
                'usernameOptional' => true,
                'withDepartment'   => false,
                'minPassword'      => 8,
                'indexUrl'         => route('management.index', ['tab' => 'admin']),
            ],
        ]);
    }

    public function update(Request $request, User $admin)
    {
        abort_if($admin->role !== 'admin', 404);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($admin->id)],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($admin->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required'      => 'Nama lengkap wajib diisi.',
            'email.required'     => 'Email wajib diisi.',
            'email.email'        => 'Format email tidak valid.',
            'email.unique'       => 'Email sudah digunakan.',
            'username.unique'    => 'Username sudah digunakan.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'password.min'       => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $data['username'] = $data['username'] ?: null;

        $admin->update($data);
        ActivityLog::record('update', "Edit admin: {$admin->name}", $admin);

        return redirect()->route('management.index', ['tab' => 'admin'])
            ->with('success', "Data admin '{$admin->name}' berhasil diperbarui.");
    }

    public function destroy(User $admin)
    {
        abort_if($admin->role !== 'admin', 404);

        if ($admin->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        $name = $admin->name;
        $admin->delete();
        ActivityLog::record('delete', "Hapus admin: {$name}");

        return redirect()->route('management.index', ['tab' => 'admin'])
            ->with('success', "Admin '{$name}' berhasil dihapus.");
    }
}
