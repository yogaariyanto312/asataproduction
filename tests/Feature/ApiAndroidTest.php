<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\ProductionTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** API aplikasi Android (/api/v1, token Sanctum). */
class ApiAndroidTest extends TestCase
{
    use RefreshDatabase;

    private Product $tangki;
    private Product $channel;

    protected function setUp(): void
    {
        parent::setUp();
        Department::create(['name' => 'QC-WELDING', 'is_active' => true]);
        Department::create(['name' => 'ASSEMBLY', 'is_active' => true]);
        $kat = Category::create(['name' => 'Tangki-PLN', 'is_active' => true]);
        $katCh = Category::create(['name' => 'Channel-PLN', 'is_active' => true]);
        $this->tangki = Product::create(['category_id' => $kat->id, 'type' => 'regular', 'name' => 'TANGKI', 'series' => '26Q0001', 'kva' => '50', 'unit' => 'unit', 'is_active' => true]);
        $this->channel = Product::create(['category_id' => $katCh->id, 'type' => 'channel', 'name' => 'CHANNEL', 'series' => '26C0001', 'kva' => '100', 'unit' => 'unit', 'is_active' => true]);
    }

    private function akun(string $role, string $dept = 'QC-WELDING', bool $aktif = true, ?string $username = null): User
    {
        $u = new User(['name' => ucfirst($role), 'username' => $username ?? $role, 'email' => ($username ?? $role) . '@uji.test', 'role' => $role, 'department' => $dept, 'password' => Hash::make('rahasia123')]);
        $u->forceFill(['is_active' => $aktif])->save();

        return $u;
    }

    private function token(User $u): string
    {
        return $this->postJson('/api/v1/login', ['identifier' => $u->username, 'password' => 'rahasia123', 'perangkat' => 'Pixel'])
            ->assertOk()->json('token');
    }

    private function api(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->withHeader('Accept', 'application/json');
    }

    public function test_login_berhasil_mengembalikan_token_dan_izin(): void
    {
        $op = $this->akun('operator');
        $res = $this->postJson('/api/v1/login', ['identifier' => 'operator', 'password' => 'rahasia123'])->assertOk();

        $this->assertNotEmpty($res->json('token'));
        $this->assertSame('operator', $res->json('user.role'));
        $this->assertTrue($res->json('user.izin')['input-produksi.create']);
        $this->assertFalse($res->json('user.izin')['targets.edit']); // bawaan: operator tak boleh set target
    }

    public function test_login_salah_akun_nonaktif_dan_batas_percobaan(): void
    {
        $this->akun('operator');
        $this->akun('mandor', aktif: false);

        $this->postJson('/api/v1/login', ['identifier' => 'mandor', 'password' => 'rahasia123'])
            ->assertStatus(422)->assertJsonPath('errors.identifier.0', 'Akun Anda tidak aktif. Hubungi administrator.');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', ['identifier' => 'operator', 'password' => 'salah'])->assertStatus(422);
        }
        $this->assertStringContainsString('Terlalu banyak', $this->postJson('/api/v1/login', ['identifier' => 'operator', 'password' => 'rahasia123'])->json('errors.identifier.0'));
    }

    public function test_tanpa_token_401_dan_token_akun_yang_dinonaktifkan_dicabut(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);

        $op = $this->akun('operator');
        $t = $this->token($op);
        $this->api($t)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.username', 'operator');

        $op->forceFill(['is_active' => false])->save();
        $this->api($t)->getJson('/api/v1/me')->assertStatus(401);
        $this->assertSame(0, $op->tokens()->count());
    }

    public function test_input_produksi_digabung_dan_tercatat_di_departemen_pengguna(): void
    {
        $op = $this->akun('operator');
        $t = $this->token($op);
        $isi = ['product_id' => $this->tangki->id, 'production_date' => today()->toDateString(), 'total_qty' => 3, 'notes' => 'NO.001-003'];

        $this->api($t)->postJson('/api/v1/produksi', $isi)->assertOk()->assertJsonPath('success', true);
        $this->api($t)->postJson('/api/v1/produksi', ['total_qty' => 2, 'notes' => 'NO.004-005'] + $isi)
            ->assertOk()->assertJsonPath('total', 5);

        $log = ProductionLog::withoutGlobalScopes()->sole();
        $this->assertSame('QC-WELDING', $log->department);
        $this->assertSame(5.0, (float) $log->total_qty);

        $this->api($t)->postJson('/api/v1/produksi', ['product_id' => $this->tangki->id])->assertStatus(422)
            ->assertJsonValidationErrors(['production_date', 'total_qty']);
    }

    public function test_channel_total_dari_up_bt_dan_opsi_form(): void
    {
        $t = $this->token($this->akun('operator'));
        $this->api($t)->postJson('/api/v1/produksi', ['product_id' => $this->channel->id, 'production_date' => today()->toDateString(), 'up_qty' => 4, 'bt_qty' => 6, 'total_qty' => 0])
            ->assertOk()->assertJsonPath('total', 5);

        $opsi = $this->api($t)->getJson('/api/v1/produksi/opsi')->assertOk();
        $this->assertCount(Product::where('is_active', true)->count(), $opsi->json('products'));
        $this->assertContains($this->channel->id, array_column($opsi->json('products'), 'value'));
        $this->assertSame([], $opsi->json('departments')); // bukan developer
        $this->assertNotEmpty($opsi->json('rejectCategories'));
    }

    public function test_riwayat_detail_edit_hapus_terpisah_per_departemen(): void
    {
        $op = $this->akun('operator');
        $lain = $this->akun('admin', 'ASSEMBLY', username: 'adminlain');
        $log = ProductionLog::withoutGlobalScopes()->create([
            'product_id' => $this->tangki->id, 'user_id' => $op->id, 'operator_name' => 'Operator', 'department' => 'QC-WELDING',
            'production_date' => today()->toDateString(), 'total_qty' => 4, 'up_qty' => 0, 'bt_qty' => 0, 'reject_qty' => 0,
        ]);

        $t = $this->token($op);
        $this->assertSame(1, $this->api($t)->getJson('/api/v1/produksi')->assertOk()->json('days.0.count'));
        $this->api($t)->getJson("/api/v1/produksi/{$log->id}")->assertOk()->assertJsonPath('total_qty', 4);

        // Departemen lain tidak bisa membuka/mengubah/menghapus.
        $tLain = $this->token($lain);
        $this->api($tLain)->getJson("/api/v1/produksi/{$log->id}")->assertNotFound();
        $this->api($tLain)->deleteJson("/api/v1/produksi/{$log->id}")->assertNotFound();
        $this->assertSame([], $this->api($tLain)->getJson('/api/v1/produksi')->json('days'));

        $this->api($t)->putJson("/api/v1/produksi/{$log->id}", ['product_id' => $this->tangki->id, 'production_date' => today()->toDateString(), 'total_qty' => 7, 'operator_name' => 'Operator'])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame(7.0, (float) $log->fresh()->total_qty);

        $this->api($t)->postJson("/api/v1/produksi/{$log->id}/reject", ['jumlah' => 2, 'tanggal' => today()->toDateString(), 'reject_category' => array_key_first(ProductionLog::$rejectCategories)])
            ->assertOk();
        $this->assertSame(5.0, (float) $log->fresh()->total_qty);
        $this->assertSame(2, (int) $log->fresh()->reject_qty);

        $this->api($t)->deleteJson("/api/v1/produksi/{$log->id}")->assertOk()->assertJsonPath('success', true);
        $this->assertNull(ProductionLog::withoutGlobalScopes()->find($log->id));
    }

    public function test_target_ikut_hak_akses_menu(): void
    {
        $op = $this->akun('operator');
        $mandor = $this->akun('mandor');

        $this->api($this->token($op))->postJson('/api/v1/target', ['product_id' => $this->tangki->id, 'target_qty' => 10])
            ->assertForbidden();

        $tm = $this->token($mandor);
        $this->api($tm)->postJson('/api/v1/target', ['product_id' => $this->tangki->id, 'target_qty' => 10])->assertOk();
        $data = $this->api($tm)->getJson('/api/v1/target')->assertOk();
        $this->assertSame(10, $data->json('totals.target'));
        $id = ProductionTarget::sole()->id;
        $this->api($tm)->deleteJson("/api/v1/target/{$id}")->assertOk();
        $this->assertSame(0, ProductionTarget::count());
    }

    public function test_dashboard_dan_profil(): void
    {
        $op = $this->akun('operator');
        $t = $this->token($op);

        $d = $this->api($t)->getJson('/api/v1/dashboard')->assertOk();
        $this->assertCount(7, $d->json('grafik'));
        $this->assertSame(Product::where('is_active', true)->count(), $d->json('total_products'));

        $this->api($t)->putJson('/api/v1/profil', ['name' => 'Operator Baru', 'email' => 'op@uji.test', 'password' => 'baru12345', 'password_confirmation' => 'baru12345', 'current_password' => 'salah'])
            ->assertStatus(422)->assertJsonPath('errors.current_password.0', 'Password saat ini tidak sesuai.');

        $this->api($t)->putJson('/api/v1/profil', ['name' => 'Operator Baru', 'email' => 'op@uji.test', 'password' => 'baru12345', 'password_confirmation' => 'baru12345', 'current_password' => 'rahasia123'])
            ->assertOk()->assertJsonPath('user.name', 'Operator Baru');
        $this->assertTrue(Hash::check('baru12345', $op->fresh()->password));
    }

    public function test_mode_maintenance_berlaku_kecuali_developer(): void
    {
        $op = $this->akun('operator');
        $dev = $this->akun('developer');
        $tOp = $this->token($op);
        $tDev = $this->token($dev);

        BotSetting::instance()->update(['maintenance_mode' => true]);
        BotSetting::lupakan();

        $this->api($tOp)->getJson('/api/v1/dashboard')->assertStatus(503);
        $this->api($tDev)->getJson('/api/v1/dashboard')->assertOk();
    }
}
