<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warna kartu produk di Master Produk (hex #rrggbb). Seperti urutan,
 * disimpan sama di semua varian satu nama produk; kosong = warna bawaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('warna_ikon', 7)->nullable()->after('urutan');
            $table->string('warna_teks', 7)->nullable()->after('warna_ikon');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['warna_ikon', 'warna_teks']);
        });
    }
};
