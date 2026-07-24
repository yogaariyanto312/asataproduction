<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('ukuran');
            $table->string('panjang', 50)->nullable()->after('kva');
            $table->string('lebar', 50)->nullable()->after('panjang');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['panjang', 'lebar']);
            $table->string('ukuran', 100)->nullable()->after('kva');
        });
    }
};
