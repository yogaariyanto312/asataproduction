<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Filter departemen khusus developer (role lain sudah di-scope otomatis
     * lewat [[App\Models\Scopes\DepartmentScope]]). Menerapkan filter ke $query
     * bila ada, lalu mengembalikan [daftar departemen untuk dropdown, nilai aktif].
     *
     * @param  string  $column  Nama kolom department (boleh ter-kualifikasi tabel).
     * @return array{0:\Illuminate\Support\Collection,1:?string}
     */
    protected function departmentFilter(Request $request, $query, string $column = 'department'): array
    {
        // Khusus developer, dan hanya bila fitur Departemen menyala di Settings (sama dengan Input/Riwayat).
        if (! \App\Models\BotSetting::departemenUntuk(auth()->user())) {
            return [collect(), null];
        }

        $deptFilter = trim((string) $request->input('department')) ?: null;
        if ($deptFilter) {
            $query->where($column, $deptFilter);
        }

        $departments = Department::where('is_active', true)->orderBy('name')->pluck('name');

        return [$departments, $deptFilter];
    }

    /**
     * Nilai filter departemen aktif untuk developer (null untuk role lain, yang
     * sudah di-scope otomatis). Dipakai saat perlu nilai filter tanpa builder,
     * mis. untuk export.
     */
    protected function developerDeptFilter(Request $request): ?string
    {
        // Khusus developer, dan hanya bila fitur Departemen menyala di Settings (sama dengan Input/Riwayat).
        if (! \App\Models\BotSetting::departemenUntuk(auth()->user())) {
            return null;
        }

        return trim((string) $request->input('department')) ?: null;
    }

    /** Daftar departemen aktif untuk dropdown filter (developer). */
    protected function departmentOptions(): \Illuminate\Support\Collection
    {
        // Khusus developer, dan hanya bila fitur Departemen menyala di Settings (sama dengan Input/Riwayat).
        if (! \App\Models\BotSetting::departemenUntuk(auth()->user())) {
            return collect();
        }

        return Department::where('is_active', true)->orderBy('name')->pluck('name');
    }
}
