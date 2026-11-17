<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Tahap 1 — memastikan fondasi Inertia/React dan panel Filament terpasang
 * benar dan tetap tunduk pada sistem role aplikasi.
 */
class FoundationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, bool $active = true): User
    {
        return User::create([
            'name'      => ucfirst($role) . ' Uji',
            'username'  => $role . 'fondasi' . ($active ? '' : 'off'),
            'email'     => $role . ($active ? '' : 'off') . '@fondasi.test',
            'role'      => $role,
            'is_active' => $active,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    // ── Inertia ──────────────────────────────────────────────────────────

    public function test_halaman_inertia_render_dengan_shared_props(): void
    {
        $this->actingAs($this->user('developer'))
            ->get('/developer/system-check')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SystemCheck')
                ->where('auth.user.role', 'developer')
                ->has('stack.Laravel')
                ->has('stack.Filament')
                ->has('menu')
            );
    }

    public function test_halaman_inertia_memakai_root_view_terpisah(): void
    {
        $html = $this->actingAs($this->user('developer'))
            ->get('/developer/system-check')
            ->getContent();

        // Root view Inertia, bukan layouts/app.blade.php milik halaman Blade lama.
        $this->assertStringContainsString('id="app"', $html);
        $this->assertStringContainsString('data-page', $html);
        // Ziggy (@routes) sengaja TIDAK disuntikkan: tidak ada kode React yang
        // memakai route() (semua URL datang dari server lewat props), dan daftar
        // semua nama route di setiap halaman hanya menambah byte + membuka peta
        // route aplikasi ke siapa pun yang membuka Inspect.
        $this->assertStringNotContainsString('Ziggy', $html);
    }

    public function test_halaman_aplikasi_sudah_dirender_inertia(): void
    {
        // Migrasi sudah tuntas: halaman operasional pun kini React, bukan Blade.
        // (Tes ini dulunya memastikan sebaliknya, saat migrasi masih bertahap.)
        $html = $this->actingAs($this->user('operator'))
            ->get('/production/create')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-page', $html);
    }

    // ── Filament ─────────────────────────────────────────────────────────

    public function test_admin_bisa_masuk_panel_filament(): void
    {
        $this->actingAs($this->user('admin'))->get('/admin')->assertOk();
    }

    public function test_developer_bisa_masuk_panel_filament(): void
    {
        $this->actingAs($this->user('developer'))->get('/admin')->assertOk();
    }

    /**
     * Panel kini menjadi rumah Dashboard, yang menurut config/menus.php boleh
     * dilihat semua peran. Karena itu gerbangnya dibuka untuk semua akun AKTIF
     * — pembatasan isi ada di tiap widget, bukan di pintu panel.
     */
    public function test_operator_bisa_masuk_panel_filament(): void
    {
        $this->actingAs($this->user('operator'))->get('/admin')->assertOk();
    }

    public function test_supervisor_bisa_masuk_panel_filament(): void
    {
        $this->actingAs($this->user('supervisor'))->get('/admin')->assertOk();
    }

    public function test_akun_nonaktif_ditolak_panel_filament(): void
    {
        // Satu-satunya syarat yang tersisa: akun harus aktif.
        $this->actingAs($this->user('admin', active: false))->get('/admin')->assertForbidden();
        $this->actingAs($this->user('operator', active: false))->get('/admin')->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_login_aplikasi_bukan_login_filament(): void
    {
        // Panel sengaja tidak memanggil ->login(); satu-satunya pintu login
        // adalah /login milik aplikasi (punya rate limiter & cek is_active).
        $this->get('/admin')->assertRedirect(route('login'));
        $this->assertFalse(app('router')->has('filament.admin.auth.login'));
    }
}
