<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Gambar kerja dilayani lewat route storage.file. Berkasnya bernama unik dan
 * tidak pernah ditimpa, jadi browser boleh menyimpannya lama — inilah yang
 * membuat gambar/PDF yang sudah dibuka tidak perlu diunduh ulang di koneksi
 * lambat. Tes ini menjaga header itu tidak hilang diam-diam.
 */
class FileCacheTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::create([
            'name'      => 'Admin Berkas',
            'username'  => 'adminberkas',
            'email'     => 'admin@berkas.test',
            'password'  => 'rahasia-panjang-sekali',
            'role'      => 'admin',
            'is_active' => true,
        ]);
    }

    private function berkasContoh(): string
    {
        Storage::fake('public');
        UploadedFile::fake()->create('gambar.pdf', 12, 'application/pdf')
            ->storeAs('gambar-kerja', 'contoh.pdf', 'public');

        return 'gambar-kerja/contoh.pdf';
    }

    public function test_berkas_boleh_disimpan_lama_di_browser(): void
    {
        $path = $this->berkasContoh();

        $res = $this->actingAs($this->pengguna())->get(route('storage.file', ['path' => $path]));

        $res->assertOk();

        $cacheControl = $res->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);

        // Berkas ada di balik login, jadi hanya browser pengguna yang boleh
        // menyimpannya — bukan proxy bersama.
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringNotContainsString('no-store', $cacheControl);

        $this->assertNotEmpty($res->headers->get('ETag'));
    }

    public function test_salinan_yang_sama_dijawab_304(): void
    {
        $path = $this->berkasContoh();
        $user = $this->pengguna();

        $etag = $this->actingAs($user)
            ->get(route('storage.file', ['path' => $path]))
            ->headers->get('ETag');

        $this->actingAs($user)
            ->get(route('storage.file', ['path' => $path]), ['If-None-Match' => $etag])
            ->assertStatus(304);
    }

    public function test_berkas_tetap_wajib_login(): void
    {
        $path = $this->berkasContoh();

        $this->get(route('storage.file', ['path' => $path]))->assertRedirect(route('login'));
    }
}
