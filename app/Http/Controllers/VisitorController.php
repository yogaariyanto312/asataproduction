<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class VisitorController extends Controller
{
    public function create()
    {
        return view('visitors.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required'       => 'Nama lengkap wajib diisi.',
            'username.required'   => 'Username wajib diisi.',
            'username.unique'     => 'Username sudah digunakan.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'password.required'   => 'Password wajib diisi.',
            'password.min'        => 'Password minimal 8 karakter.',
            'password.confirmed'  => 'Konfirmasi password tidak cocok.',
        ]);

        // Auto-generate email unik (tidak ditampilkan di form)
        $data['email']     = $data['username'] . '@visitor.internal';
        $data['role']      = 'visitor';
        $data['is_active'] = true;
        $data['password']  = Hash::make($data['password']);

        $visitor = User::create($data);
        ActivityLog::record('create', "Tambah visitor: {$visitor->name}", $visitor);

        return redirect()->route('management.index', ['tab' => 'visitor'])
            ->with('success', "Visitor '{$visitor->name}' berhasil ditambahkan.");
    }

    public function edit(User $visitor)
    {
        abort_if($visitor->role !== 'visitor', 404);
        return view('visitors.edit', compact('visitor'));
    }

    public function update(Request $request, User $visitor)
    {
        abort_if($visitor->role !== 'visitor', 404);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($visitor->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required'       => 'Nama lengkap wajib diisi.',
            'username.required'   => 'Username wajib diisi.',
            'username.unique'     => 'Username sudah digunakan.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'password.min'        => 'Password minimal 8 karakter.',
            'password.confirmed'  => 'Konfirmasi password tidak cocok.',
        ]);

        // Update email jika username berubah
        $data['email'] = $data['username'] . '@visitor.internal';

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $visitor->update($data);
        ActivityLog::record('update', "Edit visitor: {$visitor->name}", $visitor);

        return redirect()->route('management.index', ['tab' => 'visitor'])
            ->with('success', "Data visitor '{$visitor->name}' berhasil diperbarui.");
    }

    public function destroy(User $visitor)
    {
        abort_if($visitor->role !== 'visitor', 404);

        $name = $visitor->name;
        $visitor->delete();
        ActivityLog::record('delete', "Hapus visitor: {$name}");

        return redirect()->route('management.index', ['tab' => 'visitor'])
            ->with('success', "Visitor '{$name}' berhasil dihapus.");
    }
}
