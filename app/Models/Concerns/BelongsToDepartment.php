<?php

namespace App\Models\Concerns;

use App\Models\Scopes\DepartmentScope;

/**
 * Menandai model sebagai data ber-departemen:
 *  - menerapkan global scope pemisahan data ([[DepartmentScope]]);
 *  - mengisi kolom `department` otomatis dari departemen user yang membuat
 *    record (bila belum diisi eksplisit).
 *
 * Model harus punya kolom `department` (string, nullable) & mencantumkannya
 * di $fillable.
 */
trait BelongsToDepartment
{
    protected static function bootBelongsToDepartment(): void
    {
        static::addGlobalScope(new DepartmentScope);

        static::creating(function ($model) {
            if (empty($model->department) && auth()->check()) {
                $model->department = auth()->user()->department;
            }
        });
    }
}
