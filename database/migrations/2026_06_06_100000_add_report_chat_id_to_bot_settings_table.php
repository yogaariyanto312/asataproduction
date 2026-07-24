<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            // Chat ID khusus laporan produksi harian — terpisah dari telegram_chat_id
            // (yang dipakai untuk log aktivitas server). Jika kosong, fallback ke telegram_chat_id.
            $table->string('telegram_report_chat_id', 100)->nullable()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->dropColumn('telegram_report_chat_id');
        });
    }
};
