<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar fitur Departemen di Input & Riwayat Produksi (pilihan "Departemen tujuan"
 * dan saring departemen, khusus developer). Diatur di menu Settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->boolean('fitur_departemen')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->dropColumn('fitur_departemen');
        });
    }
};
