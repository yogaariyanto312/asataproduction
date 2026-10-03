<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penanda "sudah selesai" milik satu penerima pada satu catatan broadcast.
 */
class NoteCompletion extends Model
{
    protected $fillable = ['note_id', 'user_id', 'done_at'];

    protected $casts = ['done_at' => 'datetime'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
