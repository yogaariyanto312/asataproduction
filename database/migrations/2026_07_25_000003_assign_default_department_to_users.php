<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kondisi saat ini hanya ada satu departemen (QC-WELDING). Seluruh user non-dev
 * aktif yang belum punya departemen ditugaskan ke QC-WELDING agar bisa melihat
 * data produksi (yang telah dinormalisasi ke QC-WELDING pada migration 000004).
 *
 * Developer sengaja dikecualikan — bypass scope & tetap lihat semua departemen.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('department')
            ->where('role', '!=', 'developer')
            ->where('is_active', true)
            ->update(['department' => 'QC-WELDING']);
    }

    public function down(): void
    {
        // No-op: tidak dapat mengembalikan departemen NULL sebelumnya dengan akurat.
    }
};
