<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sisa-sisa "shift" dibuang dari basis data.
 *
 * Aplikasi ini sudah lama tidak memakai konsep shift: angkanya disebut UP dan
 * BT di semua layar. Yang tertinggal cuma di basis data, dan bedanya nama itu
 * bikin bingung tiap kali membaca kode.
 *
 *  - shift1_qty / shift2_qty  -> up_qty / bt_qty (isinya dipertahankan)
 *  - shift3_qty               -> dibuang; selalu dipaksa 0 oleh controller dan
 *                                tidak pernah ditampilkan di mana pun
 *  - production_logs.shift    -> dibuang; kolom enum pagi/siang/malam yang tidak
 *                                pernah diisi maupun dibaca
 *  - tabel shifts             -> dibuang; model Shift-nya nol pemakaian
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dipisah dari drop: sebagian basis data menyusun ulang tabelnya saat
        // kolom dibuang, dan itu tidak boleh terjadi di tengah penggantian nama.
        Schema::table('production_logs', function (Blueprint $table) {
            $table->renameColumn('shift1_qty', 'up_qty');
            $table->renameColumn('shift2_qty', 'bt_qty');
        });

        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropColumn('shift3_qty');

            if (Schema::hasColumn('production_logs', 'shift')) {
                $table->dropColumn('shift');
            }
        });

        Schema::dropIfExists('shifts');
    }

    public function down(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('production_logs', function (Blueprint $table) {
            $table->integer('shift3_qty')->default(0);
            $table->enum('shift', ['pagi', 'siang', 'malam'])->nullable();
        });

        Schema::table('production_logs', function (Blueprint $table) {
            $table->renameColumn('up_qty', 'shift1_qty');
            $table->renameColumn('bt_qty', 'shift2_qty');
        });
    }
};
