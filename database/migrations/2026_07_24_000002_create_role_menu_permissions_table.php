<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_menu_permissions')) {
            return; // tabel sudah dibuat pada percobaan sebelumnya — jangan gagal
        }

        Schema::create('role_menu_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('menu_key', 50);
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->unique(['role', 'menu_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu_permissions');
    }
};
