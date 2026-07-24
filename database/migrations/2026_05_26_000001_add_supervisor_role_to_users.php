<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'supervisor', 'operator') DEFAULT 'operator'");
        } else {
            // SQLite: ubah role jadi string biasa (lepas check constraint enum) agar
            // semua role bisa disimpan. Migrasi role berikutnya jadi no-op di SQLite.
            Schema::table('users', fn (Blueprint $t) => $t->string('role')->default('operator')->change());
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'operator') DEFAULT 'operator'");
        }
    }
};
