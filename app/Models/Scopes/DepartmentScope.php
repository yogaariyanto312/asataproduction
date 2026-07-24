<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope pemisahan data per-departemen (Fase 2 RBAC).
 *
 * Aturan:
 *  - developer          → lihat SEMUA departemen (tak di-scope).
 *  - tak terautentikasi → tak di-scope (konteks console/queue; mis. bot berjalan
 *    di afterResponse tetap harus pakai withoutGlobalScope agar tak keliru).
 *  - role lain          → hanya melihat record departemennya sendiri. Jika user
 *    belum punya departemen (null), hanya melihat record yang department-nya null.
 */
class DepartmentScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user || $user->role === 'developer') {
            return;
        }

        $column = $model->qualifyColumn('department');

        if ($user->department) {
            $builder->where($column, $user->department);
        } else {
            $builder->whereNull($column);
        }
    }
}
