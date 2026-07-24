<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DeveloperController extends Controller
{
    public function create()
    {
        return view('developers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $data['role']      = 'developer';
        $data['is_active'] = true;
        $data['password']  = Hash::make($data['password']);

        $dev = User::create($data);
        ActivityLog::record('create', "Tambah developer: {$dev->name}", $dev);

        return redirect()->route('management.index', ['tab' => 'developer'])
            ->with('success', "Developer '{$dev->name}' berhasil ditambahkan.");
    }

    public function edit(User $developer)
    {
        abort_if($developer->role !== 'developer', 404);
        return view('developers.edit', compact('developer'));
    }

    public function update(Request $request, User $developer)
    {
        abort_if($developer->role !== 'developer', 404);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($developer->id)],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($developer->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $data['username'] = $data['username'] ?: null;
        $developer->update($data);
        ActivityLog::record('update', "Edit developer: {$developer->name}", $developer);

        return redirect()->route('management.index', ['tab' => 'developer'])
            ->with('success', "Data developer '{$developer->name}' berhasil diperbarui.");
    }

    public function destroy(User $developer)
    {
        abort_if($developer->role !== 'developer', 404);

        if ($developer->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        $name = $developer->name;
        $developer->delete();
        ActivityLog::record('delete', "Hapus developer: {$name}");

        return redirect()->route('management.index', ['tab' => 'developer'])
            ->with('success', "Developer '{$name}' berhasil dihapus.");
    }
}
