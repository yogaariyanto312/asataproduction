<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->decimal('reject_threshold', 5, 2)->default(5.00)->after('discord_enabled');
            $table->boolean('report_enabled')->default(false)->after('reject_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->dropColumn(['reject_threshold', 'report_enabled']);
        });
    }
};
