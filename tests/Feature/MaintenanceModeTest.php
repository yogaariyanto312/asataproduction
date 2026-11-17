<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Saklar Settings → Maintenance.
 *
 * Dulu hanya ditegakkan di layout Blade lama, jadi halaman React tidak terkunci
 * dan data tetap bisa diambil lewat URL/API. Sekarang dijaga MaintenanceMode.
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private function nyalakan(?string $sampai = null, ?string $pesan = 'Ganti mesin las'): void
    {
        BotSetting::instance()->update([
            'maintenance_mode'    => true,
            'maintenance_message' => $pesan,
            'maintenance_until'   => $sampai,
        ]);
        BotSetting::lupakan();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_mati_semua_berjalan_normal(): void
    {
        $this->actingAs($this->user('operator'))->get(route('dashboard'))
            ->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('Dashboard'));
    }

    public function test_non_developer_hanya_melihat_layar_maintenance_di_halaman_mana_pun(): void
    {
        $this->nyalakan();

        foreach (['operator', 'admin', 'supervisor', 'mandor', 'visitor'] as $peran) {
            $this->flushSession();
            $u = $this->user($peran);

            foreach (['dashboard', 'production.index', 'notes.index'] as $route) {
                $this->actingAs($u)->get(route($route))
                    ->assertStatus(503)
                    ->assertInertia(fn (AssertableInertia $p) => $p
                        ->component('Maintenance')
                        ->where('message', 'Ganti mesin las')
                        ->where('user.name', $u->name));
            }
        }
    }

    public function test_kunjungan_inertia_dibalas_halaman_maintenance_berstatus_sukses(): void
    {
        $this->nyalakan();
        $u = $this->user('operator');
        $versi = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        $res = $this->actingAs($u)
            ->withHeaders(array_filter(['X-Inertia' => 'true', 'X-Inertia-Version' => $versi, 'X-Requested-With' => 'XMLHttpRequest']))
            ->get(route('production.index'));

        $res->assertOk();
        $this->assertSame('Maintenance', $res->json('component'));
    }

    public function test_api_dan_simpan_data_ditolak_503(): void
    {
        $this->nyalakan();
        $u = $this->user('operator');

        $this->actingAs($u)->getJson(route('api.dashboard.live'))->assertStatus(503)->assertJson(['maintenance' => true]);
        $this->actingAs($u)->getJson(route('notes.list'))->assertStatus(503);
        $this->actingAs($u)->postJson(route('notes.store'), ['title' => 'x'])->assertStatus(503);
        $this->assertSame(0, \App\Models\Note::count());
    }

    public function test_developer_tetap_bisa_memakai_aplikasi_dan_melihat_penanda(): void
    {
        $this->nyalakan();

        $this->actingAs($this->user('developer'))->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Dashboard')
                ->where('maintenanceAktif.settingsUrl', route('developer.bot-settings')));
    }

    public function test_logout_tetap_bisa_selama_maintenance(): void
    {
        $this->nyalakan();

        $this->actingAs($this->user('operator'))->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_berakhir_sendiri_setelah_waktu_selesai(): void
    {
        $this->nyalakan(now()->addHour()->toDateTimeString());
        $u = $this->user('operator');

        $this->actingAs($u)->get(route('dashboard'))->assertStatus(503)
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Maintenance')->whereNot('until', null));

        $this->travel(61)->minutes();
        BotSetting::lupakan();

        $this->actingAs($u)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Dashboard'));
    }

    public function test_pesan_bawaan_bila_kosong(): void
    {
        $this->nyalakan(null, null);

        $this->actingAs($this->user('operator'))->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('message', fn ($m) => str_contains($m, 'pemeliharaan sistem')));
    }
}
