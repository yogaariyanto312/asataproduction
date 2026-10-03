<?php

namespace Tests\Feature\Ref;

use App\Models\RoleMenuPermission;
use App\Models\User;
use App\Support\MenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Audit hak akses menu: memastikan setiap route yang mengubah data terpetakan ke
 * permission key, dan grant/revoke lewat "Hak Akses Menu" benar-benar berefek.
 */
class MenuPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MenuAccess::flush(); // cache statis bertahan antar test dalam satu proses
    }

    private function user(string $role): User
    {
        return User::create([
            'name'       => ucfirst($role) . ' Uji',
            'username'   => $role . 'uji',
            'email'      => $role . '@uji.test',
            'role'       => $role,
            'is_active'  => true,
            'password'   => Hash::make('rahasia123'),
        ]);
    }

    private function grant(string $role, string $key, bool $allowed): void
    {
        RoleMenuPermission::updateOrCreate(
            ['role' => $role, 'menu_key' => $key],
            ['allowed' => $allowed],
        );
        MenuAccess::flush();
    }

    /** Semua route yang menulis data (POST/PUT/PATCH/DELETE) harus punya gate. */
    public function test_semua_route_penulis_data_punya_gate(): void
    {
        $unguarded = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (!$name) continue;

            $writes = array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']);
            if (!$writes) continue;

            $mw = implode(',', $route->gatherMiddleware());
            if (!str_contains($mw, 'auth')) continue;      // login/logout/webhook
            if (str_contains($mw, 'role:')) continue;      // sudah dikunci role

            if (MenuAccess::permissionKeyForRoute($name) === null) {
                $unguarded[] = $name;
            }
        }

        // Route personal (profil, agenda & chat milik sendiri) memang sengaja terbuka
        // untuk semua user yang login — sisanya wajib terpetakan ke permission key.
        $allowlist = [
            'logout',
            'profile.update', 'profile.avatar', 'profile.logout-others',
            'profile.about-avatar', 'profile.about-info',
            // Penautan Telegram: tiap orang hanya menautkan/memutus akunnya
            // sendiri (dari $request->user()), tidak bisa menyentuh akun lain.
            'profile.telegram.kode', 'profile.telegram.putus',
            'calendar.events.store', 'calendar.events.destroy',
        ];

        $this->assertSame([], array_values(array_diff($unguarded, $allowlist)),
            'Route berikut bisa mengubah data tanpa gate hak akses: ' . implode(', ', $unguarded));
    }

    /** Tiap pola match di config/menus.php harus benar-benar mengenai route. */
    public function test_semua_pola_match_mengenai_route_nyata(): void
    {
        $names = collect(Route::getRoutes())->map->getName()->filter()->all();
        $dead  = [];

        foreach (MenuAccess::items() as $item) {
            $patterns = [[$item['key'], $item['match'] ?? []]];
            foreach ($item['actions'] ?? [] as $act) {
                $patterns[] = [$act['key'], $act['match'] ?? []];
            }
            foreach ($patterns as [$key, $list]) {
                foreach ($list as $pattern) {
                    $hit = collect($names)->contains(fn($n) => Str::is($pattern, $n));
                    if (!$hit) $dead[] = "{$key}:{$pattern}";
                }
            }
        }

        $this->assertSame([], $dead, 'Pola match tidak mengenai route apapun: ' . implode(', ', $dead));
    }

    /**
     * Permission key yang BOLEH tetap dikunci middleware role:, berikut alasannya.
     *
     * Daftar ini sengaja pendek dan harus tetap pendek. Menambahkannya di sini
     * berarti saklar di UI Hak Akses tidak sepenuhnya berkuasa atas route itu,
     * jadi alasannya harus lebih kuat daripada ketidaknyamanan itu.
     */
    private const KEKECUALIAN_ROLE_MIDDLEWARE = [
        // role: di sini menjaga dimensi yang TIDAK diwakili matriks: siapa yang
        // boleh dikelola. Admin hanya boleh mengelola operator & visitor. Tanpa
        // role:, memberi Admin izin "Tambah Pengguna" berarti Admin bisa
        // membuat akun developer — eskalasi hak akses.
        'manajemen.create',
        'manajemen.edit',
        'manajemen.delete',

        // Menu terkunci (locked) di config: ditampilkan di matriks supaya
        // daftarnya lengkap, tapi memang tidak bisa diberikan ke role lain.
        'settings',
        'permissions',
    ];

    /** Menu yang tampil di UI Hak Akses harus benar-benar bisa di-grant (tidak dikunci role:). */
    public function test_menu_manageable_tidak_dikunci_middleware_role(): void
    {
        $locked = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (!$name) continue;

            $key = MenuAccess::permissionKeyForRoute($name);
            if ($key === null) continue;

            $manageable = collect(MenuAccess::items())->contains(function ($item) use ($key) {
                if (!($item['manageable'] ?? false)) return false;
                if ($item['key'] === $key) return true;
                return collect($item['actions'] ?? [])->contains(fn($a) => $a['key'] === $key);
            });
            if (!$manageable) continue;

            if (in_array($key, self::KEKECUALIAN_ROLE_MIDDLEWARE, true)) continue;

            if (str_contains(implode(',', $route->gatherMiddleware()), 'role:')) {
                $locked[] = "{$name} ({$key})";
            }
        }

        $this->assertSame([], $locked,
            'Menu bisa diatur di UI tapi route-nya dikunci middleware role: ' . implode(', ', $locked));
    }

    /**
     * Penjaga atas daftar kekecualian itu sendiri: tiap key di dalamnya harus
     * memang masih dikunci role:. Kalau middleware-nya dilepas, key-nya harus
     * dikeluarkan dari daftar — bukan dibiarkan menumpuk tanpa alasan.
     */
    public function test_daftar_kekecualian_role_middleware_tidak_basi(): void
    {
        $masihDikunci = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (!$name) continue;

            $key = MenuAccess::permissionKeyForRoute($name);
            if ($key === null) continue;

            if (str_contains(implode(',', $route->gatherMiddleware()), 'role:')) {
                $masihDikunci[$key] = true;
            }
        }

        foreach (self::KEKECUALIAN_ROLE_MIDDLEWARE as $key) {
            $this->assertArrayHasKey($key, $masihDikunci,
                "{$key} sudah tidak dikunci role: — keluarkan dari KEKECUALIAN_ROLE_MIDDLEWARE");
        }

        $this->assertLessThanOrEqual(6, count(self::KEKECUALIAN_ROLE_MIDDLEWARE),
            'Kekecualian bertambah banyak — tiap tambahan membuat UI Hak Akses makin tidak berkuasa');
    }

    public function test_visitor_tidak_bisa_membuat_catatan(): void
    {
        $this->actingAs($this->user('visitor'))
            ->post('/notes', ['content' => 'coba tembus'])
            ->assertForbidden();
    }

    public function test_operator_bisa_membuat_catatan(): void
    {
        $this->actingAs($this->user('operator'))
            ->post('/notes', ['content' => 'catatan sah'])
            ->assertRedirect();
    }

    public function test_revoke_barang_pengganti_memblokir_toggle_selesai(): void
    {
        $operator = $this->user('operator');

        $category = \App\Models\Category::create(['name' => 'Cover', 'is_active' => true]);
        $product  = \App\Models\Product::create([
            'category_id' => $category->id,
            'name'        => 'Cover Uji',
            'is_active'   => true,
        ]);

        $replacement = \App\Models\Replacement::create([
            'product_id'       => $product->id,
            'user_id'          => $operator->id,
            'operator_name'    => $operator->name,
            'replacement_date' => now()->toDateString(),
            'qty'              => 1,
            'reason'           => 'rusak',
        ]);

        $this->grant('operator', 'barang-pengganti', false);

        $this->actingAs($operator)
            ->patch("/replacements/{$replacement->id}/toggle-complete")
            ->assertForbidden();
    }

    public function test_revoke_chatting_memblokir_kirim_pesan(): void
    {
        $this->grant('operator', 'chatting', false);

        $this->actingAs($this->user('operator'))
            ->post('/messages', ['message' => 'halo'])
            ->assertForbidden();
    }

    public function test_grant_kategori_ke_admin_membuka_halaman_kategori(): void
    {
        $this->grant('admin', 'kategori', true);

        $this->actingAs($this->user('admin'))
            ->get('/categories')
            ->assertOk();
    }

    public function test_admin_tanpa_grant_kategori_tetap_ditolak(): void
    {
        $this->actingAs($this->user('admin'))
            ->get('/categories')
            ->assertForbidden();
    }

    public function test_grant_lihat_kategori_saja_tidak_membuka_form_tambah(): void
    {
        $this->grant('admin', 'kategori', true);

        $this->actingAs($this->user('admin'))
            ->get('/categories/create')
            ->assertForbidden();
    }

    public function test_grant_aksi_tanpa_menu_induk_tetap_ditolak(): void
    {
        $this->grant('admin', 'kategori', false);
        $this->grant('admin', 'kategori.create', true);

        $this->actingAs($this->user('admin'))
            ->get('/categories/create')
            ->assertForbidden();
    }

    public function test_developer_bypass_semua_permission(): void
    {
        $this->grant('developer', 'kategori', false);

        $this->actingAs($this->user('developer'))
            ->get('/categories')
            ->assertOk();
    }

    public function test_halaman_hak_akses_menampilkan_semua_permission_yang_dikelola(): void
    {
        $state = $this->actingAs($this->user('developer'))
            ->get('/permissions')
            ->assertOk()
            ->viewData('page')['props']['state'];

        foreach (['notes.create', 'chatting.send', 'barang-pengganti.edit', 'kategori.delete'] as $key) {
            $this->assertArrayHasKey("admin|{$key}", $state);
        }
    }

    public function test_simpan_hak_akses_menulis_baris_untuk_setiap_permission(): void
    {
        $this->actingAs($this->user('developer'))
            ->post('/permissions', ['allowed' => ['admin' => ['notes' => '1']]])
            ->assertRedirect(route('permissions.index'));

        $this->assertDatabaseHas('role_menu_permissions', [
            'role' => 'admin', 'menu_key' => 'notes', 'allowed' => true,
        ]);
        $this->assertDatabaseHas('role_menu_permissions', [
            'role' => 'admin', 'menu_key' => 'notes.create', 'allowed' => false,
        ]);
    }
}
