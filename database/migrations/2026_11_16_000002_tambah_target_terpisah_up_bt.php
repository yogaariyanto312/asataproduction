<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Target produk channel dipisah per deret: UP dan BT masing-masing punya target dan titik nol (baseline).
 * Kolom lama (target_qty, baseline_qty) tetap berisi nilai gabungan (UP + BT) / 2 supaya bot, widget, dan
 * aplikasi Android/Windows yang memakainya tidak berubah. Kosong (null) = target biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_targets', function (Blueprint $table) {
            $table->unsignedInteger('target_up')->nullable()->after('target_qty');
            $table->unsignedInteger('target_bt')->nullable()->after('target_up');
            $table->unsignedInteger('baseline_up')->nullable()->after('baseline_qty');
            $table->unsignedInteger('baseline_bt')->nullable()->after('baseline_up');
        });
    }

    public function down(): void
    {
        Schema::table('production_targets', function (Blueprint $table) {
            $table->dropColumn(['target_up', 'target_bt', 'baseline_up', 'baseline_bt']);
        });
    }
};
