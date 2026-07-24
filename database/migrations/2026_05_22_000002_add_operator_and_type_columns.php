<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('type', ['regular', 'channel'])->default('regular')->after('category_id');
        });

        Schema::table('production_logs', function (Blueprint $table) {
            $table->string('operator_name', 150)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropColumn('operator_name');
        });
    }
};
