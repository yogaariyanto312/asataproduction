<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentMenuPermission extends Model
{
    protected $fillable = ['department', 'menu_key', 'allowed'];

    protected $casts = ['allowed' => 'boolean'];
}
