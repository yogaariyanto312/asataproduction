<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\SchedulePhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bot Telegram hanya boleh mengubah data atas nama akun yang DITAUTKAN.
 * Dulu siapa pun yang tahu username bot bisa menimpa foto jadwal produksi.
 */
class TelegramPenautanTest extends TestCase
{
    use RefreshDatabase;

    private const TG_ID = '987654321';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.webhook_secret' => null]);
        Storage::fake('public');
        Http::fake([
            'api.telegram.org/bot*/getFile*' => Http::response(['ok' => true, 'result' => ['file_path' => 'photos/x.jpg']]),
            'api.telegram.org/file/*'         => Http::response('ISI-GAMBAR'),
            '*'                               => Http::response(['ok' => true]),
        ]);
        BotSetting::instance()->update(['telegram_token' => 'TOKENUJI']);
    }

    private function user(string $role): User
    {
        $u = new User([
            'name' => "Uji {$role}", 'username' => "uji_{$role}", 'email' => "{$role}@uji.test",
            'role' => $role, 'password' => Hash::make('rahasia123'),
        ]);
        $u->forceFill(['is_active' => true])->save();

        return $u;
    }

    private function kirim(?string $text = null, array $extra = []): void
    {
        $message = array_merge([
            'message_id' => 1,
            'chat'       => ['id' => 555],
            'from'       => ['id' => (int) self::TG_ID],
        ], $text !== null ? ['text' => $text] : [], $extra);

        $this->postJson('/telegram/webhook', ['message' => $message])->assertOk();
    }

    private function kodeUntuk(User $user): string
    {
        $this->actingAs($user)->post('/profile/telegram/kode')->assertRedirect();

        return session('telegram_kode');
    }

    public function test_pengirim_tak_tertaut_tidak_bisa_mengunggah_jadwal(): void
    {
        $this->kirim('/jadwal');
        $this->kirim(null, ['photo' => [['file_id' => 'f1']]]);

        $this->assertSame(0, SchedulePhoto::count());
    }

    public function test_tautkan_lalu_unggah_jadwal_tercatat_atas_nama_pengguna(): void
    {
        $admin = $this->user('admin');
        $kode  = $this->kodeUntuk($admin);

        $this->kirim('/tautkan ' . $kode);
        $this->assertSame(self::TG_ID, $admin->fresh()->telegram_user_id);

        // Perintah unggah kini "/jadwal upload <tanggal>" (seperti referensi) dan
        // jadwal berlaku seminggu: tanggalnya dibulatkan ke Senin minggu itu.
        $this->kirim('/jadwal upload 2026-09-01');
        $this->kirim(null, ['photo' => [['file_id' => 'kecil'], ['file_id' => 'besar']]]);

        $foto = SchedulePhoto::sole();
        $this->assertSame('2026-08-31', $foto->target_date->toDateString());
        $this->assertSame($admin->id, $foto->uploaded_by);
        Storage::disk('public')->assertExists($foto->file_path);
    }

    public function test_kode_hanya_sekali_pakai(): void
    {
        $admin = $this->user('admin');
        $kode  = $this->kodeUntuk($admin);

        $this->kirim('/tautkan ' . $kode);
        $this->actingAs($admin)->delete('/profile/telegram')->assertRedirect();
        $this->assertNull($admin->fresh()->telegram_user_id);

        // Kode yang sama dipakai lagi → ditolak
        $this->kirim('/tautkan ' . $kode);
        $this->assertNull($admin->fresh()->telegram_user_id);
    }

    public function test_peran_operator_tidak_boleh_menautkan(): void
    {
        $op = $this->user('operator');
        $this->actingAs($op)->post('/profile/telegram/kode')->assertForbidden();
    }

    public function test_tautan_dicabut_di_tengah_sesi_membatalkan_unggahan(): void
    {
        $admin = $this->user('admin');
        $this->kirim('/tautkan ' . $this->kodeUntuk($admin));
        $this->kirim('/jadwal');

        $admin->forceFill(['is_active' => false])->save();
        $this->kirim(null, ['photo' => [['file_id' => 'f1']]]);

        $this->assertSame(0, SchedulePhoto::count());
    }

    public function test_secret_token_salah_ditolak(): void
    {
        config(['services.telegram.webhook_secret' => 'rahasia-webhook']);

        $this->postJson('/telegram/webhook', ['message' => ['chat' => ['id' => 1], 'text' => '/start']])
            ->assertForbidden();
        $this->postJson('/telegram/webhook', ['message' => ['chat' => ['id' => 1], 'text' => '/start']],
            ['X-Telegram-Bot-Api-Secret-Token' => 'rahasia-webhook'])
            ->assertOk();
    }
}
