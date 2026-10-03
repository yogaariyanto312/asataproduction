<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'department',
        'avatar',
        'about_avatar',
        'handle',
        'bio',
        'link_instagram',
        'link_github',
        'link_portfolio',
        'link_email',
        'last_seen_at',
        'typing_to',
        'typing_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'telegram_user_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at'      => 'datetime',
            'telegram_linked_at'=> 'datetime',
            'typing_at'         => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function avatarUrl(): ?string
    {
        if (!$this->avatar) return null;
        if (str_starts_with($this->avatar, 'http')) return $this->avatar;
        return route('storage.file', ['path' => $this->avatar]);
    }

    /**
     * Gerbang masuk panel Filament (/admin).
     *
     * Panel ini kini menjadi rumah Dashboard, yang menurut config/menus.php
     * boleh dilihat semua peran termasuk visitor. Karena itu semua akun aktif
     * dipersilakan masuk — pembatasan isi diserahkan ke tiap widget lewat
     * canView(), bukan ke gerbang panel. Akun nonaktif tetap ditolak, sejajar
     * dengan pengecekan is_active saat login.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function isDeveloper(): bool
    {
        return $this->role === 'developer';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function isMandor(): bool
    {
        return $this->role === 'mandor';
    }

    public function isVisitor(): bool
    {
        return $this->role === 'visitor';
    }

    public function isPrivileged(): bool
    {
        return in_array($this->role, ['developer', 'admin', 'supervisor']);
    }

    public function productionLogs(): HasMany
    {
        return $this->hasMany(ProductionLog::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
