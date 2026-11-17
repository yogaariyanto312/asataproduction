<?php

namespace Tests\Feature\Ref;

use App\Support\ImageThumbnail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;

/**
 * Kartu daftar gambar kerja hanya setinggi 144 px, tetapi thumbnail yang
 * diunggah operator bisa 3000 px / ratusan KB. Halaman daftar karenanya menarik
 * data jauh lebih besar dari yang dibutuhkan — paling terasa di HP.
 */
class GambarKerjaThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private function gambarBesar(int $w = 3000, int $h = 2000): string
    {
        $im = imagecreatetruecolor($w, $h);
        // isi bergradasi supaya JPEG tidak terkompres tidak wajar (bukan bidang polos)
        for ($x = 0; $x < $w; $x += 10) {
            $warna = imagecolorallocate($im, $x % 255, (int) ($x / 12) % 255, 128);
            imagefilledrectangle($im, $x, 0, $x + 10, $h, $warna);
        }
        ob_start();
        imagejpeg($im, null, 92);
        $biner = ob_get_clean();
        imagedestroy($im);

        return $biner;
    }

    public function test_turunan_jauh_lebih_kecil_dari_sumbernya(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gambar-kerja/thumbnails/besar.jpg', $this->gambarBesar());

        $asal = Storage::disk('public')->size('gambar-kerja/thumbnails/besar.jpg');
        $path = ImageThumbnail::untuk('gambar-kerja/thumbnails/besar.jpg');

        $this->assertNotNull($path, 'Turunan gagal dibuat.');
        $kecil = Storage::disk('public')->size($path);

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        fwrite(STDERR, sprintf("sumber %d KB (3000px) -> turunan %d KB (%dx%d)\n",
            $asal / 1024, $kecil / 1024, $w, $h));

        $this->assertSame(ImageThumbnail::LEBAR, $w, 'Lebar turunan harus dibatasi.');
        $this->assertLessThan($asal / 3, $kecil, 'Turunan harus jauh lebih kecil dari sumbernya.');
    }

    public function test_turunan_dipakai_ulang_bukan_dibuat_lagi(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gambar-kerja/thumbnails/besar.jpg', $this->gambarBesar());

        $a = ImageThumbnail::untuk('gambar-kerja/thumbnails/besar.jpg');
        $waktu = Storage::disk('public')->lastModified($a);
        $b = ImageThumbnail::untuk('gambar-kerja/thumbnails/besar.jpg');

        $this->assertSame($a, $b, 'Path turunan harus stabil.');
        $this->assertSame($waktu, Storage::disk('public')->lastModified($b), 'Turunan tidak boleh dibuat ulang tiap permintaan.');
    }

    public function test_gambar_kecil_tidak_diperbesar(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gambar-kerja/thumbnails/kecil.jpg', $this->gambarBesar(200, 150));

        $path = ImageThumbnail::untuk('gambar-kerja/thumbnails/kecil.jpg');
        [$w] = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame(200, $w, 'Gambar yang sudah kecil tidak boleh diperbesar.');
    }

    public function test_pdf_tidak_dipaksa_jadi_turunan(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gambar-kerja/berkas.pdf', '%PDF-1.4 palsu');

        $this->assertNull(ImageThumbnail::untuk('gambar-kerja/berkas.pdf'),
            'Format tak didukung harus mengembalikan null agar pemanggil memakai berkas asli.');
    }

    public function test_route_thumb_menyajikan_versi_kecil(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gambar-kerja/thumbnails/besar.jpg', $this->gambarBesar());
        $user = User::factory()->create(['role' => 'admin']);

        $res = $this->actingAs($user)->get(route('storage.thumb', 'gambar-kerja/thumbnails/besar.jpg'));
        $res->assertOk();

        $isi = $res->streamedContent();
        [$w] = getimagesizefromstring($isi);

        $this->assertSame(ImageThumbnail::LEBAR, $w);
        $this->assertStringContainsString('immutable', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $res->headers->get('Cache-Control'));
    }

    public function test_route_thumb_untuk_pdf_tetap_menyajikan_berkasnya(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gambar-kerja/berkas.pdf', '%PDF-1.4 palsu');
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get(route('storage.thumb', 'gambar-kerja/berkas.pdf'))->assertOk();
    }

    public function test_thumbnail_dikompres_saat_diunggah(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'developer']);

        \App\Models\GambarKerja::create([
            'judul' => 'Uji', 'seri' => 'S1', 'kva' => '100', 'tahun' => 2026,
            'file_path' => 'gambar-kerja/a.pdf', 'file_type' => 'pdf',
            'uploaded_by' => $user->id, 'urutan' => 1,
        ]);

        $berkas = UploadedFile::fake()->createWithContent('thumb.jpg', $this->gambarBesar());
        $asal   = $berkas->getSize();

        $this->actingAs($user)->post(route('gambar-kerja.upload-thumbnail'), [
            'judul' => 'Uji', 'seri' => 'S1', 'kva' => '100', 'tahun' => 2026,
            'thumbnail' => $berkas,
        ])->assertRedirect();

        $path = \App\Models\GambarKerja::first()->thumbnail_path;
        $this->assertNotNull($path);

        $tersimpan = Storage::disk('public')->size($path);
        [$w] = getimagesizefromstring(Storage::disk('public')->get($path));
        fwrite(STDERR, sprintf("upload %d KB -> tersimpan %d KB (%dpx)\n", $asal / 1024, $tersimpan / 1024, $w));

        $this->assertSame(ImageThumbnail::LEBAR, $w, 'Thumbnail harus dikecilkan saat diunggah.');
        $this->assertLessThan($asal / 3, $tersimpan);
    }

    public function test_route_thumb_butuh_login(): void
    {
        $this->get(route('storage.thumb', 'gambar-kerja/apa.jpg'))->assertRedirect(route('login'));
    }
}
