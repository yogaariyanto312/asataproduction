<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Halaman login sudah dipindah ke React (Inertia). Tes ini menjaga agar
 * perpindahan itu tidak diam-diam menghilangkan perilaku versi Blade:
 * header anti-bfcache, pesan error, rate limiter, dan alur login itu sendiri.
 */
class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'      => 'Operator Uji',
            'username'  => 'operatoruji',
            'email'     => 'operator@uji.test',
            'role'      => 'operator',
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    public function test_halaman_login_dirender_react_bukan_blade(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login')
                ->has('backgroundUrl')
                ->has('forgotPasswordUrl')
                ->has('loginUrl')
                ->has('year')
            );
    }

    public function test_header_anti_bfcache_tetap_dipertahankan(): void
    {
        // Tanpa header ini, tombol Back menampilkan halaman login basi dengan
        // token CSRF kedaluwarsa -> 419 Page Expired.
        $res = $this->get('/login');

        // Symfony menormalkan urutan direktif, jadi yang diperiksa keberadaannya
        // satu per satu, bukan string persisnya.
        $cacheControl = $res->headers->get('Cache-Control');
        foreach (['no-store', 'no-cache', 'must-revalidate', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }

        $res->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString('publickey-credentials-get=()', $res->headers->get('Permissions-Policy'));
    }

    public function test_user_yang_sudah_login_diarahkan_ke_dashboard(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/login')
            ->assertRedirect(route('dashboard'));
    }

    public function test_login_lewat_request_inertia_berhasil(): void
    {
        $this->makeUser();

        $this->post('/login', [
            'identifier' => 'operatoruji',
            'password'   => 'rahasia123',
        ], ['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_error_kredensial_sampai_ke_props_react(): void
    {
        $this->makeUser();

        $this->from('/login')->post('/login', [
            'identifier' => 'operatoruji',
            'password'   => 'salah',
        ])->assertRedirect('/login');

        // Error dibaca komponen React lewat shared prop "errors".
        $this->followingRedirects()
            ->from('/login')
            ->post('/login', ['identifier' => 'operatoruji', 'password' => 'salah'])
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login')
                ->where('errors.identifier', 'Username/email atau password salah.')
            );
    }

    public function test_pesan_rate_limit_sampai_ke_props_react(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['identifier' => 'operatoruji', 'password' => 'salah']);
        }

        // Pesan harus tetap memuat pola "Coba lagi dalam N detik" karena dari
        // situlah komponen React membaca durasi kunci form.
        $this->followingRedirects()
            ->from('/login')
            ->post('/login', ['identifier' => 'operatoruji', 'password' => 'salah'])
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login')
                ->where('errors.identifier', fn ($msg) => (bool) preg_match('/Coba lagi dalam \d+ detik/', $msg))
            );

        $this->assertGuest();
    }

    public function test_flash_logout_tampil_di_halaman_login(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->followingRedirects()
            ->actingAs($user)
            ->post('/logout')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login')
                ->where('flash.success', 'Anda berhasil logout.')
            );
    }
}
