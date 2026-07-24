<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_token')->nullable();
            $table->string('telegram_chat_id', 100)->nullable();
            $table->boolean('telegram_enabled')->default(false);
            $table->string('discord_webhook', 500)->nullable();
            $table->boolean('discord_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_settings');
    }
};
