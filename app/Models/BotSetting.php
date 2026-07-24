<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotSetting extends Model
{
    protected $fillable = [
        'telegram_token',
        'telegram_chat_id',
        'telegram_report_chat_id',
        'telegram_enabled',
        'discord_webhook',
        'discord_enabled',
        'reject_threshold',
        'report_enabled',
        'tutorial_iframe_url',
        'disable_devtools',
        'maintenance_mode',
        'maintenance_message',
        'maintenance_until',
    ];

    protected $casts = [
        'telegram_enabled'  => 'boolean',
        'discord_enabled'   => 'boolean',
        'report_enabled'    => 'boolean',
        'reject_threshold'  => 'decimal:2',
        'disable_devtools'  => 'boolean',
        'maintenance_mode'  => 'boolean',
        'maintenance_until' => 'datetime',
    ];

    public static function instance(): static
    {
        return static::first() ?? static::create([
            'telegram_enabled' => false,
            'discord_enabled'  => false,
        ]);
    }
}
