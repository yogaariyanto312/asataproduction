<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks untuk jalur realtime saat riwayat membesar (uji skala 554 ribu baris):
 *  - (product_id, total_qty): SUM total per produk (Target Produksi, Dashboard) terbaca dari indeks saja.
 *  - updated_at: poll "ada perubahan?" memakai MAX(updated_at) tanpa memindai seluruh tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            if (! Schema::hasIndex('production_logs', 'production_logs_product_id_total_qty_index')) {
                $table->index(['product_id', 'total_qty'], 'production_logs_product_id_total_qty_index');
            }
            if (! Schema::hasIndex('production_logs', 'production_logs_updated_at_index')) {
                $table->index('updated_at', 'production_logs_updated_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropIndex('production_logs_product_id_total_qty_index');
            $table->dropIndex('production_logs_updated_at_index');
        });
    }
};
