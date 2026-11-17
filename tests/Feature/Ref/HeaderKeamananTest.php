<?php

namespace Tests\Feature\Ref;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Header keamanan HTTP.
 *
 * Kode sisi peramban bisa dibaca siapa pun lewat Inspect — itu memang harus.
 * Yang dijaga di sini adalah batasan yang dikirim bersama tiap halaman:
 * tidak boleh dibingkai situs lain, tipe berkas tidak ditebak-tebak, alamat
 * halaman tidak bocor ke situs luar, dan versi PHP tidak diumumkan.
 */
class HeaderKeamananTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name'      => 'Uji Header',
            'username'  => 'ujiheader',
            'email'     => 'ujiheader@uji.test',
            'role'      => 'developer',
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    public function test_halaman_login_membawa_header_keamanan(): void
    {
        $res = $this->get(route('login'));

        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_halaman_dalam_aplikasi_juga_membawanya(): void
    {
        $res = $this->actingAs($this->user())->get(route('dashboard'));

        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_aturan_csp_menutup_pembingkaian_dan_penyisipan(): void
    {
        $csp = $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'self'", (string) $csp);
        $this->assertStringContainsString("object-src 'none'", (string) $csp);
        $this->assertStringContainsString("base-uri 'self'", (string) $csp);
    }

    public function test_csp_tidak_mengunci_skrip_inline(): void
    {
        // Aplikasi ini penuh skrip inline; script-src yang ketat akan
        // mematikan halamannya. Dijaga supaya tidak dipasang tanpa sadar.
        $csp = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('script-src', $csp,
            'script-src akan mematikan skrip inline aplikasi — perlu penyesuaian dulu.');
    }

    public function test_versi_php_tidak_diumumkan(): void
    {
        $this->assertNull($this->get(route('login'))->headers->get('X-Powered-By'));
    }

    public function test_hsts_hanya_dikirim_lewat_https(): void
    {
        // Permintaan uji standar memakai http, jadi HSTS-nya belum perlu ada.
        $this->assertNull($this->get(route('login'))->headers->get('Strict-Transport-Security'));

        $aman = $this->get('https://localhost' . route('login', absolute: false));
        $this->assertNotNull($aman->headers->get('Strict-Transport-Security'),
            'Lewat HTTPS, peramban harus diminta mengingat untuk tidak memakai HTTP lagi.');
    }

    public function test_upgrade_insecure_requests_tidak_dipasang_lewat_http(): void
    {
        // Kontrol negatif, dan ini bukan soal teori: aturan ini pernah dipasang
        // tanpa syarat, lalu server pabrik (http polos di alamat IP) tampil
        // telanjang tanpa gaya — peramban memaksa semua CSS/JS diminta lewat
        // https:// ke port 443 yang tidak ada isinya.
        $csp = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('upgrade-insecure-requests', $csp,
            'Di server HTTP polos, aturan ini mematikan semua aset halaman.');
    }

    public function test_upgrade_insecure_requests_dipasang_lewat_https(): void
    {
        $csp = (string) $this->get('https://localhost' . route('login', absolute: false))
            ->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('upgrade-insecure-requests', $csp,
            'Saat sudah HTTPS, sisa permintaan http:// memang harus dinaikkan.');
    }

    public function test_berkas_yang_dilayani_ikut_dilindungi(): void
    {
        // Berkas unggahan paling rawan ditebak tipenya oleh peramban.
        $res = $this->actingAs($this->user())->get('/file/gambar-kerja/tidak-ada.pdf');

        $res->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
