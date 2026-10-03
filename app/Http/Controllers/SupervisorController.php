<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SupervisorController extends Controller
{

    public function create()
    {
        return Inertia::render('Users/Form', [
            'mode'   => 'create',
            'action' => route('supervisors.store'),
            'resource' => [
                'label'            => 'Supervisor',
                'role'             => 'supervisor',
                'emailRequired'    => true,
                'usernameOptional' => true,
                'withDepartment'   => false,
                'minPassword'      => 8,
                'indexUrl'         => route('management.index', ['tab' => 'supervisor']),
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

        $data['role']      = 'supervisor';
        $data['is_active'] = true;
        $data['password']  = Hash::make($data['password']);

        $supervisor = User::create($data);
        ActivityLog::record('create', "Tambah supervisor: {$supervisor->name}", $supervisor);

        return redirect()->route('management.index', ['tab' => 'supervisor'])
            ->with('success', "Supervisor '{$supervisor->name}' berhasil ditambahkan.");
    }

    public function edit(User $supervisor)
    {
        abort_if($supervisor->role !== 'supervisor', 404);
        return Inertia::render('Users/Form', [
            'mode'   => 'edit',
            'action' => route('supervisors.update', $supervisor->id),
            'user'   => [
                'id'         => $supervisor->id,
                'name'       => $supervisor->name,
                'username'   => $supervisor->username,
                'email'      => $supervisor->email,
                'department' => $supervisor->department,
                'is_active'  => (bool) $supervisor->is_active,
            ],
            'resource' => [
                'label'            => 'Supervisor',
                'role'             => 'supervisor',
                'emailRequired'    => true,
                'usernameOptional' => true,
                'withDepartment'   => false,
                'minPassword'      => 8,
                'indexUrl'         => route('management.index', ['tab' => 'supervisor']),
            ],
        ]);
    }

    public function update(Request $request, User $supervisor)
    {
        abort_if($supervisor->role !== 'supervisor', 404);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($supervisor->id)],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($supervisor->id)],
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

        $supervisor->update($data);
        ActivityLog::record('update', "Edit supervisor: {$supervisor->name}", $supervisor);

        return redirect()->route('management.index', ['tab' => 'supervisor'])
            ->with('success', "Data supervisor '{$supervisor->name}' berhasil diperbarui.");
    }

    public function destroy(User $supervisor)
    {
        abort_if($supervisor->role !== 'supervisor', 404);

        if ($supervisor->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        $name = $supervisor->name;
        $supervisor->delete();
        ActivityLog::record('delete', "Hapus supervisor: {$name}");

        return redirect()->route('management.index', ['tab' => 'supervisor'])
            ->with('success', "Supervisor '{$name}' berhasil dihapus.");
    }
}
