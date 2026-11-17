<?php

namespace Tests\Feature\Ref;

use App\Models\GambarKerja;
use App\Models\User;
use App\Support\ImageThumbnail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Menghapus gambar kerja harus ikut membuang berkas pendukungnya.
 *
 * Sebelumnya hanya `file_path` yang dihapus: thumbnail grup tertinggal di disk
 * tanpa pemilik. Itu yang membuat folder gambar-kerja di server menumpuk berkas
 * yatim (97 MB saat diperiksa 19 Sep 2026).
 */
class GambarKerjaHapusBerkasTest extends TestCase
{
    use RefreshDatabase;

    private function gambar(): string
    {
        $im = imagecreatetruecolor(800, 600);
        imagefilledrectangle($im, 0, 0, 800, 600, imagecolorallocate($im, 10, 120, 200));
        ob_start();
        imagejpeg($im, null, 85);
        $biner = ob_get_clean();
        imagedestroy($im);

        return $biner;
    }

    /** @return array{0:User,1:string} */
    private function grup(int $jumlahBerkas = 2): array
    {
        $user = User::factory()->create(['role' => 'developer']);

        Storage::disk('public')->put('gambar-kerja/thumb.jpg', $this->gambar());

        for ($i = 1; $i <= $jumlahBerkas; $i++) {
            Storage::disk('public')->put("gambar-kerja/f{$i}.pdf", '%PDF palsu ' . $i);
            GambarKerja::create([
                'judul' => 'Grup Uji', 'seri' => 'S1', 'kva' => '100', 'tahun' => 2026,
                'file_path' => "gambar-kerja/f{$i}.pdf", 'file_type' => 'pdf',
                'thumbnail_path' => 'gambar-kerja/thumb.jpg',
                'uploaded_by' => $user->id, 'urutan' => $i,
            ]);
        }

        return [$user, 'gambar-kerja/thumb.jpg'];
    }

    private function paramGrup(): array
    {
        return ['judul' => 'Grup Uji', 'seri' => 'S1', 'kva' => '100', 'tahun' => 2026];
    }

    public function test_hapus_grup_membuang_semua_berkas_termasuk_thumbnail(): void
    {
        Storage::fake('public');
        [$user, $thumb] = $this->grup();

        // pastikan turunan kecil sudah sempat dibuat, seperti setelah halaman dibuka
        $turunan = ImageThumbnail::untuk($thumb);
        $this->assertTrue(Storage::disk('public')->exists($turunan));

        $this->actingAs($user)
            ->delete(route('gambar-kerja.destroy-by-group'), $this->paramGrup())
            ->assertRedirect(route('gambar-kerja.index'));

        $this->assertFalse(Storage::disk('public')->exists('gambar-kerja/f1.pdf'));
        $this->assertFalse(Storage::disk('public')->exists('gambar-kerja/f2.pdf'));
        $this->assertFalse(Storage::disk('public')->exists($thumb), 'Thumbnail grup tertinggal sebagai berkas yatim.');
        $this->assertFalse(Storage::disk('public')->exists($turunan), 'Turunan kecil tertinggal sebagai berkas yatim.');
        $this->assertSame(0, GambarKerja::count());
    }

    public function test_hapus_berkas_terakhir_juga_membuang_thumbnail_grup(): void
    {
        Storage::fake('public');
        [$user, $thumb] = $this->grup(1);
        $gk = GambarKerja::first();

        $this->actingAs($user)->delete(route('gambar-kerja.destroy', $gk))->assertRedirect();

        $this->assertFalse(Storage::disk('public')->exists('gambar-kerja/f1.pdf'));
        $this->assertFalse(Storage::disk('public')->exists($thumb), 'Grup sudah kosong tapi thumbnail-nya tertinggal.');
    }

    public function test_hapus_satu_berkas_tidak_mengganggu_thumbnail_grup_yang_masih_terpakai(): void
    {
        Storage::fake('public');
        [$user, $thumb] = $this->grup(2);
        $gk = GambarKerja::orderBy('urutan')->first();

        $this->actingAs($user)->delete(route('gambar-kerja.destroy', $gk))->assertRedirect();

        $this->assertTrue(Storage::disk('public')->exists($thumb), 'Thumbnail masih dipakai berkas lain, tidak boleh ikut terhapus.');
        $this->assertTrue(Storage::disk('public')->exists('gambar-kerja/f2.pdf'));
        $this->assertSame(1, GambarKerja::count());
    }

    public function test_ganti_thumbnail_membuang_yang_lama(): void
    {
        Storage::fake('public');
        [$user, $lama] = $this->grup(1);

        $baru = \Illuminate\Http\UploadedFile::fake()->createWithContent('baru.jpg', $this->gambar());

        $this->actingAs($user)->post(route('gambar-kerja.upload-thumbnail'),
            $this->paramGrup() + ['thumbnail' => $baru])->assertRedirect();

        $this->assertFalse(Storage::disk('public')->exists($lama), 'Thumbnail lama harus dibuang saat diganti.');
        $this->assertNotSame($lama, GambarKerja::first()->thumbnail_path);
    }

    public function test_hapus_thumbnail_membuang_berkasnya(): void
    {
        Storage::fake('public');
        [$user, $thumb] = $this->grup(1);

        $this->actingAs($user)->delete(route('gambar-kerja.delete-thumbnail'), $this->paramGrup())->assertRedirect();

        $this->assertFalse(Storage::disk('public')->exists($thumb));
        $this->assertNull(GambarKerja::first()->thumbnail_path);
    }
}
