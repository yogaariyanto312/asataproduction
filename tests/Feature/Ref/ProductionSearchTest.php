<?php

namespace Tests\Feature\Ref;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Searchbar di Riwayat Produksi.
 *
 * Pencarian dipakai bersama filter lain (produk, tahun, rentang tanggal) dan
 * dipanggil dua jalur: submit form biasa dan permintaan AJAX dari kolom search
 * real-time. Keduanya harus menghasilkan daftar yang sama.
 */
class ProductionSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name'      => 'Admin Cari',
            'username'  => 'admincari',
            'email'     => 'admincari@uji.test',
            'role'      => 'admin',
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    private function product(string $name, string $series): Product
    {
        $category = Category::firstOrCreate(
            ['name' => 'Kategori-Cari'],
            ['is_active' => true, 'has_manual_serial' => false],
        );

        return Product::create([
            'category_id' => $category->id,
            'type'        => 'regular',
            'name'        => $name,
            'series'      => $series,
            'kva'         => '100',
            'is_active'   => true,
        ]);
    }

    private function log(Product $product, string $date, ?string $notes = null): ProductionLog
    {
        return ProductionLog::create([
            'product_id'      => $product->id,
            'user_id'         => User::first()->id,
            'operator_name'   => 'Operator',
            'production_date' => $date,
            'up_qty'      => 1,
            'bt_qty'      => 0,
            'total_qty'       => 1,
            'reject_qty'      => 0,
            'notes'           => $notes,
        ]);
    }

    public function test_cari_berdasarkan_seri_produk(): void
    {
        $admin = $this->admin();
        $cocok = $this->product('COVER A', '26B0091000');
        $lain  = $this->product('COVER B', '26C0251001');

        $this->log($cocok, today()->toDateString());
        $this->log($lain, today()->toDateString());

        $html = $this->actingAs($admin)->get('/production?search=26B0091000')->assertOk()->getContent();

        $this->assertStringContainsString('26B0091000', $html);
        $this->assertStringNotContainsString('26C0251001', $html);
    }

    public function test_cari_seri_sebagian_juga_ketemu(): void
    {
        $admin = $this->admin();
        $cocok = $this->product('COVER A', '26B0091000');
        $this->log($cocok, today()->toDateString());

        $html = $this->actingAs($admin)->get('/production?search=0091')->assertOk()->getContent();

        $this->assertStringContainsString('26B0091000', $html);
    }

    public function test_pencarian_tidak_menembus_filter_lain(): void
    {
        $this->admin();
        $produk = $this->product('COVER A', '26B0091000');

        // Catatan memuat kata kunci, tapi tahunnya di luar filter.
        $this->log($produk, '2024-05-10', 'catatan khusus');
        $this->log($produk, today()->toDateString(), 'biasa');

        $hasil = ProductionLog::query()
            ->whereYear('production_date', today()->year)
            ->search('catatan khusus')
            ->get();

        $this->assertCount(0, $hasil,
            'Baris 2024 bocor lewat orWhere: pencarian membatalkan filter tahun.');
    }

    public function test_pencarian_bersama_filter_produk_tetap_terkurung(): void
    {
        $this->admin();
        $a = $this->product('COVER A', '26B0091000');
        $b = $this->product('COVER B', '26C0251001');

        $this->log($a, today()->toDateString(), 'dipakai ulang');
        $this->log($b, today()->toDateString(), 'dipakai ulang');

        $hasil = ProductionLog::query()
            ->whereHas('product', fn($q) => $q->where('name', 'COVER A'))
            ->search('dipakai ulang')
            ->get();

        $this->assertCount(1, $hasil, 'Filter produk harus tetap berlaku saat mencari isi catatan.');
        $this->assertSame($a->id, $hasil->first()->product_id);
    }

}
