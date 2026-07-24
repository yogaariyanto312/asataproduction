<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gambar_kerja', function (Blueprint $table) {
            $table->boolean('is_thumbnail')->default(false)->after('urutan');
        });
    }

    public function down(): void
    {
        Schema::table('gambar_kerja', function (Blueprint $table) {
            $table->dropColumn('is_thumbnail');
        });
    }
};
