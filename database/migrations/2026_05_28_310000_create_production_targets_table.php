<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->date('target_date');
            $table->unsignedInteger('target_qty');
            $table->string('notes', 200)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_targets');
    }
};
