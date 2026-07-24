<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['sender_id', 'recipient_id', 'message', 'reply', 'replied_at', 'is_read'];

    protected $casts = [
        'replied_at' => 'datetime',
        'is_read'    => 'boolean',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function isReplied(): bool
    {
        return !is_null($this->reply);
    }
}
