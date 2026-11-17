<?php

namespace Tests\Feature\Ref;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Overlay upload gambar kerja harus melaporkan kemajuan sebenarnya. Sebelumnya
 * hanya ada lingkaran berputar tanpa angka apa pun — dan di mode gelap kelas
 * `dark:border-*` menimpa `border-t-*` sehingga lingkarannya jadi satu warna
 * dan putarannya pun tak terlihat.
 */
class GambarKerjaUploadProgressTest extends TestCase
{
    use RefreshDatabase;

    private function jsx(): string
    {
        return file_get_contents(resource_path('js/Pages/GambarKerja/Create.jsx'));
    }

    public function test_halaman_upload_punya_elemen_progres(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $this->actingAs($dev)->get('/gambar-kerja/create')->assertOk();

        $jsx = $this->jsx();
        foreach (['gkUploadForm', 'gkBar', 'gkPct', 'gkInfo', 'gkStage', 'gkRetry'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $jsx, "Elemen #{$id} tidak ada di halaman.");
        }

        $this->assertStringContainsString("xhr.upload.addEventListener('progress'", $jsx,
            'Progres harus dibaca dari event upload XHR, bukan animasi belaka.');
    }

    public function test_warna_spinner_tidak_bergantung_urutan_kelas(): void
    {
        $this->assertStringContainsString("borderTopColor: up?.error ? '#ef4444' : '#2563eb'", $this->jsx(),
            'Warna sisi atas spinner harus inline agar tidak tertimpa kelas border lain.');
        $this->assertStringNotContainsString('border-t-blue-500 animate-spin', $this->jsx());
    }

    public function test_tidak_ada_lagi_onsubmit_lama_yang_hanya_menampilkan_overlay(): void
    {
        $this->assertStringNotContainsString('post(action, { forceFormData: true })', $this->jsx(),
            'Submit lama tanpa progres seharusnya sudah diganti penanganan XHR.');
        $this->assertStringContainsString('function kirim()', $this->jsx());
    }
}
