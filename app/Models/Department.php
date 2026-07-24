<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    // Operator yang terdaftar di departemen ini (match by name string)
    public function operators(): HasMany
    {
        return $this->hasMany(User::class, 'department', 'name');
    }
}
