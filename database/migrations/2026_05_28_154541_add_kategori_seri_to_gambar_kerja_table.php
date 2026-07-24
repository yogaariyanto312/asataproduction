<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gambar_kerja', function (Blueprint $table) {
            $table->string('kategori_seri', 20)->nullable()->default('pln')->after('kva');
        });
    }

    public function down(): void
    {
        Schema::table('gambar_kerja', function (Blueprint $table) {
            $table->dropColumn('kategori_seri');
        });
    }
};
