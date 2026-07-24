<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
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

    public function test_login_berhasil_dengan_kredensial_benar(): void
    {
        $this->makeUser();

        $res = $this->post('/login', [
            'identifier' => 'operatoruji',
            'password'   => 'rahasia123',
        ]);

        $res->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_percobaan_login_keenam_diblokir_rate_limiter(): void
    {
        $this->makeUser();

        // 5 percobaan gagal pertama hanya menampilkan error kredensial salah
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'identifier' => 'operatoruji',
                'password'   => 'salah-password',
            ])->assertSessionHasErrors('identifier');
        }

        // Percobaan ke-6 harus diblokir dengan pesan rate limit
        $res = $this->post('/login', [
            'identifier' => 'operatoruji',
            'password'   => 'salah-password',
        ]);

        $res->assertSessionHasErrors('identifier');
        $error = session('errors')->first('identifier');
        $this->assertStringContainsString('Terlalu banyak percobaan', $error);
        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        $user = $this->makeUser();
        $user->update(['is_active' => false]);

        $this->post('/login', [
            'identifier' => 'operatoruji',
            'password'   => 'rahasia123',
        ])->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }
}
