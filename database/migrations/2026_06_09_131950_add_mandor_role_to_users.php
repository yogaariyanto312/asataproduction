<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite: role sudah jadi string sejak migrasi supervisor — no-op di sini.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('developer', 'admin', 'supervisor', 'mandor', 'operator', 'visitor') DEFAULT 'operator'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('developer', 'admin', 'supervisor', 'operator', 'visitor') DEFAULT 'operator'");
        }
    }
};
