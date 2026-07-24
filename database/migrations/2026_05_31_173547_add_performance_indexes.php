<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cross-DB (MySQL & SQLite): ambil nama index lewat Schema introspection
        $existing = fn(string $table) => collect(Schema::getIndexes($table))->pluck('name');

        Schema::table('production_logs', function (Blueprint $table) use ($existing) {
            $idx = $existing('production_logs');
            if (!$idx->contains('production_logs_production_date_index'))
                $table->index('production_date');
            if (!$idx->contains('production_logs_reject_qty_index'))
                $table->index('reject_qty');
        });

        Schema::table('production_targets', function (Blueprint $table) use ($existing) {
            $idx = $existing('production_targets');
            if (!$idx->contains('production_targets_target_date_index'))
                $table->index('target_date');
        });
    }

    public function down(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropIndex(['production_date']);
            $table->dropIndex(['reject_qty']);
        });
        Schema::table('production_targets', function (Blueprint $table) {
            $table->dropIndex(['target_date']);
        });
    }
};
