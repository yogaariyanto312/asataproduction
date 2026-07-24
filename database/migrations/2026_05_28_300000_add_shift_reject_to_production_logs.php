<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->enum('shift', ['pagi', 'siang', 'malam'])->nullable()->after('keterangan');
            $table->unsignedInteger('reject_qty')->default(0)->after('shift');
            $table->enum('reject_category', ['material', 'mesin', 'human_error', 'desain', 'lainnya'])->nullable()->after('reject_qty');
            $table->string('reject_notes', 300)->nullable()->after('reject_category');
        });
    }

    public function down(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropColumn(['shift', 'reject_qty', 'reject_category', 'reject_notes']);
        });
    }
};
