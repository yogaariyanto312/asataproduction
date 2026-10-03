<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DepartmentController extends Controller
{

    public function create()
    {
        return Inertia::render('Departments/Form', [
            'mode'     => 'create',
            'action'   => route('departments.store'),
            'indexUrl' => route('management.index', ['tab' => 'department']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:departments,name'],
        ], [
            'name.required' => 'Nama departemen wajib diisi.',
            'name.unique'   => 'Departemen sudah terdaftar.',
            'name.max'      => 'Nama departemen maksimal 100 karakter.',
        ]);

        $data['is_active'] = true;
        $dept = Department::create($data);
        ActivityLog::record('create', "Tambah departemen: {$dept->name}", $dept);

        return redirect()->route('management.index', ['tab' => 'department'])
            ->with('success', "Departemen '{$dept->name}' berhasil ditambahkan.");
    }

    public function edit(Department $department)
    {
        return Inertia::render('Departments/Form', [
            'mode'       => 'edit',
            'action'     => route('departments.update', $department->id),
            'indexUrl'   => route('management.index', ['tab' => 'department']),
            'department' => [
                'id'        => $department->id,
                'name'      => $department->name,
                'is_active' => (bool) $department->is_active,
            ],
        ]);
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($department->id)],
            'is_active' => ['boolean'],
        ], [
            'name.required' => 'Nama departemen wajib diisi.',
            'name.unique'   => 'Departemen sudah terdaftar.',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $department->update($data);
        ActivityLog::record('update', "Edit departemen: {$department->name}", $department);

        return redirect()->route('management.index', ['tab' => 'department'])
            ->with('success', "Departemen '{$department->name}' berhasil diperbarui.");
    }

    public function destroy(Department $department)
    {
        $operatorCount = $department->operators()->where('role', 'operator')->count();
        if ($operatorCount > 0) {
            return back()->with('error', "Departemen '{$department->name}' tidak bisa dihapus karena masih digunakan oleh {$operatorCount} operator.");
        }

        $name = $department->name;
        $department->delete();
        ActivityLog::record('delete', "Hapus departemen: {$name}");

        return redirect()->route('management.index', ['tab' => 'department'])
            ->with('success', "Departemen '{$name}' berhasil dihapus.");
    }

    // Quick-add dari dashboard (AJAX-style POST, redirect back)
    public function quickStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:departments,name'],
        ], [
            'name.required' => 'Nama departemen wajib diisi.',
            'name.unique'   => 'Departemen sudah terdaftar.',
        ]);

        $dept = Department::create(['name' => $data['name'], 'is_active' => true]);
        ActivityLog::record('create', "Tambah departemen: {$dept->name}", $dept);

        return back()->with('success', "Departemen '{$dept->name}' berhasil ditambahkan.");
    }
}
