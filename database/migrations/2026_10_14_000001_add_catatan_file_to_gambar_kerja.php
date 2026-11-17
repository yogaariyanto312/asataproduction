<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Keterangan per file (mis. "Revisi ke-2"). Kolom `keterangan` milik grup: updateInfo menimpanya ke semua file.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gambar_kerja', function (Blueprint $table) {
            $table->string('catatan_file', 200)->nullable()->after('keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('gambar_kerja', function (Blueprint $table) {
            $table->dropColumn('catatan_file');
        });
    }
};
