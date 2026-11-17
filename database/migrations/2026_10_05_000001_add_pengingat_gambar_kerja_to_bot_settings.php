<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Popup pengingat saat membuka menu Gambar Kerja (web & aplikasi Android):
 * gambar di aplikasi bukan patokan, cari gambar kerja aslinya dulu.
 * Lama tampil & pesannya diatur di menu Settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->boolean('gk_popup_enabled')->default(true);
            $table->unsignedTinyInteger('gk_popup_detik')->default(3);
            $table->string('gk_popup_pesan', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->dropColumn(['gk_popup_enabled', 'gk_popup_detik', 'gk_popup_pesan']);
        });
    }
};
