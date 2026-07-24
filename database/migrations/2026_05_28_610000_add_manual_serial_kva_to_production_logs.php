<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->string('manual_series', 100)->nullable()->after('notes');
            $table->string('manual_kva', 50)->nullable()->after('manual_series');
        });
    }

    public function down(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropColumn(['manual_series', 'manual_kva']);
        });
    }
};
