<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hak akses menu per-departemen (Fase 2 lanjutan).
 *
 * Mirip role_menu_permissions, tetapi dimensi-nya departemen. Akses efektif =
 * role MENGIZINKAN && departemen MENGIZINKAN (lihat App\Support\MenuAccess::can).
 * Default bila tak ada baris: departemen mengizinkan (true) — developer mencabut
 * per departemen sesuai kebutuhan. Menu ber-flag `dept_shared` (Master Produk,
 * Kategori) tak pernah di-gate departemen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('department_menu_permissions')) {
            return; // tabel sudah dibuat pada percobaan sebelumnya — jangan gagal
        }

        Schema::create('department_menu_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('department', 100);
            $table->string('menu_key', 100);
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->unique(['department', 'menu_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_menu_permissions');
    }
};
