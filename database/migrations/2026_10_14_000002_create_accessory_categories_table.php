<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Kategori (nama/jenis) aksesoris sebagai daftar tersendiri supaya bisa diubah & dihapus.
// Diisi dari nama yang sudah dipakai; nama dengan beda huruf besar/kecil dianggap sama.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accessory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->timestamps();
        });

        $sudah = [];
        foreach (DB::table('accessories')->select('name')->distinct()->orderBy('name')->pluck('name') as $nama) {
            $nama = trim((string) $nama);
            $kunci = mb_strtolower($nama);
            if ($nama === '' || isset($sudah[$kunci])) {
                continue;
            }
            $sudah[$kunci] = true;
            DB::table('accessory_categories')->insert(['name' => $nama, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accessory_categories');
    }
};
