<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menautkan akun Telegram ke pengguna aplikasi.
 *
 * Sebelum ini bot sama sekali tidak tahu SIAPA yang mengirim perintah: siapa pun
 * yang menemukan username botnya bisa mengirim /jadwal beserta foto, dan foto
 * itu tersimpan sebagai jadwal produksi resmi.
 *
 * Dengan id Telegram tersimpan di sini, perintah bot bisa memakai sistem izin
 * yang sama dengan aplikasinya (MenuAccess) — bukan daftar khusus yang mudah
 * ketinggalan zaman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Disimpan sebagai teks: id Telegram bisa melampaui rentang integer
            // 32-bit, dan tidak pernah dipakai untuk hitungan apa pun.
            $table->string('telegram_user_id', 32)->nullable()->unique()->after('remember_token');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telegram_user_id']);
            $table->dropColumn(['telegram_user_id', 'telegram_linked_at']);
        });
    }
};
