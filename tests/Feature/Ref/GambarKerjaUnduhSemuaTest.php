<?php

namespace Tests\Feature\Ref;

use App\Models\GambarKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tombol "Unduh Semua" di Gambar Kerja: menyimpan seluruh berkas di perangkat
 * lebih dulu supaya saat genting gambar langsung terbuka.
 *
 * Service worker mencocokkan cache per URL persis, jadi URL yang didaftar
 * endpoint harus SAMA dengan yang dipakai halaman — beda sedikit saja (mis.
 * /file/ vs /thumb/) dan hasil unduhannya tidak pernah terpakai.
 */
class GambarKerjaUnduhSemuaTest extends TestCase
{
    use RefreshDatabase;

    private function gambar(string $judul, string $path, ?string $thumb = null): GambarKerja
    {
        Storage::disk('public')->put($path, str_repeat('x', 1234));

        return GambarKerja::create([
            'judul' => $judul, 'seri' => '26T001', 'kva' => '100', 'tahun' => 2026,
            'file_path' => $path, 'file_type' => 'pdf', 'thumbnail_path' => $thumb,
            'uploaded_by' => User::first()->id, 'urutan' => 1,
        ]);
    }

    public function test_daftar_memuat_semua_berkas_beserta_ukurannya(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'operator']);
        $this->gambar('Trafo A', 'gambar-kerja/a1.pdf');
        $this->gambar('Trafo A', 'gambar-kerja/a2.pdf');

        $json = $this->actingAs($user)->getJson(route('api.gambar-kerja.berkas'))->assertOk()->json();
        $urls = array_column($json['berkas'], 'url', 'url');

        $this->assertArrayHasKey(route('storage.file', 'gambar-kerja/a1.pdf'), $urls);
        $this->assertArrayHasKey(route('storage.file', 'gambar-kerja/a2.pdf'), $urls);
        $this->assertSame(1234, $json['berkas'][0]['ukuran']);
        $this->assertContains(asset('vendor/pdfjs/pdf.worker.min.js'), $json['aset']);
    }

    public function test_berkas_yang_hilang_dari_penyimpanan_tidak_didaftar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'operator']);
        $g = $this->gambar('Trafo B', 'gambar-kerja/b.pdf');
        Storage::disk('public')->delete($g->file_path);

        $json = $this->actingAs($user)->getJson(route('api.gambar-kerja.berkas'))->assertOk()->json();

        // Berkas yang pasti 404 hanya akan tercatat "gagal" terus-menerus.
        $this->assertSame([], $json['berkas']);
    }

    public function test_url_thumbnail_sama_persis_dengan_yang_tampil_di_halaman(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'operator']);
        $this->gambar('Pakai sampul', 'gambar-kerja/c.pdf', 'gambar-kerja/thumbnails/c.jpg');
        Storage::disk('public')->put('gambar-kerja/thumbnails/c.jpg', 'jpg');
        $this->gambar('PDF tanpa sampul', 'gambar-kerja/d.pdf');
        GambarKerja::create([
            'judul' => 'Foto tanpa sampul', 'seri' => '26T002', 'kva' => '50', 'tahun' => 2026,
            'file_path' => 'gambar-kerja/e.jpg', 'file_type' => 'image',
            'uploaded_by' => $user->id, 'urutan' => 1,
        ]);
        Storage::disk('public')->put('gambar-kerja/e.jpg', 'jpg');

        $props = $this->actingAs($user)->get(route('gambar-kerja.index'))->assertOk()->viewData('page')['props'];
        $dipakai = collect($props['years'])->flatMap(fn ($y) => collect($y['groups'])->pluck('thumbnail'))->filter()->all();
        $json = $this->getJson(route('api.gambar-kerja.berkas'))->json();

        $thumbs = array_filter(array_column($json['berkas'], 'url'), fn ($u) => str_contains($u, '/thumb/'));
        $this->assertCount(2, $thumbs);
        foreach ($thumbs as $u) {
            $this->assertContains($u, $dipakai, "Thumbnail {$u} tidak dipakai halaman.");
        }
    }

    public function test_halaman_menampilkan_tombol_unduh_semua(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);

        $props = $this->actingAs($user)->get(route('gambar-kerja.index'))->assertOk()->viewData('page')['props'];
        $this->assertSame(route('api.gambar-kerja.berkas'), $props['berkasUrl']);

        $jsx = file_get_contents(resource_path('js/Pages/GambarKerja/Index.jsx'));
        $this->assertStringContainsString('id="gk-offline"', $jsx);
        $this->assertStringContainsString('Unduh Semua', $jsx);
    }

    public function test_tamu_tidak_bisa_melihat_daftar_berkas(): void
    {
        $this->getJson(route('api.gambar-kerja.berkas'))->assertUnauthorized();
    }
}
