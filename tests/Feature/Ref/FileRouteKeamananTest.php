<?php

namespace Tests\Feature\Ref;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Route penyaji berkas menerima path bebas (`{path}` dengan `.+`), jadi perlu
 * dipastikan tidak bisa dipakai membaca berkas di luar disk publik.
 */
class FileRouteKeamananTest extends TestCase
{
    use RefreshDatabase;

    public static function jalurJahat(): array
    {
        return [
            'naik satu tingkat'     => ['../.env'],
            'naik beberapa tingkat' => ['../../.env'],
            'menyusup di tengah'    => ['gambar-kerja/../../../.env'],
            'absolut'               => ['/etc/passwd'],
        ];
    }

    #[DataProvider('jalurJahat')]
    public function test_file_menolak_jalur_keluar_disk(string $path): void
    {
        $user = User::factory()->create(['role' => 'developer']);

        $res = $this->actingAs($user)->get('/file/' . ltrim($path, '/'));

        $this->assertContains($res->getStatusCode(), [403, 404],
            "Path '{$path}' harus ditolak, bukan disajikan (status {$res->getStatusCode()}).");
    }

    #[DataProvider('jalurJahat')]
    public function test_thumb_menolak_jalur_keluar_disk(string $path): void
    {
        $user = User::factory()->create(['role' => 'developer']);

        $res = $this->actingAs($user)->get('/thumb/' . ltrim($path, '/'));

        $this->assertContains($res->getStatusCode(), [403, 404],
            "Path '{$path}' harus ditolak, bukan disajikan (status {$res->getStatusCode()}).");
    }

    public function test_berkas_hanya_untuk_yang_sudah_login(): void
    {
        $this->get('/file/gambar-kerja/apa.pdf')->assertRedirect(route('login'));
        $this->get('/thumb/gambar-kerja/apa.jpg')->assertRedirect(route('login'));
    }
}
