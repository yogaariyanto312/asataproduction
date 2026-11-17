<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satuan aksesoris sempat tersimpan dengan ejaan berbeda ("Unit" vs "unit"),
 * sehingga daftar menampilkan dua bentuk untuk satuan yang sama. Penyimpanan
 * baru sudah dinormalkan lewat mutator di model; baris lama disamakan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accessories')) {
            return;
        }

        DB::table('accessories')
            ->whereNotNull('unit')
            ->update(['unit' => DB::raw('LOWER(TRIM(unit))')]);
    }

    public function down(): void
    {
        // Ejaan lama tidak dipulihkan: bentuk huruf kecil adalah bentuk yang benar.
    }
};
