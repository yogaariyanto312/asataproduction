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

        if (! $user) {
            return;
        }

        // Developer melihat semua departemen, kecuali ia memilih menyempitkan tampilan lewat menu akun
        // (sesi 'dept_lihat'). Hanya memengaruhi tampilan: entri baru tetap ikut departemen akunnya.
        if ($user->role === 'developer') {
            $lihat = self::departemenDilihat();
            if ($lihat !== null) {
                $builder->where($model->qualifyColumn('department'), $lihat);
            }

            return;
        }

        $column = $model->qualifyColumn('department');

        if ($user->department) {
            $builder->where($column, $user->department);
        } else {
            $builder->whereNull($column);
        }
    }

    /** Departemen yang sedang disaring developer, atau null (semua). Hanya ada di permintaan web (punya sesi). */
    public static function departemenDilihat(): ?string
    {
        // Hanya bila permintaan saat ini punya sesi (web); perintah artisan/antrean tidak.
        if (! app()->bound('request') || ! request()->hasSession()) {
            return null;
        }

        $nilai = request()->session()->get('dept_lihat');

        return is_string($nilai) && $nilai !== '' ? $nilai : null;
    }
}
