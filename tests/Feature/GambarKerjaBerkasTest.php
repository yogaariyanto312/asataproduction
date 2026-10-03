<?php

namespace Tests\Feature;

use App\Models\GambarKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GambarKerjaBerkasTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = new User(['name' => 'Admin', 'username' => 'adm', 'email' => 'adm@uji.test', 'role' => 'admin', 'password' => Hash::make('x12345678')]);
        $u->forceFill(['is_active' => true])->save();

        return $u;
    }

    public function test_daftar_berkas_memuat_berkas_dan_thumbnail_kecil(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $gambar = UploadedFile::fake()->image('a.jpg', 2400, 1600)->store('gambar-kerja', 'public');
        $pdf    = UploadedFile::fake()->create('b.pdf', 300, 'application/pdf')->store('gambar-kerja', 'public');

        foreach ([[$gambar, 'image', 1], [$pdf, 'pdf', 2]] as [$path, $type, $urut]) {
            GambarKerja::create([
                'judul' => 'Trafo', 'seri' => 'S1', 'kva' => '50', 'tahun' => 2026, 'kategori_seri' => 'pln',
                'file_path' => $path, 'file_type' => $type, 'uploaded_by' => $admin->id, 'urutan' => $urut,
            ]);
        }

        $berkas = collect($this->actingAs($admin)->getJson('/api/gambar-kerja/berkas')->assertOk()->json('berkas'));

        // 2 berkas + 1 thumbnail kartu grup
        $this->assertCount(3, $berkas);
        $this->assertSame(2, $berkas->filter(fn ($b) => str_contains($b['url'], '/file/'))->count());

        $thumb = $berkas->first(fn ($b) => str_contains($b['url'], '/thumb/'));
        $this->assertNotNull($thumb);
        // Ukuran thumbnail = turunan kecil, bukan gambar aslinya
        $this->assertLessThan(Storage::disk('public')->size($gambar), $thumb['ukuran']);
    }

    public function test_tamu_tidak_bisa_melihat_daftar(): void
    {
        $this->getJson('/api/gambar-kerja/berkas')->assertUnauthorized();
    }
}
