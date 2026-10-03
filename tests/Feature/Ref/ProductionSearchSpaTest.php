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
 * Pencarian Riwayat Produksi tanpa reload penuh — padanan asata untuk
 * ProductionSearchSpaTest referensi. Referensi memakai AJAX di app.js yang
 * menyalin #prod-list-area; asata memakai Inertia partial reload (router.get
 * dengan `only`), jadi yang dijaga perilakunya, bukan mekanismenya.
 */
class ProductionSearchSpaTest extends TestCase
{
    use RefreshDatabase;

    private function sumber(): string
    {
        return file_get_contents(resource_path('js/Pages/Production/Index.jsx'));
    }

    private function siapkan(): User
    {
        $admin = new User(['name' => 'Admin', 'username' => 'adm', 'email' => 'adm@uji.test', 'role' => 'admin', 'password' => Hash::make('x12345678')]);
        $admin->forceFill(['is_active' => true])->save();

        $cat = Category::create(['name' => 'Cover PLN', 'is_active' => true, 'has_manual_serial' => false]);
        foreach (['26B0091000', '26C0251001'] as $seri) {
            $p = Product::create(['category_id' => $cat->id, 'type' => 'regular', 'name' => 'COVER', 'series' => $seri, 'kva' => '50', 'is_active' => true]);
            ProductionLog::create(['product_id' => $p->id, 'user_id' => $admin->id, 'production_date' => today()->toDateString(),
                'up_qty' => 0, 'bt_qty' => 0, 'total_qty' => 1, 'reject_qty' => 0]);
        }

        return $admin;
    }

    public function test_permintaan_parsial_hanya_mengirim_daftar_terfilter(): void
    {
        $admin = $this->siapkan();
        $versi = $this->actingAs($admin)->get('/production')->viewData('page')['version'];

        $res = $this->withHeaders([
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => $versi,
            'X-Inertia-Partial-Component' => 'Production/Index',
            'X-Inertia-Partial-Data'      => 'days,pagination,filters',
        ])->get('/production?search=26B0091000')->assertOk();

        $props = $res->json('props');
        $this->assertArrayHasKey('days', $props);
        $this->assertArrayNotHasKey('summary', $props, 'ringkasan tidak perlu dikirim ulang setiap ketikan');

        $json = json_encode($props['days']);
        $this->assertStringContainsString('26B0091000', $json);
        $this->assertStringNotContainsString('26C0251001', $json);
    }

    public function test_pencarian_berjalan_sambil_mengetik_dengan_jeda(): void
    {
        $src = $this->sumber();
        $this->assertMatchesRegularExpression('/setTimeout\(\(\) => kirim\(f\), \d+\)/', $src, 'ada jeda sebelum mengirim');
        $this->assertStringContainsString("only: ['days', 'pagination', 'filters']", $src, 'hanya daftar yang dimuat ulang');
        $this->assertStringContainsString('preserveState: true', $src);
    }

    public function test_enter_dan_submit_tidak_memicu_reload_penuh(): void
    {
        $src = $this->sumber();
        $awal = strpos($src, 'function applyFilter(e)');
        $this->assertNotFalse($awal);
        $this->assertStringContainsString('e.preventDefault()', substr($src, $awal, 200));
    }
}
