<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class MandorController extends Controller
{
    public function index(Request $request)
    {
        $mandors = User::where('role', 'mandor')
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('mandors.index', compact('mandors'));
    }

    public function create()
    {
        return view('mandors.create');
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

        return redirect()->route('mandors.index')
            ->with('success', "Mandor '{$mandor->name}' berhasil ditambahkan.");
    }

    public function edit(User $mandor)
    {
        abort_if($mandor->role !== 'mandor', 404);
        return view('mandors.edit', compact('mandor'));
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

        return redirect()->route('mandors.index')
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

        return redirect()->route('mandors.index')
            ->with('success', "Mandor '{$name}' berhasil dihapus.");
    }
}
