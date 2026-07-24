<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'name'      => ucfirst($role) . ' Uji',
            'username'  => $role . 'uji',
            'email'     => $role . '@uji.test',
            'role'      => $role,
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    public function test_supervisor_tidak_bisa_akses_input_produksi(): void
    {
        $this->actingAs($this->user('supervisor'))
            ->get('/production/create')
            ->assertForbidden();
    }

    public function test_supervisor_tidak_bisa_akses_master_produk(): void
    {
        $this->actingAs($this->user('supervisor'))
            ->get('/products')
            ->assertForbidden();
    }

    public function test_operator_bisa_akses_input_produksi(): void
    {
        $this->actingAs($this->user('operator'))
            ->get('/production/create')
            ->assertOk();
    }

    public function test_admin_bisa_akses_master_produk(): void
    {
        $this->actingAs($this->user('admin'))
            ->get('/products')
            ->assertOk();
    }

    public function test_supervisor_tetap_bisa_lihat_riwayat_produksi(): void
    {
        $this->actingAs($this->user('supervisor'))
            ->get('/production')
            ->assertOk();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
