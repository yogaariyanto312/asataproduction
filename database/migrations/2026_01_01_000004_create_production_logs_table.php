<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // operator yang input
            $table->date('production_date');
            $table->integer('shift1_qty')->default(0);
            $table->integer('shift2_qty')->default(0);
            $table->integer('shift3_qty')->default(0);
            $table->integer('total_qty')->storedAs('shift1_qty + shift2_qty + shift3_qty'); // auto-calculated
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'confirmed'])->default('confirmed');
            $table->timestamps();

            // Index untuk pencarian cepat
            $table->index('production_date');
            $table->index('product_id');
            $table->index('user_id');
            $table->index(['production_date', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_logs');
    }
};
