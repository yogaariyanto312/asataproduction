<?php

namespace Tests\Feature\Ref;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Penanganan "Page Expired" (419).
 *
 * Laravel mengubah TokenMismatchException menjadi HttpException(419) di
 * prepareException(), yang dipanggil SEBELUM callback render kustom. Penanganan
 * lama menangkap TokenMismatchException lewat tipe, sehingga tidak pernah kena
 * dan user tetap melihat halaman 419 mentah. Test ini mengunci perilaku yang
 * benar supaya jebakan itu tidak terulang.
 *
 * Middleware CSRF sendiri dilewati saat pengujian (VerifyCsrfToken memeriksa
 * runningUnitTests), jadi exception-nya dilempar dari route uji agar jalur
 * penanganannya tetap yang asli.
 */
class CsrfExpiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post('/__uji-csrf', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });
    }

    private function user(): User
    {
        return User::create([
            'name'      => 'Operator Uji',
            'username'  => 'opujicsrf',
            'email'     => 'opujicsrf@uji.test',
            'role'      => 'operator',
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    public function test_tamu_diarahkan_ke_login_bukan_halaman_419(): void
    {
        $response = $this->post('/__uji-csrf', ['identifier' => 'budi']);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('warning');
        $this->assertNotSame(419, $response->getStatusCode());
    }

    public function test_user_login_dikembalikan_dengan_input_dipertahankan(): void
    {
        $response = $this->actingAs($this->user())
            ->from('/dashboard')
            ->post('/__uji-csrf', [
                'notes'    => 'UP NO.001-010',
                'password' => 'jangan-disimpan',
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error');
        $response->assertSessionHasInput('notes', 'UP NO.001-010');

        // Password tidak boleh ikut dipertahankan di sesi.
        $this->assertNull(session('_old_input.password'));
    }

    public function test_permintaan_json_menerima_419_bukan_redirect(): void
    {
        $response = $this->postJson('/__uji-csrf', []);

        $response->assertStatus(419);
        $response->assertJsonStructure(['message']);
    }

    public function test_status_lain_tetap_ditangani_laravel(): void
    {
        // Callback 419 mengembalikan null untuk status lain; kalau null itu
        // hilang, 404 akan ikut berubah jadi redirect.
        $this->get('/halaman-yang-tidak-ada-sama-sekali')->assertStatus(404);
    }

    public function test_endpoint_csrf_token_mengembalikan_token_sesi(): void
    {
        $response = $this->get(route('csrf.token'));

        $response->assertOk();
        $response->assertJsonStructure(['token']);
        $this->assertSame(csrf_token(), $response->json('token'));
        $response->assertHeader('Cache-Control', 'no-cache, no-store, private');
    }

    public function test_endpoint_csrf_token_juga_bisa_dipakai_user_yang_login(): void
    {
        // Route ini melewati middleware web lengkap (AuthenticateSession dan
        // EnforceMenuAccess). Pastikan tidak ada yang memblokirnya, karena
        // halaman di dalam aplikasi ikut memakainya untuk menyegarkan token.
        $response = $this->actingAs($this->user())->get(route('csrf.token'));

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
    }
}
