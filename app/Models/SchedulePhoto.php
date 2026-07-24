<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchedulePhoto extends Model
{
    protected $fillable = ['target_date', 'file_path', 'uploaded_by'];

    protected $casts = ['target_date' => 'date'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
