<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = ['user_id', 'target_user_id', 'is_broadcast', 'title', 'content', 'due_date', 'color', 'photo_path', 'is_done', 'done_at'];

    protected $appends = ['photo_url'];

    protected $casts = [
        'due_date'     => 'date',
        'is_done'      => 'boolean',
        'is_broadcast' => 'boolean',
        'done_at'      => 'datetime',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? asset('storage/' . $this->photo_path) : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
