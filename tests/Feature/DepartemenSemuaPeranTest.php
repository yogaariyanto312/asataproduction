<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DepartemenSemuaPeranTest extends TestCase
{
    use RefreshDatabase;

    public static function peran(): array
    {
        return [['admin', 'admins'], ['supervisor', 'supervisors'], ['mandor', 'mandors'], ['visitor', 'visitors']];
    }

    #[DataProvider('peran')]
    public function test_edit_pengguna_bisa_memilih_departemen(string $role, string $rute): void
    {
        $dev = new User(['name' => 'Dev', 'username' => 'dev', 'email' => 'dev@uji.test', 'role' => 'developer', 'password' => Hash::make('x12345678')]);
        $dev->forceFill(['is_active' => true])->save();
        Department::create(['name' => 'QC-WELDING', 'is_active' => true]);
        $u = new User(['name' => 'Target', 'username' => 'target', 'email' => 'target@uji.test', 'role' => $role, 'password' => Hash::make('x12345678')]);
        $u->forceFill(['is_active' => true])->save();

        $props = $this->actingAs($dev)->get(route("$rute.edit", $u->id))->assertOk()->viewData('page')['props'];
        $this->assertTrue($props['resource']['withDepartment']);
        $this->assertSame('QC-WELDING', $props['departments'][0]['value']);

        $this->put(route("$rute.update", $u->id), ['name' => 'Target', 'email' => 'target@uji.test', 'username' => 'target', 'department' => 'QC-WELDING'])
            ->assertSessionHasNoErrors();
        $this->assertSame('QC-WELDING', $u->fresh()->department);

        $this->put(route("$rute.update", $u->id), ['name' => 'Target', 'email' => 'target@uji.test', 'username' => 'target', 'department' => 'Ngawur'])
            ->assertSessionHasErrors('department');
    }
}
