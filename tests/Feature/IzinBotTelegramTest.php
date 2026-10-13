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

/** Kartu & penautan Telegram diatur lewat Hak Akses Menu ("Bot Telegram"), bukan daftar peran tetap. */
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
        $this->assertNotNull($this->kartuTelegram($this->akun('supervisor')));
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

        $this->assertNotNull($this->kartuTelegram($this->akun('operator')));
    }
}
