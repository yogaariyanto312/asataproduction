<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\DepartmentMenuPermission;
use App\Models\RoleMenuPermission;
use App\Support\MenuAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    /**
     * Bangun daftar menu yang bisa diatur, masing-masing beserta baris permission-nya
     * (view + aksi). Bentuk: [ ['label'=>, 'icon'/'paths'=>, 'perms'=>[ ['key','label','shared'], ... ] ], ... ]
     */
    protected function matrix(): array
    {
        $rows = [];
        foreach (MenuAccess::items() as $item) {
            if (!($item['manageable'] ?? false)) continue;

            $shared = (bool) ($item['dept_shared'] ?? false);

            $perms = [[
                'key'    => $item['key'],
                'label'  => 'Lihat',
                'shared' => $shared,
            ]];
            foreach ($item['actions'] ?? [] as $act) {
                $perms[] = ['key' => $act['key'], 'label' => $act['label'], 'shared' => $shared];
            }

            $rows[] = [
                'label'  => $item['label'],
                'icon'   => $item['icon']  ?? null,
                'paths'  => $item['paths'] ?? null,
                'perms'  => $perms,
                'shared' => $shared,
            ];
        }
        return $rows;
    }

    /** Semua permission key yang dikelola (untuk validasi saat simpan). */
    protected function allKeys(): array
    {
        $keys = [];
        foreach ($this->matrix() as $row) {
            foreach ($row['perms'] as $p) $keys[] = $p['key'];
        }
        return $keys;
    }

    public function index(Request $request)
    {
        $rows        = $this->matrix();
        $roles       = config('menus.manageable_roles', []);
        $departments = Department::where('is_active', true)->orderBy('name')->pluck('name');

        // Mode departemen bila ?department= valid; selain itu mode role (default).
        $selectedDept = $request->input('department');
        if ($selectedDept && !$departments->contains($selectedDept)) {
            $selectedDept = null;
        }

        if ($selectedDept) {
            // State per departemen (single-dimensi): key => bool.
            $deptState = [];
            foreach ($this->allKeys() as $key) {
                $deptState[$key] = MenuAccess::departmentAllowed($selectedDept, $key);
            }

            return view('permissions.index', compact(
                'rows', 'roles', 'departments', 'selectedDept', 'deptState'
            ));
        }

        // Mode role: status efektif tiap sel (role|key) => bool, untuk state switch.
        $state = [];
        foreach ($roles as $role) {
            foreach ($this->allKeys() as $key) {
                $state["{$role}|{$key}"] = MenuAccess::allowed($role, $key);
            }
        }

        return view('permissions.index', compact(
            'rows', 'roles', 'departments', 'selectedDept', 'state'
        ));
    }

    public function update(Request $request)
    {
        $departments = Department::where('is_active', true)->orderBy('name')->pluck('name');
        $selectedDept = $request->input('department');

        // Mode departemen: simpan ke department_menu_permissions (skip menu dept_shared).
        if ($selectedDept && $departments->contains($selectedDept)) {
            $checked = (array) $request->input('allowed', []);

            DB::transaction(function () use ($selectedDept, $checked) {
                foreach ($this->allKeys() as $key) {
                    DepartmentMenuPermission::updateOrCreate(
                        ['department' => $selectedDept, 'menu_key' => $key],
                        ['allowed' => isset($checked[$key])],
                    );
                }
            });

            MenuAccess::flush();
            ActivityLog::record('update', "Perbarui hak akses menu departemen: {$selectedDept}");

            return redirect()->route('permissions.index', ['department' => $selectedDept])
                ->with('success', "Hak akses departemen {$selectedDept} berhasil diperbarui.");
        }

        // Mode role (default).
        $roles   = config('menus.manageable_roles', []);
        $allKeys = $this->allKeys();
        $checked = (array) $request->input('allowed', []);

        DB::transaction(function () use ($roles, $allKeys, $checked) {
            foreach ($roles as $role) {
                foreach ($allKeys as $key) {
                    $allowed = isset($checked[$role][$key]);
                    RoleMenuPermission::updateOrCreate(
                        ['role' => $role, 'menu_key' => $key],
                        ['allowed' => $allowed],
                    );
                }
            }
        });

        MenuAccess::flush();
        ActivityLog::record('update', 'Perbarui hak akses menu & aksi per role');

        return redirect()->route('permissions.index')
            ->with('success', 'Hak akses berhasil diperbarui.');
    }
}
