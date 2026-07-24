<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class OperatorController extends Controller
{
    public function index(Request $request)
    {
        $operators = User::where('role', 'operator')
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('username', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->status !== null && $request->status !== '', fn($q) => $q->where('is_active', $request->status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('operators.index', compact('operators'));
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        return view('operators.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:150'],
            'username'   => ['required', 'string', 'max:50', 'unique:users,username'],
            'email'      => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:6', 'confirmed'],
            'department' => ['nullable', 'string', 'max:100'],
        ], [
            'name.required'      => 'Nama lengkap wajib diisi.',
            'username.required'  => 'Username wajib diisi.',
            'username.unique'    => 'Username sudah digunakan.',
            'email.email'        => 'Format email tidak valid.',
            'email.unique'       => 'Email sudah digunakan.',
            'password.required'  => 'Password wajib diisi.',
            'password.min'       => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $data['role']      = 'operator';
        $data['is_active'] = true;
        $data['password']  = Hash::make($data['password']);

        $operator = User::create($data);
        ActivityLog::record('create', "Tambah operator: {$operator->name}", $operator);

        return redirect()->route('operators.index')
            ->with('success', "Operator '{$operator->name}' berhasil ditambahkan.");
    }

    public function edit(User $operator)
    {
        abort_if($operator->role !== 'operator', 404);
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        return view('operators.edit', compact('operator', 'departments'));
    }

    public function update(Request $request, User $operator)
    {
        abort_if($operator->role !== 'operator', 404);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:150'],
            'username'   => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($operator->id)],
            'email'      => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($operator->id)],
            'password'   => ['nullable', 'string', 'min:6', 'confirmed'],
            'department' => ['nullable', 'string', 'max:100'],
            'is_active'  => ['boolean'],
        ], [
            'name.required'      => 'Nama lengkap wajib diisi.',
            'username.required'  => 'Username wajib diisi.',
            'username.unique'    => 'Username sudah digunakan.',
            'email.email'        => 'Format email tidak valid.',
            'email.unique'       => 'Email sudah digunakan.',
            'password.min'       => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $data['is_active'] = $request->boolean('is_active');

        $operator->update($data);
        ActivityLog::record('update', "Edit operator: {$operator->name}", $operator);

        return redirect()->route('operators.index')
            ->with('success', "Data operator '{$operator->name}' berhasil diperbarui.");
    }

    public function destroy(User $operator)
    {
        abort_if($operator->role !== 'operator', 404);

        if ($operator->productionLogs()->exists()) {
            return back()->with('error', "Operator '{$operator->name}' tidak bisa dihapus karena memiliki data produksi.");
        }

        $name = $operator->name;
        $operator->delete();
        ActivityLog::record('delete', "Hapus operator: {$name}");

        return redirect()->route('operators.index')
            ->with('success', "Operator '{$name}' berhasil dihapus.");
    }

    public function toggleActive(User $operator)
    {
        abort_if($operator->role !== 'operator', 404);

        $operator->update(['is_active' => !$operator->is_active]);
        $status = $operator->is_active ? 'diaktifkan' : 'dinonaktifkan';
        ActivityLog::record('update', "Operator {$operator->name} {$status}");

        return back()->with('success', "Operator '{$operator->name}' berhasil {$status}.");
    }
}
