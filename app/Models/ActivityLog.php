<?php

namespace App\Models;

use App\Services\BotNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper untuk merekam aktivitas
    public static function record(string $action, string $description, $model = null): void
    {
        self::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'model_type' => $model ? class_basename($model) : null,
            'model_id'   => $model?->id,
            'description' => $description,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        // Kirim notifikasi bot SETELAH response terkirim ke browser agar aksi
        // (simpan/hapus) terasa instan — panggilan HTTP ke Telegram/Discord tidak
        // lagi menahan response.
        $user = auth()->user();
        dispatch(function () use ($action, $description, $user) {
            try {
                BotNotificationService::notify($action, $description, $user);
            } catch (\Throwable) {}
        })->afterResponse();
    }
}
