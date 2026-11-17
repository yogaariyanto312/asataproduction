<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status "selesai" per penerima untuk catatan yang dikirim ke Semua User.
 *
 * Sebelumnya status itu menumpang di kolom `notes.is_done`, satu baris dipakai
 * bersama semua penerima: begitu satu orang mencentang selesai, catatannya ikut
 * tercoret di layar semua orang. Catatan personal tetap memakai kolom lama
 * karena penerimanya cuma satu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('done_at');
            $table->timestamps();

            // Satu orang hanya punya satu status per catatan.
            $table->unique(['note_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_completions');
    }
};
