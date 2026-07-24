<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Nama index yang sudah ada pada tabel messages (portabel MySQL & SQLite). */
    private function existingIndexes(): array
    {
        return collect(Schema::getIndexes('messages'))
            ->pluck('name')->unique()->all();
    }

    public function up(): void
    {
        $existing = $this->existingIndexes();

        Schema::table('messages', function (Blueprint $table) use ($existing) {
            // Lookup pesan per lawan bicara (recipient_id index sudah ada dari FK).
            if (!in_array('messages_recipient_id_index', $existing, true)) {
                $table->index('recipient_id');
            }
            // Penghitungan unread per lawan bicara.
            if (!in_array('messages_recipient_id_is_read_index', $existing, true)) {
                $table->index(['recipient_id', 'is_read']);
            }
            // Untuk auto-hapus pesan > 7 hari (WHERE created_at <= ...).
            if (!in_array('messages_created_at_index', $existing, true)) {
                $table->index('created_at');
            }
        });
    }

    public function down(): void
    {
        $existing = $this->existingIndexes();

        Schema::table('messages', function (Blueprint $table) use ($existing) {
            if (in_array('messages_recipient_id_is_read_index', $existing, true)) {
                $table->dropIndex(['recipient_id', 'is_read']);
            }
            if (in_array('messages_created_at_index', $existing, true)) {
                $table->dropIndex(['created_at']);
            }
        });
    }
};
