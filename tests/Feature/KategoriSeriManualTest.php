<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KategoriSeriManualTest extends TestCase
{
    use RefreshDatabase;

    private function dev(): User
    {
        $u = new User(['name' => 'Dev', 'username' => 'dev', 'email' => 'dev@uji.test', 'role' => 'developer', 'password' => Hash::make('x12345678')]);
        $u->forceFill(['is_active' => true])->save();

        return $u;
    }

    public function test_form_kategori_bisa_menyalakan_dan_mematikan_seri_manual(): void
    {
        $dev = $this->dev();

        $this->actingAs($dev)->post('/categories', ['name' => 'Channel-PLN', 'has_manual_serial' => true, 'is_active' => true])
            ->assertSessionHasNoErrors();
        $kat = Category::where('name', 'Channel-PLN')->first();
        $this->assertTrue($kat->has_manual_serial);

        $this->put("/categories/{$kat->id}", ['name' => 'Channel-PLN', 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertFalse($kat->fresh()->has_manual_serial);
    }

    public function test_dropdown_input_produksi_tidak_lagi_menampilkan_tanda_strip(): void
    {
        $dev = $this->dev();
        $manual = Category::create(['name' => 'Channel-PLN', 'is_active' => true, 'has_manual_serial' => true]);
        $biasa  = Category::create(['name' => 'Aksesoris', 'is_active' => true]);
        Product::create(['category_id' => $manual->id, 'type' => 'channel', 'name' => 'Channel-PLN', 'unit' => 'unit', 'is_active' => true]);
        Product::create(['category_id' => $biasa->id, 'type' => 'regular', 'name' => 'Baut', 'unit' => 'pcs', 'is_active' => true]);

        $labels = collect($this->actingAs($dev)->get('/production/create')->assertOk()->viewData('page')['props']['products'])
            ->pluck('label', 'group');

        $this->assertSame('Seri & KVA Manual CH → PLN', $labels['Channel-PLN']);
        $this->assertSame('Tanpa seri', $labels['Baut']);
    }
}
