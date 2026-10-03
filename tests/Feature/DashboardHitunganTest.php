<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\ProductionTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Angka dashboard: progres target lintas departemen, total hari ini memakai
 * rentang tanggal, dan log aktivitas hanya untuk yang berizin.
 */
class DashboardHitunganTest extends TestCase
{
    use RefreshDatabase;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        Department::create(['name' => 'QC-WELDING', 'is_active' => true]);
        Department::create(['name' => 'ASSEMBLY', 'is_active' => true]);

        $cat = Category::create(['name' => 'Cover-PLN', 'is_active' => true, 'has_manual_serial' => false]);
        $this->produk = Product::create([
            'category_id' => $cat->id, 'type' => 'regular', 'name' => 'COVER',
            'series' => 'CV01', 'kva' => '50', 'is_active' => true,
        ]);
    }

    private function user(string $role, ?string $dept = null): User
    {
        $u = new User([
            'name' => "U {$role} {$dept}", 'username' => strtolower("u_{$role}_" . ($dept ?? 'x')),
            'email' => strtolower("{$role}" . ($dept ?? 'x') . '@uji.test'), 'role' => $role,
            'department' => $dept, 'password' => Hash::make('rahasia123'),
        ]);
        $u->forceFill(['is_active' => true])->save();

        return $u;
    }

    private function log(string $dept, float $qty, ?string $tanggal = null, int $reject = 0): void
    {
        $u = User::first();
        ProductionLog::withoutGlobalScopes()->create([
            'product_id' => $this->produk->id, 'user_id' => $u->id, 'department' => $dept,
            'production_date' => $tanggal ?? today()->toDateString(),
            'up_qty' => 0, 'bt_qty' => 0,            'total_qty' => $qty, 'reject_qty' => $reject,
        ]);
    }

    public function test_progres_target_dihitung_lintas_departemen_untuk_semua_role(): void
    {
        $dev = $this->user('developer');
        $op  = $this->user('operator', 'QC-WELDING');

        // Sebelum target dibuat: 10 unit (baseline lintas departemen)
        $this->log('QC-WELDING', 10, today()->subDays(3)->toDateString());
        ProductionTarget::create([
            'product_id' => $this->produk->id, 'target_date' => today()->toDateString(),
            'target_qty' => 100, 'baseline_qty' => 10, 'created_by' => $dev->id,
        ]);
        // Sesudah target: 30 unit dari QC-WELDING + 20 dari ASSEMBLY = 50
        $this->log('QC-WELDING', 30);
        $this->log('ASSEMBLY', 20);

        foreach ([$dev, $op] as $u) {
            $this->flushSession();
            $this->actingAs($u)->get('/dashboard')->assertOk()
                ->assertInertia(fn (AssertableInertia $p) => $p
                    ->where('target.actual', 50)
                    ->where('target.total', 100)
                    ->where('target.pct', 50)
                    ->etc());

            $this->getJson('/api/dashboard-live')->assertOk()
                ->assertJsonPath('total_actual', 50)
                ->assertJsonPath('target_pct', 50);
        }
    }

    public function test_total_hari_ini_dan_bulan_ini(): void
    {
        $admin = $this->user('admin', 'QC-WELDING');

        $this->log('QC-WELDING', 5, null, 1);
        $this->log('QC-WELDING', 2.5);
        $this->log('QC-WELDING', 7, today()->subDay()->toDateString() === today()->startOfMonth()->subDay()->toDateString()
            ? today()->toDateString() : today()->subDay()->toDateString());
        $this->log('QC-WELDING', 100, today()->subMonths(2)->toDateString());   // bulan lain — diabaikan
        $this->log('ASSEMBLY', 50);                                           // departemen lain — di-scope

        $res = $this->actingAs($admin)->getJson('/api/dashboard-live')->assertOk();

        $kemarinBulanIni = today()->day > 1;
        $this->assertEquals($kemarinBulanIni ? 7.5 : 14.5, $res->json('today_total'));
        $this->assertSame($kemarinBulanIni ? 2 : 3, $res->json('today_entries'));
        $this->assertEquals(14.5, $res->json('monthly_total'));
        $this->assertSame(1, $res->json('today_reject'));
    }

    public function test_log_aktivitas_hanya_untuk_yang_berizin(): void
    {
        $op    = $this->user('operator', 'QC-WELDING');
        $admin = $this->user('admin', 'QC-WELDING');

        // Log aktivitas kini punya endpoint sendiri (seperti referensi), tidak
        // lagi menumpang di polling statistik.
        $this->actingAs($op)->getJson('/api/dashboard-live')->assertJsonMissingPath('activities');
        $this->actingAs($op)->getJson('/api/dashboard-aktivitas')->assertForbidden();

        $this->flushSession();
        $this->assertIsArray($this->actingAs($admin)->getJson('/api/dashboard-aktivitas')->json('items'));
    }

    public function test_bulan_kalender_di_luar_jangkauan_ditolak(): void
    {
        $u = $this->user('admin', 'QC-WELDING');

        $this->actingAs($u)->getJson('/api/calendar-events?year=9999&month=1')->assertStatus(422);
        $this->getJson('/api/calendar-events?year=' . now()->year . '&month=13')->assertStatus(422);
        $this->getJson('/api/calendar-events?year=' . now()->year . '&month=2')->assertOk();
    }
}
