<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OperatorController extends Controller
{

    public function create()
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        return Inertia::render('Users/Form', [
            'mode'   => 'create',
            'action' => route('operators.store'),
            'resource' => [
                'label'            => 'Operator',
                'role'             => 'operator',
                'emailRequired'    => false,
                'usernameOptional' => false,
                'withDepartment'   => true,
                'minPassword'      => 6,
                'indexUrl'         => route('management.index', ['tab' => 'operator']),
            ],
            'departments' => $departments->map(fn ($d) => ['value' => $d->name, 'label' => $d->name])->values(),
        ]);
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

        return redirect()->route('management.index', ['tab' => 'operator'])
            ->with('success', "Operator '{$operator->name}' berhasil ditambahkan.");
    }

    public function edit(User $operator)
    {
        abort_if($operator->role !== 'operator', 404);
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        return Inertia::render('Users/Form', [
            'mode'   => 'edit',
            'action' => route('operators.update', $operator->id),
            'user'   => [
                'id'         => $operator->id,
                'name'       => $operator->name,
                'username'   => $operator->username,
                'email'      => $operator->email,
                'department' => $operator->department,
                'is_active'  => (bool) $operator->is_active,
            ],
            'resource' => [
                'label'            => 'Operator',
                'role'             => 'operator',
                'emailRequired'    => false,
                'usernameOptional' => false,
                'withDepartment'   => true,
                'minPassword'      => 6,
                'indexUrl'         => route('management.index', ['tab' => 'operator']),
            ],
            'departments' => $departments->map(fn ($d) => ['value' => $d->name, 'label' => $d->name])->values(),
        ]);
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

        return redirect()->route('management.index', ['tab' => 'operator'])
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

        return redirect()->route('management.index', ['tab' => 'operator'])
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
