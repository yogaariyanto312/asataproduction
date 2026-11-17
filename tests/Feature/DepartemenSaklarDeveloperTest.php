<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Saklar departemen developer: filter tampilan (sesi) dan ganti departemen akun. */
class DepartemenSaklarDeveloperTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?string $dept = null): User
    {
        $u = new User(['name' => ucfirst($role), 'username' => $role . rand(1, 9999), 'email' => $role . rand(1, 9999) . '@uji.test', 'role' => $role, 'password' => Hash::make('rahasia123')]);
        $u->forceFill(['is_active' => true, 'department' => $dept])->save();

        return $u;
    }

    private function log(Product $p, User $u, string $dept, int $qty): void
    {
        ProductionLog::withoutGlobalScopes()->create(['product_id' => $p->id, 'user_id' => $u->id, 'department' => $dept, 'operator_name' => $u->name, 'production_date' => today()->toDateString(), 'up_qty' => 0, 'bt_qty' => 0, 'total_qty' => $qty, 'reject_qty' => 0]);
    }

    public function test_filter_tampilan_developer_menyaring_data_dan_bisa_dikembalikan(): void
    {
        Department::create(['name' => 'QC-WELDING', 'is_active' => true]);
        Department::create(['name' => 'Management', 'is_active' => true]);
        $dev = $this->user('developer', 'Management');
        $kat = Category::create(['name' => 'K', 'is_active' => true, 'has_manual_serial' => false]);
        $p = Product::create(['category_id' => $kat->id, 'type' => 'regular', 'name' => 'TANGKI', 'series' => '26T1', 'kva' => '50', 'is_active' => true]);
        $this->log($p, $dev, 'Management', 5);
        $this->log($p, $dev, 'QC-WELDING', 7);

        $this->actingAs($dev);
        $this->assertSame(12, (int) ProductionLog::sum('total_qty'), 'bawaan: semua departemen');

        $this->post(route('akun.departemen'), ['lihat' => 'QC-WELDING'])->assertSessionHasNoErrors();
        $this->assertSame(7, (int) ProductionLog::sum('total_qty'));

        $this->post(route('akun.departemen'), ['lihat' => ''])->assertSessionHasNoErrors();
        $this->assertSame(12, (int) ProductionLog::sum('total_qty'));
    }

    public function test_developer_bisa_mengganti_departemen_akun_sendiri_dan_nama_asing_ditolak(): void
    {
        Department::create(['name' => 'QC-WELDING', 'is_active' => true]);
        $dev = $this->user('developer', 'Management');

        $this->actingAs($dev)->post(route('akun.departemen'), ['akun' => 'QC-WELDING'])->assertSessionHasNoErrors();
        $this->assertSame('QC-WELDING', $dev->fresh()->department);

        $this->post(route('akun.departemen'), ['akun' => 'Ngarang'])->assertSessionHasErrors('akun');
        $this->assertSame('QC-WELDING', $dev->fresh()->department);
    }

    public function test_role_lain_tidak_bisa_memakai_saklar(): void
    {
        foreach (['admin', 'operator', 'visitor'] as $role) {
            $this->flushSession();
            $this->actingAs($this->user($role, 'QC-WELDING'))->post(route('akun.departemen'), ['lihat' => 'Management'])->assertForbidden();
        }
    }
}
