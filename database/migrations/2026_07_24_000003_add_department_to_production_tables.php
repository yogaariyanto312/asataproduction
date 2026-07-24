<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 RBAC — pemisahan data per-departemen.
 *
 * Menambahkan kolom `department` (snapshot nama departemen penginput) ke tabel
 * produksi/pengganti/aksesoris, lalu backfill dari `users.department` berdasar
 * user_id tiap record.
 */
return new class extends Migration
{
    private array $tables = ['production_logs', 'replacements', 'accessories'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'department')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('department', 100)->nullable()->after('user_id')->index();
                });
            }

            // Backfill dari departemen penginput (user_id).
            DB::table($table)
                ->whereNull("$table.department")
                ->update([
                    'department' => DB::raw(
                        "(SELECT u.department FROM users u WHERE u.id = $table.user_id)"
                    ),
                ]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'department')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex([ 'department' ]);
                    $t->dropColumn('department');
                });
            }
        }
    }
};
