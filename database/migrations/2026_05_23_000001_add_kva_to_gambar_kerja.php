<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gambar_kerja', function (Blueprint $table) {
            $table->string('kva', 50)->nullable()->after('seri');
        });
    }

    public function down(): void
    {
        Schema::table('gambar_kerja', function (Blueprint $table) {
            $table->dropColumn('kva');
        });
    }
};
