<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gambar_kerja', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun')->nullable()->after('kva');
        });
    }

    public function down(): void
    {
        Schema::table('gambar_kerja', function (Blueprint $table) {
            $table->dropColumn('tahun');
        });
    }
};
