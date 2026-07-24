<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalisasi Fase 2 — seluruh data produksi/pengganti/aksesoris yang ADA saat
 * ini adalah milik departemen QC-WELDING (departemen lain belum punya data).
 *
 * Backfill sebelumnya (migration 000003) menandai sebagian record dengan
 * departemen penginput (mis. developer = "Management"); di sini disamakan semua
 * ke QC-WELDING agar operator QC-WELDING melihat seluruh histori.
 */
return new class extends Migration
{
    private string $dept = 'QC-WELDING';

    private array $tables = ['production_logs', 'replacements', 'accessories'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'department')) {
                DB::table($table)->update(['department' => $this->dept]);
            }
        }
    }

    public function down(): void
    {
        // Tidak dapat mengembalikan pembagian departemen sebelumnya (data historis
        // hilang). Sengaja no-op.
    }
};
