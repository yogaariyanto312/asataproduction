<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Popup "jangan jadikan patokan" di menu Gambar Kerja, diatur dari Settings (web & API Android). */
class PengingatGambarKerjaTest extends TestCase
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
        $u = new User(['name' => ucfirst($role), 'username' => $role . 'gk', 'email' => $role . '.gk@uji.test', 'role' => $role, 'department' => 'QC-WELDING', 'password' => Hash::make('rahasia123')]);
        $u->forceFill(['is_active' => true])->save();

        return $u;
    }

    private function propsGambarKerja(User $u): array
    {
        // Ganti user dalam satu tes: hash password sesi lama ditolak AuthenticateSession.
        $this->flushSession();

        return $this->actingAs($u)->get(route('gambar-kerja.index'))->assertOk()->viewData('page')['props']['pengingat'];
    }

    public function test_bawaan_aktif_tiga_detik_dengan_pesan_bawaan(): void
    {
        $this->assertSame(
            ['aktif' => true, 'detik' => 3, 'pesan' => BotSetting::PESAN_PENGINGAT_GK],
            $this->propsGambarKerja($this->akun('operator')),
        );
    }

    public function test_diatur_di_settings_lalu_terbaca_di_web_dan_api(): void
    {
        $dev = $this->akun('developer');
        $this->actingAs($dev)->post(route('developer.bot-settings.update'), [
            'gk_popup_enabled' => '1',
            'gk_popup_detik'   => '7',
            'gk_popup_pesan'   => 'Cek gambar asli di ruang QC.',
        ])->assertSessionHasNoErrors();

        $harap = ['aktif' => true, 'detik' => 7, 'pesan' => 'Cek gambar asli di ruang QC.'];
        $this->assertSame($harap, $this->propsGambarKerja($this->akun('operator')));

        // API Android memakai controller yang sama lewat 'jembatan'.
        $this->app['auth']->forgetGuards();
        $api = $this->withToken($this->akun('mandor')->createToken('uji')->plainTextToken)
            ->getJson('/api/v1/gambar-kerja')->assertOk();
        $this->assertSame($harap, $api->json('pengingat'));
    }

    public function test_bisa_dimatikan_dan_pesan_kosong_kembali_ke_bawaan(): void
    {
        $dev = $this->akun('developer');
        $this->actingAs($dev)->post(route('developer.bot-settings.update'), [
            'gk_popup_enabled' => '0', 'gk_popup_pesan' => '',
        ])->assertSessionHasNoErrors();

        $p = $this->propsGambarKerja($dev);
        $this->assertFalse($p['aktif']);
        $this->assertSame(BotSetting::PESAN_PENGINGAT_GK, $p['pesan']);
    }

    public function test_menyimpan_bagian_lain_tidak_mengubah_pengingat(): void
    {
        BotSetting::instance()->update(['gk_popup_detik' => 9, 'gk_popup_enabled' => false]);
        $this->actingAs($this->akun('developer'))->post(route('developer.bot-settings.update'), ['reject_threshold' => '7'])->assertSessionHasNoErrors();

        $s = BotSetting::query()->first();
        $this->assertSame(9, $s->gk_popup_detik);
        $this->assertFalse($s->gk_popup_enabled);
    }

    public function test_lama_tampil_divalidasi(): void
    {
        $dev = $this->akun('developer');
        foreach (['0', '31', 'abc'] as $salah) {
            $this->actingAs($dev)->post(route('developer.bot-settings.update'), ['gk_popup_detik' => $salah])->assertSessionHasErrors('gk_popup_detik');
        }
        $this->actingAs($dev)->post(route('developer.bot-settings.update'), ['gk_popup_pesan' => str_repeat('a', 301)])->assertSessionHasErrors('gk_popup_pesan');
    }

    public function test_settings_menampilkan_isian_pengingat(): void
    {
        $props = $this->actingAs($this->akun('developer'))->get(route('developer.bot-settings'))->assertOk()->viewData('page')['props'];
        $this->assertTrue($props['setting']['gk_popup_enabled']);
        $this->assertSame(3, $props['setting']['gk_popup_detik']);
        $this->assertSame(BotSetting::PESAN_PENGINGAT_GK, $props['pesanPengingatBawaan']);
    }
}
