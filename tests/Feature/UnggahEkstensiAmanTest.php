<?php

namespace Tests\Feature;

use App\Models\GambarKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Berkas unggahan disimpan dengan ekstensi dari ISI berkas, bukan nama dari klien.
 * Tanpa ini, PDF asli bernama "x.html" lolos validasi mimes lalu tersimpan sebagai
 * .html di folder yang bisa dibuka lewat web (public/storage), jadi dirender sebagai
 * halaman (XSS pada origin yang sama). Laravel sendiri sudah menolak nama .php/.phar.
 */
class UnggahEkstensiAmanTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_bernama_html_tersimpan_sebagai_pdf(): void
    {
        Storage::fake('public');
        $u = new User(['name' => 'Adm', 'username' => 'adm', 'email' => 'adm@uji.test', 'role' => 'admin', 'password' => Hash::make('x12345678')]);
        $u->forceFill(['is_active' => true])->save();

        // Berkas nyata di disk: UploadedFile::fake() menebak jenisnya dari NAMA, bukan isi.
        $tmp = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($tmp, "%PDF-1.4
1 0 obj<</Type/Catalog>>endobj
trailer<</Root 1 0 R>>
%%EOF
");
        $berkas = new UploadedFile($tmp, 'jahat.html', 'application/pdf', null, true);

        $this->actingAs($u)->post(route('gambar-kerja.store'), ['judul' => 'X', 'files' => [$berkas]])->assertSessionHasNoErrors();

        $g = GambarKerja::firstOrFail();
        $this->assertStringEndsWith('.pdf', $g->file_path);
        $this->assertSame('pdf', $g->file_type);
        $this->assertStringNotContainsString('.html', $g->file_path);
    }
}
