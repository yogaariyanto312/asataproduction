<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MandorController extends Controller
{

    public function create()
    {
        return Inertia::render('Users/Form', [
            'mode'   => 'create',
            'action' => route('mandors.store'),
            'resource' => [
                'label'            => 'Mandor',
                'role'             => 'mandor',
                'emailRequired'    => true,
                'usernameOptional' => true,
                'withDepartment'   => false,
                'minPassword'      => 8,
                'indexUrl'         => route('management.index', ['tab' => 'mandor']),
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
            'name.required'       => 'Nama lengkap wajib diisi.',
            'email.required'      => 'Email wajib diisi.',
            'email.email'         => 'Format email tidak valid.',
            'email.unique'        => 'Email sudah digunakan.',
            'username.unique'     => 'Username sudah digunakan.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'password.required'   => 'Password wajib diisi.',
            'password.min'        => 'Password minimal 8 karakter.',
            'password.confirmed'  => 'Konfirmasi password tidak cocok.',
        ]);

        $data['role']      = 'mandor';
        $data['is_active'] = true;
        $data['password']  = Hash::make($data['password']);

        $mandor = User::create($data);
        ActivityLog::record('create', "Tambah mandor: {$mandor->name}", $mandor);

        return redirect()->route('management.index', ['tab' => 'mandor'])
            ->with('success', "Mandor '{$mandor->name}' berhasil ditambahkan.");
    }

    public function edit(User $mandor)
    {
        abort_if($mandor->role !== 'mandor', 404);
        return Inertia::render('Users/Form', [
            'mode'   => 'edit',
            'action' => route('mandors.update', $mandor->id),
            'user'   => [
                'id'         => $mandor->id,
                'name'       => $mandor->name,
                'username'   => $mandor->username,
                'email'      => $mandor->email,
                'department' => $mandor->department,
                'is_active'  => (bool) $mandor->is_active,
            ],
            'resource' => [
                'label'            => 'Mandor',
                'role'             => 'mandor',
                'emailRequired'    => true,
                'usernameOptional' => true,
                'withDepartment'   => false,
                'minPassword'      => 8,
                'indexUrl'         => route('management.index', ['tab' => 'mandor']),
            ],
        ]);
    }

    public function update(Request $request, User $mandor)
    {
        abort_if($mandor->role !== 'mandor', 404);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($mandor->id)],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($mandor->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required'       => 'Nama lengkap wajib diisi.',
            'email.required'      => 'Email wajib diisi.',
            'email.email'         => 'Format email tidak valid.',
            'email.unique'        => 'Email sudah digunakan.',
            'username.unique'     => 'Username sudah digunakan.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'password.min'        => 'Password minimal 8 karakter.',
            'password.confirmed'  => 'Konfirmasi password tidak cocok.',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $data['username'] = $data['username'] ?: null;

        $mandor->update($data);
        ActivityLog::record('update', "Edit mandor: {$mandor->name}", $mandor);

        return redirect()->route('management.index', ['tab' => 'mandor'])
            ->with('success', "Data mandor '{$mandor->name}' berhasil diperbarui.");
    }

    public function destroy(User $mandor)
    {
        abort_if($mandor->role !== 'mandor', 404);

        if ($mandor->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        $name = $mandor->name;
        $mandor->delete();
        ActivityLog::record('delete', "Hapus mandor: {$name}");

        return redirect()->route('management.index', ['tab' => 'mandor'])
            ->with('success', "Mandor '{$name}' berhasil dihapus.");
    }
}
