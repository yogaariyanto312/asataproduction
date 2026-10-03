<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductUrutanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $kat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = new User(['name' => 'Admin', 'username' => 'adm', 'email' => 'adm@uji.test', 'role' => 'admin', 'password' => Hash::make('x12345678')]);
        $this->admin->forceFill(['is_active' => true])->save();
        $this->kat = Category::create(['name' => 'Channel-PLN', 'is_active' => true]);
    }

    private function produk(string $name, ?int $urutan, string $series = 'S1'): Product
    {
        return Product::create(['category_id' => $this->kat->id, 'type' => 'regular', 'name' => $name, 'urutan' => $urutan, 'series' => $series, 'unit' => 'unit', 'tahun' => 2026, 'is_active' => true]);
    }

    private function urutanKartu(): array
    {
        $page = $this->actingAs($this->admin)->get('/products')->assertOk()->viewData('page');

        return collect($page['props']['sections'][0]['products'])->pluck('name')->all();
    }

    public function test_kartu_diurutkan_dari_urutan_lalu_abjad(): void
    {
        $this->produk('Cover', null);
        $this->produk('Tangki', 2);
        $this->produk('Channel-PLN', 1);
        $this->produk('Aksesoris', null);

        $this->assertSame(['Channel-PLN', 'Tangki', 'Aksesoris', 'Cover'], $this->urutanKartu());
    }

    public function test_urutan_disamakan_ke_semua_varian_satu_nama(): void
    {
        $a = $this->produk('Channel-PLN', null, 'S1');
        $this->produk('Channel-PLN', null, 'S2');

        $this->actingAs($this->admin)->put("/products/{$a->id}", [
            'category_id' => $this->kat->id, 'type' => 'regular', 'name' => 'Channel-PLN', 'urutan' => 3,
            'series' => 'S1', 'unit' => 'lembar', 'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([3, 3], Product::where('name', 'Channel-PLN')->pluck('urutan')->all());
        $this->assertSame('lembar', $a->fresh()->unit);
    }

    public function test_varian_baru_tanpa_urutan_ikut_urutan_grupnya(): void
    {
        $this->produk('Channel-PLN', 1, 'S1');

        $this->actingAs($this->admin)->post('/products', [
            'category_id' => $this->kat->id, 'type' => 'regular', 'name' => 'Channel-PLN',
            'series' => 'S9', 'unit' => 'unit', 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame([1, 1], Product::where('name', 'Channel-PLN')->pluck('urutan')->all());
    }

    public function test_warna_kartu_disamakan_ke_semua_varian_dan_tampil_di_kartu(): void
    {
        $a = $this->produk('Channel-PLN', 1, 'S1');
        $this->produk('Channel-PLN', 1, 'S2');

        $this->actingAs($this->admin)->put("/products/{$a->id}", [
            'category_id' => $this->kat->id, 'type' => 'regular', 'name' => 'Channel-PLN', 'urutan' => 1,
            'warna_ikon' => '#22c55e', 'warna_teks' => '#FF8800', 'series' => 'S1', 'unit' => 'unit', 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(['#22c55e', '#22c55e'], Product::where('name', 'Channel-PLN')->pluck('warna_ikon')->all());
        $kartu = $this->get('/products')->viewData('page')['props']['sections'][0]['products'][0];
        $this->assertSame(['#22c55e', '#FF8800'], [$kartu['warnaIkon'], $kartu['warnaTeks']]);

        // Varian baru dari tombol (+) ikut warna kartunya.
        $this->post('/products', ['category_id' => $this->kat->id, 'type' => 'regular', 'name' => 'Channel-PLN', 'series' => 'S3', 'unit' => 'unit'])
            ->assertSessionHasNoErrors();
        $this->assertSame('#22c55e', Product::where('series', 'S3')->value('warna_ikon'));
    }

    public function test_warna_harus_kode_hex(): void
    {
        $this->actingAs($this->admin)->post('/products', [
            'category_id' => $this->kat->id, 'type' => 'regular', 'name' => 'X', 'unit' => 'unit', 'warna_ikon' => 'red;}',
        ])->assertSessionHasErrors(['warna_ikon' => 'Warna ikon harus kode hex, mis. #2563eb.']);
    }

    public function test_urutan_harus_angka_positif(): void
    {
        $this->actingAs($this->admin)->post('/products', [
            'category_id' => $this->kat->id, 'type' => 'regular', 'name' => 'X', 'urutan' => 0, 'unit' => 'unit',
        ])->assertSessionHasErrors(['urutan' => 'Urutan paling kecil 1.']);
    }

    public function test_form_tambah_varian_terisi_dari_tombol_plus_dan_membawa_pilihan_satuan(): void
    {
        $this->produk('Channel-PLN', 4)->update(['unit' => 'batang']);

        $props = $this->actingAs($this->admin)
            ->get('/products/create?name=Channel-PLN&category_id=' . $this->kat->id . '&type=regular&urutan=4')
            ->assertOk()->viewData('page')['props'];

        $this->assertSame('Channel-PLN', $props['prefill']['name']);
        $this->assertSame('4', (string) $props['prefill']['urutan']);
        $this->assertContains('lembar', $props['units']);
        $this->assertContains('batang', $props['units']);
    }
}
