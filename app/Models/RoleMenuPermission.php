<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleMenuPermission extends Model
{
    protected $fillable = ['role', 'menu_key', 'allowed'];

    protected $casts = ['allowed' => 'boolean'];
}
