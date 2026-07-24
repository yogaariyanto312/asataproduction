<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gambar_kerja', function (Blueprint $table) {
            $table->string('seri', 150)->nullable()->after('judul');
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gambar_kerja', function (Blueprint $table) {
            $table->dropColumn('seri');
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });
    }
};
