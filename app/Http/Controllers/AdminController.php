<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $admins = User::where('role', 'admin')
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admins.index', compact('admins'));
    }

    public function create()
    {
        return view('admins.create');
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

        return redirect()->route('admins.index')
            ->with('success', "Admin '{$admin->name}' berhasil ditambahkan.");
    }

    public function edit(User $admin)
    {
        abort_if($admin->role !== 'admin', 404);
        return view('admins.edit', compact('admin'));
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

        return redirect()->route('admins.index')
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

        return redirect()->route('admins.index')
            ->with('success', "Admin '{$name}' berhasil dihapus.");
    }
}
