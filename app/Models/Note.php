<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Note extends Model
{
    protected $fillable = ['user_id', 'target_user_id', 'is_broadcast', 'title', 'content', 'due_date', 'color', 'photo_path', 'is_done', 'done_at'];

    protected $appends = ['photo_url'];

    protected $casts = [
        'due_date'     => 'date:Y-m-d',
        'is_done'      => 'boolean',
        'is_broadcast' => 'boolean',
        'done_at'      => 'datetime',
    ];

    /**
     * Foto catatan disajikan lewat route ber-middleware auth, bukan URL storage
     * publik. Catatan bisa ditujukan ke orang tertentu dan isinya internal, jadi
     * berkasnya tidak boleh bisa dibuka siapa saja yang menebak tautannya.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? route('storage.file', ['path' => $this->photo_path]) : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /** Penanda selesai per penerima; hanya dipakai catatan broadcast. */
    public function completions(): HasMany
    {
        return $this->hasMany(NoteCompletion::class);
    }

    /**
     * Sudah selesai menurut orang ini?
     *
     * Catatan broadcast punya banyak penerima, jadi statusnya disimpan per orang
     * — kalau menumpang di kolom `is_done`, centang satu orang mencoret catatan
     * itu di layar semua orang.
     */
    public function selesaiUntuk(?int $userId): bool
    {
        if (! $this->is_broadcast) {
            return (bool) $this->is_done;
        }

        if (! $userId) {
            return false;
        }

        return $this->relationLoaded('completions')
            ? $this->completions->contains('user_id', $userId)
            : $this->completions()->where('user_id', $userId)->exists();
    }

    /** Tandai selesai / batal selesai untuk satu orang (catatan broadcast). */
    public function tandaiSelesaiUntuk(int $userId, bool $selesai): void
    {
        if ($selesai) {
            $this->completions()->updateOrCreate(['user_id' => $userId], ['done_at' => now()]);

            return;
        }

        $this->completions()->where('user_id', $userId)->delete();
    }
}
