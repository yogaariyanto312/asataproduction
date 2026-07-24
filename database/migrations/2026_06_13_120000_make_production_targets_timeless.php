<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Target kini tanpa batas waktu: 1 target aktif per produk.
        // Hapus duplikat lama (beda target_date), simpan baris terbaru per produk.
        $keepIds = DB::table('production_targets')
            ->select(DB::raw('MAX(id) as keep_id'))
            ->groupBy('product_id')
            ->pluck('keep_id')
            ->all();

        if (!empty($keepIds)) {
            DB::table('production_targets')->whereNotIn('id', $keepIds)->delete();
        }

        Schema::table('production_targets', function (Blueprint $table) {
            // Total produksi kumulatif produk saat target dibuat (titik nol progres).
            $table->unsignedInteger('baseline_qty')->default(0)->after('target_qty');
            // Waktu target pertama kali tercapai; dipakai untuk auto-hapus 2 jam setelahnya.
            $table->timestamp('reached_at')->nullable()->after('notes');
            // target_date tidak lagi dipakai untuk logika; biarkan untuk arsip.
            $table->date('target_date')->nullable()->change();
        });

        Schema::table('production_targets', function (Blueprint $table) {
            // Satu target aktif per produk + sekaligus index untuk FK product_id.
            $table->unique('product_id', 'production_targets_product_id_unique');
            $table->dropUnique('production_targets_product_id_target_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('production_targets', function (Blueprint $table) {
            $table->unique(['product_id', 'target_date'], 'production_targets_product_id_target_date_unique');
            $table->dropUnique('production_targets_product_id_unique');
        });

        Schema::table('production_targets', function (Blueprint $table) {
            $table->dropColumn(['baseline_qty', 'reached_at']);
            $table->date('target_date')->nullable(false)->change();
        });
    }
};
