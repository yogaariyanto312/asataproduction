<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\Category;
use App\Models\Department;
use App\Models\GambarKerja;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Saklar "Fitur Departemen" di Settings (Input & Riwayat Produksi, web + API Android)
 * dan info pengunggah + waktu upload di kartu Gambar Kerja.
 */
class FiturDepartemenDanPengunggahTest extends TestCase
{
    use RefreshDatabase;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Department::create(['name' => 'QC-WELDING', 'is_active' => true]);
        Department::create(['name' => 'ASSEMBLY', 'is_active' => true]);
        $kat = Category::create(['name' => 'Tangki-PLN', 'is_active' => true]);
        $this->produk = Product::create(['category_id' => $kat->id, 'type' => 'regular', 'name' => 'TANGKI', 'series' => '26Q0001', 'kva' => '50', 'unit' => 'unit', 'is_active' => true]);
    }

    private function akun(string $role, ?string $dept = 'QC-WELDING'): User
    {
        if ($ada = User::where('username', $role . 'fd')->first()) {
            return $ada;
        }
        $u = new User(['name' => 'Uji ' . ucfirst($role), 'username' => $role . 'fd', 'email' => $role . '.fd@uji.test', 'role' => $role, 'department' => $dept, 'password' => Hash::make('rahasia123')]);
        $u->forceFill(['is_active' => true])->save();

        return $u;
    }

    /** Ganti antara sesi web dan token API dalam satu tes. */
    private function sebagai(User $u, string $guard): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $this->actingAs($u, $guard);
    }

    private function aturFitur(bool $aktif): void
    {
        $this->sebagai($this->akun('developer', null), 'web')->post(route('developer.bot-settings.update'), [
            'fitur_departemen' => $aktif ? '1' : '0',
        ])->assertSessionHasNoErrors();
        $this->assertSame($aktif, BotSetting::instance()->fresh()->fitur_departemen);
        BotSetting::lupakan();
    }

    public function test_bawaan_aktif_developer_wajib_memilih_departemen(): void
    {
        $dev = $this->akun('developer', null);
        $this->assertTrue(BotSetting::departemenUntuk($dev));
        $this->assertFalse(BotSetting::departemenUntuk($this->akun('operator')));

        $this->sebagai($dev, 'sanctum');
        $this->assertCount(2, $this->getJson('/api/v1/produksi/opsi')->assertOk()->json('departments'));
        $this->postJson('/api/v1/produksi', ['product_id' => $this->produk->id, 'production_date' => today()->toDateString(), 'total_qty' => 2])
            ->assertStatus(422)->assertJsonValidationErrors(['department']);
    }

    public function test_dimatikan_pilihan_dan_saringan_hilang_data_ikut_akun(): void
    {
        $this->aturFitur(false);
        $dev = User::where('username', 'developerfd')->sole();

        $this->sebagai($dev, 'sanctum');
        $this->assertSame([], $this->getJson('/api/v1/produksi/opsi')->assertOk()->json('departments'));
        $this->sebagai($dev, 'web');
        $props = $this->get(route('production.create'))->assertOk()->viewData('page')['props'];
        $this->assertSame([], $props['departments']);

        $this->sebagai($dev, 'sanctum');
        // Tanpa departemen tetap tersimpan, tercatat dengan departemen akun (developer: kosong).
        $this->postJson('/api/v1/produksi', ['product_id' => $this->produk->id, 'production_date' => today()->toDateString(), 'total_qty' => 2])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertNull(ProductionLog::withoutGlobalScopes()->sole()->department);

        // Riwayat: tanpa pilihan departemen, dan ?department= diabaikan (semua departemen tampil).
        ProductionLog::withoutGlobalScopes()->create(['product_id' => $this->produk->id, 'user_id' => $dev->id, 'operator_name' => 'X', 'production_date' => today()->toDateString(), 'total_qty' => 1, 'department' => 'ASSEMBLY']);
        $this->sebagai($dev, 'web');
        $riwayat = $this->get(route('production.index', ['department' => 'QC-WELDING']))->assertOk()->viewData('page')['props'];
        $this->assertSame([], $riwayat['departments']);
        $this->assertSame(2, array_sum(array_column($riwayat['days'], 'count')));
    }

    public function test_dimatikan_saringan_departemen_hilang_di_aksesoris_pengganti_laporan(): void
    {
        $dev = $this->akun('developer', null);
        $this->sebagai($dev, 'web');
        $this->assertCount(2, $this->get(route('accessories.index'))->assertOk()->viewData('page')['props']['departments']);

        $this->aturFitur(false);
        $this->sebagai($dev, 'web');
        foreach (['accessories.index', 'replacements.index', 'reports.index'] as $rute) {
            $this->assertSame([], $this->get(route($rute))->assertOk()->viewData('page')['props']['departments'], $rute);
        }
    }

    public function test_dinyalakan_lagi_kembali_normal(): void
    {
        $this->aturFitur(false);
        $this->aturFitur(true);
        $this->sebagai(User::where('username', 'developerfd')->sole(), 'sanctum');
        $this->assertCount(2, $this->getJson('/api/v1/produksi/opsi')->assertOk()->json('departments'));
    }

    public function test_kartu_gambar_kerja_memuat_pengunggah_terakhir_dan_waktunya(): void
    {
        $a = $this->akun('admin');
        $b = $this->akun('supervisor');
        $isi = ['judul' => 'Trafo', 'seri' => 'S1', 'kva' => '50', 'tahun' => 2026, 'kategori_seri' => 'pln', 'file_type' => 'pdf'];
        $lama = GambarKerja::create($isi + ['file_path' => 'gambar-kerja/a.pdf', 'uploaded_by' => $a->id, 'urutan' => 1]);
        $lama->forceFill(['created_at' => now()->subDays(3)])->save();
        $baru = GambarKerja::create($isi + ['file_path' => 'gambar-kerja/b.pdf', 'uploaded_by' => $b->id, 'urutan' => 2]);

        $g = $this->actingAs($a)->get(route('gambar-kerja.index'))->assertOk()->viewData('page')['props']['years'][0]['groups'][0];
        $this->assertSame(2, $g['total']);
        $this->assertSame('Uji Supervisor', $g['pengunggah']);
        $this->assertSame($baru->created_at->toIso8601String(), $g['diunggah']);
    }
}
