<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // yang menginput
            $table->string('operator_name')->nullable();
            $table->date('replacement_date');
            $table->integer('qty')->default(1);
            $table->string('recipient')->nullable();        // penerima / tujuan
            $table->string('reason')->nullable();           // alasan / penyebab (mis. kecelakaan di jalan)
            $table->string('original_serial')->nullable();  // nomor urut / seri unit asli yang diganti
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index('replacement_date');
            $table->index('product_id');
            $table->index(['replacement_date', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replacements');
    }
};
