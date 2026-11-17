<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\RoleMenuPermission;
use App\Models\User;
use App\Services\Telegram\PenautanAkun;
use App\Support\MenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Kode penautan baru khusus Developer; izin bot dan pemutusan tautan lama dipertahankan. */
class IzinBotTelegramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Department::create(['name' => 'QC-WELDING', 'is_active' => true]);
    }

    private function akun(string $role): User
    {
        $u = new User(['name' => ucfirst($role), 'username' => $role . 'tg', 'email' => $role . '.tg@uji.test', 'role' => $role, 'department' => 'QC-WELDING', 'password' => Hash::make('rahasia123')]);
        $u->forceFill(['is_active' => true])->save();

        return $u;
    }

    private function kartuTelegram(User $u): ?array
    {
        $this->flushSession();

        return $this->actingAs($u)->get(route('profile.edit'))->assertOk()->viewData('page')['props']['telegram'] ?? null;
    }

    public function test_bawaan_developer_admin_supervisor(): void
    {
        $this->assertSame(['developer', 'admin', 'supervisor'], PenautanAkun::peran());
        $this->assertNotNull($this->kartuTelegram($this->akun('developer')));
        $this->assertNull($this->kartuTelegram($this->akun('supervisor')));
        $this->assertNull($this->kartuTelegram($this->akun('operator')));
        $this->assertFalse(collect(MenuAccess::items())->firstWhere('key', 'telegram')['nav']);
    }

    public function test_dimatikan_untuk_supervisor_kartu_hilang_dan_kode_ditolak(): void
    {
        RoleMenuPermission::updateOrCreate(['role' => 'supervisor', 'menu_key' => 'telegram'], ['allowed' => false]);
        MenuAccess::flush();

        $sup = $this->akun('supervisor');
        $this->assertNull($this->kartuTelegram($sup));
        $this->flushSession();
        $this->actingAs($sup)->post(route('profile.telegram.kode'))->assertForbidden();
        $this->assertSame(['developer', 'admin'], PenautanAkun::peran());
    }

    public function test_dinyalakan_untuk_operator(): void
    {
        RoleMenuPermission::updateOrCreate(['role' => 'operator', 'menu_key' => 'telegram'], ['allowed' => true]);
        MenuAccess::flush();

        $u = $this->akun('operator');
        $this->assertNull($this->kartuTelegram($u));
        $this->actingAs($u)->post(route('profile.telegram.kode'))->assertForbidden();
    }

    public function test_semua_role_non_developer_ditolak_oleh_api_penautan(): void
    {
        foreach (['admin', 'supervisor', 'mandor', 'operator', 'visitor'] as $role) {
            $u = $this->akun($role);
            $this->actingAs($u, 'sanctum')->postJson('/api/v1/profil/telegram/kode')->assertForbidden();
        }
        $this->actingAs($this->akun('developer'), 'sanctum')->postJson('/api/v1/profil/telegram/kode')
            ->assertOk()->assertJsonStructure(['kode', 'berlakuMenit']);
    }

    public function test_kode_sebelum_peran_berubah_tidak_bisa_ditukar(): void
    {
        $u = $this->akun('developer');
        $kode = PenautanAkun::buatKode($u);
        $u->update(['role' => 'admin']);
        $this->assertNull(PenautanAkun::tukarkan($kode, '555'));
        $this->assertNull($u->fresh()->telegram_user_id);
    }

    public function test_tautan_lama_tetap_memakai_izin_bot_dan_bisa_diputus(): void
    {
        $u = $this->akun('supervisor');
        $u->forceFill(['telegram_user_id' => '555', 'telegram_linked_at' => now()])->save();
        $kartu = $this->kartuTelegram($u);
        $this->assertTrue($kartu['linked']);
        $this->assertNull($kartu['kodeUrl']);
        $this->assertSame($u->id, PenautanAkun::pengguna('555')?->id);
        $this->actingAs($u)->delete(route('profile.telegram.putus'))->assertRedirect();
        $this->assertNull($u->fresh()->telegram_user_id);
    }
}
