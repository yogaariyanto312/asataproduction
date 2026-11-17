<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Dashboard sudah dipindah ke React (Inertia). Tes ini menjaga bentuk props
 * yang dipakai komponen, hak akses widget admin, dan hal-hal yang mudah rusak
 * saat aplikasi dilayani dari subpath (URL harus datang dari server).
 */
class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

    }

    private function user(string $role): User
    {
        return User::create([
            'name'      => ucfirst($role) . ' Dash',
            'username'  => $role . 'dash',
            'email'     => $role . '@dash.test',
            'role'      => $role,
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    public function test_dashboard_dirender_react(): void
    {
        $this->actingAs($this->user('admin'))
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('stats.today_total')
                ->has('stats.today_entries')
                ->has('stats.monthly_total')
                ->has('stats.total_products')
                ->has('chartData', 7)
                ->has('target.total')
                ->has('target.products')
                ->has('reject.today')
                ->has('reject.pct')
                ->has('topOperators')
                ->has('topOperatorsMonthly')
                ->has('recentLogs')
                ->has('activities.items')
                ->has('notes')
                ->has('calendar.label')
                ->has('calendar.days')
                ->has('urls.live')
            );
    }

    public function test_props_menu_dan_route_aktif_ikut_dikirim(): void
    {
        $this->actingAs($this->user('admin'))
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('routeName', 'dashboard')
                ->has('menu')
                ->has('logoutUrl')
                ->where('auth.user.role', 'admin')
            );
    }

    public function test_url_aksi_datang_dari_server_bukan_path_root(): void
    {
        // Aplikasi dilayani dari subpath, jadi URL literal seperti '/logout' di
        // sisi React akan meleset ke root domain dan menghasilkan 404.
        $this->actingAs($this->user('admin'))
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('logoutUrl', route('logout'))
                ->where('urls.live', route('api.dashboard.live'))
                ->where('urls.calendar', route('api.dashboard.calendar'))
                ->where('urls.aktivitas', route('api.dashboard.aktivitas'))
                ->where('urls.eventBase', url('/calendar-events'))
            );
    }

    public function test_semua_menu_sidebar_sudah_react(): void
    {
        // Seluruh menu kini dirender Inertia, jadi semuanya harus ditandai
        // spa=true agar sidebar memakai <Link> (navigasi tanpa reload penuh).
        // Menu yang lolos dari daftar $inertiaRoutes akan tertangkap di sini.
        $this->actingAs($this->user('developer'))
            ->get('/dashboard')
            ->assertInertia(function (AssertableInertia $page) {
                $menu = collect($page->toArray()['props']['menu']);

                $this->assertNotEmpty($menu, 'Menu sidebar tidak boleh kosong.');

                $blade = $menu->reject(fn ($m) => $m['spa'])->pluck('key')->all();

                $this->assertSame(
                    [],
                    $blade,
                    'Menu berikut belum ditandai spa: ' . implode(', ', $blade)
                );
            });
    }

    public function test_operator_tetap_bisa_membuka_dashboard(): void
    {
        $this->actingAs($this->user('operator'))
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard'));
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_endpoint_live_stats_mengembalikan_json(): void
    {
        $this->actingAs($this->user('admin'))
            ->getJson('/api/dashboard-live')
            ->assertOk()
            ->assertJsonStructure([
                'today_total',
                'today_entries',
                'monthly_total',
                'today_reject',
                'reject_pct',
                'target_pct',
                'total_target',
                'total_actual',
                'top_operators',
                'updated_at',
            ]);
    }
}
