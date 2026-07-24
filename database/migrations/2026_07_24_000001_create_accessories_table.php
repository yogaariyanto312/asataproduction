<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accessories', function (Blueprint $table) {
            $table->id();
            // Seri produk terkait — opsional (aksesoris lepas boleh tanpa produk)
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // yang menginput
            $table->string('operator_name')->nullable();
            $table->date('accessory_date');
            $table->string('name');                         // nama/jenis aksesoris (mis. Box Panel, Tahanan CP)
            $table->string('serial_number')->nullable();    // nomor urut aksesoris (jika terkait seri)
            $table->integer('qty')->default(1);
            $table->string('unit')->nullable();             // satuan (pcs, set, kg, dll)
            $table->string('recipient')->nullable();        // penerima / tujuan kirim
            $table->string('purpose')->nullable();          // tujuan / keperluan
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index('accessory_date');
            $table->index('product_id');
            $table->index(['accessory_date', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accessories');
    }
};
