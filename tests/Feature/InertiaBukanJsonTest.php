<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Request Inertia membawa header X-Requested-With, jadi controller yang memakai
 * $request->ajax() dulu membalasnya dengan JSON polos — layar lalu menampilkan
 * kotak "All Inertia requests must receive a valid Inertia response" (laporan
 * Yoga 2026-10-03, saat menghapus data di Riwayat Produksi).
 */
class InertiaBukanJsonTest extends TestCase
{
    use RefreshDatabase;

    private function headerInertia(): array
    {
        $versi = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return array_filter([
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => $versi,
            'X-Requested-With'  => 'XMLHttpRequest',
            'Accept'            => 'text/html, application/xhtml+xml',
        ]);
    }

    private function log(User $dev): ProductionLog
    {
        $kat = Category::create(['name' => 'Tangki Uji', 'is_active' => true]);
        $p = Product::create(['category_id' => $kat->id, 'type' => 'regular', 'name' => 'TANGKI', 'series' => '26Q0001', 'kva' => '50', 'is_active' => true]);

        return ProductionLog::create([
            'product_id' => $p->id, 'user_id' => $dev->id, 'operator_name' => 'Uji',
            'production_date' => now()->toDateString(), 'total_qty' => 2, 'up_qty' => 0, 'bt_qty' => 0, 'reject_qty' => 0,
        ]);
    }

    public function test_hapus_produksi_lewat_inertia_dibalas_redirect_bukan_json(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $log = $this->log($dev);

        $asal = route('production.index', ['search' => 'TANGKI', 'page' => 2]);

        $res = $this->actingAs($dev)
            ->from($asal)
            ->withHeaders($this->headerInertia())
            ->delete(route('production.destroy', $log));

        $res->assertRedirect($asal);   // filter & halaman yang sedang dibuka tetap
        $this->assertStringNotContainsString('"success":true', (string) $res->getContent());
        $this->assertNull(ProductionLog::find($log->id));
    }

    public function test_hapus_dari_halaman_detail_kembali_ke_daftar(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $log = $this->log($dev);

        $this->actingAs($dev)
            ->from(route('production.show', $log))
            ->withHeaders($this->headerInertia())
            ->delete(route('production.destroy', $log))
            ->assertRedirect(route('production.index'));
    }

    public function test_ajax_lama_tanpa_inertia_tetap_dapat_json(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $log = $this->log($dev);

        $this->actingAs($dev)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->delete(route('production.destroy', $log))
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}
