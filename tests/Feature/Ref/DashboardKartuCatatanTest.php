<?php

namespace Tests\Feature\Ref;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kartu catatan di dashboard.
 *
 * Dua hal yang dijaga: batang gulirnya disembunyikan tanpa mematikan gulirnya,
 * dan tiap catatan bisa dibentang untuk melihat isinya — termasuk tombol yang
 * membuka isi lengkapnya di modal. (asata: data lewat props Inertia, tampilan
 * di Pages/Dashboard.jsx; React meng-escape semua teks kecuali `html` yang
 * sudah disaring HtmlCatatan.)
 */
class DashboardKartuCatatanTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'developer']);
    }

    private function catatan(User $user, array $atribut = []): Note
    {
        return Note::create(array_merge([
            'user_id' => $user->id,
            'title'   => 'Cek trafo 2601',
            'content' => "Baris pertama\nBaris kedua yang panjang sekali supaya kelihatan kalau dipotong.",
            'color'   => 'amber',
        ], $atribut));
    }

    private function dataCatatan(User $user): array
    {
        return collect($this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('page')['props']['notes'])->toArray();
    }

    private function jsx(): string
    {
        return file_get_contents(resource_path('js/Pages/Dashboard.jsx'));
    }

    public function test_daftar_catatan_tetap_bisa_digulir_tanpa_batang_gulir(): void
    {
        $this->assertStringContainsString('className="au-db-catatan-list no-scrollbar"', $this->jsx());

        $ui = file_get_contents(resource_path('css/asata-ui.css'));
        $this->assertMatchesRegularExpression('~\.au-db-catatan-list \{[^}]*max-height: 16rem; overflow-y: auto~', $ui,
            'Daftar catatan harus tetap bisa digulir.');

        $css = file_get_contents(base_path('resources/css/app.css'));
        $this->assertMatchesRegularExpression('~\.no-scrollbar\s*\{[^}]*scrollbar-width:\s*none~', $css);
        $this->assertStringContainsString('.no-scrollbar::-webkit-scrollbar', $css);
    }

    public function test_tiap_catatan_punya_bagian_yang_bisa_dibentang(): void
    {
        $jsx = $this->jsx();

        $this->assertStringContainsString('className="au-db-note-head"', $jsx, 'Baris catatan tidak bisa diklik.');
        $this->assertStringContainsString('className="au-db-note-body"', $jsx, 'Tidak ada bagian isi yang dibentang.');
        $this->assertStringContainsString("aria-expanded={buka ? 'true' : 'false'}", $jsx);
        $this->assertStringContainsString('const [buka, setBuka] = useState(false);', $jsx,
            'Bagian yang dibentang harus tertutup dulu saat halaman dibuka.');
        $this->assertStringContainsString("style={{ maxHeight: 0, overflow: 'hidden', transition: 'max-height", $jsx,
            'Bagian isi harus mulai dari tinggi nol dan dianimasikan.');
    }

    public function test_isi_catatan_ikut_dikirim_ke_halaman(): void
    {
        $user = $this->user();
        $this->catatan($user, ['content' => 'Periksa oli sebelum dikirim']);

        $n = $this->dataCatatan($user)[0];
        $this->assertSame('Periksa oli sebelum dikirim', $n['content'],
            'Isi catatan harus ada di halaman, bukan diambil lagi lewat permintaan baru.');
        $this->assertStringContainsString('qc-isi-catatan', $this->jsx(),
            'Isi catatan harus tampil berformat (daftar, tebal, perataan).');
    }

    public function test_ada_tombol_dan_modal_untuk_isi_lengkap(): void
    {
        $jsx = $this->jsx();

        $this->assertStringContainsString('className="au-db-note-detail" onClick={onDetail}', $jsx, 'Tombol "Lihat detail" tidak ada.');
        $this->assertStringContainsString('function ModalCatatan(', $jsx, 'Modal detail catatan tidak ada.');
        $this->assertStringContainsString('Buka menu catatan', $jsx);
    }

    public function test_data_modal_memuat_keterangan_catatan(): void
    {
        $user   = $this->user();
        $tujuan = User::factory()->create(['role' => 'operator', 'name' => 'Budi']);
        $note   = $this->catatan($user, [
            'target_user_id' => $tujuan->id,
            'due_date'       => now()->addDays(3)->toDateString(),
            'content'        => 'Kirim laporan ke kantor',
        ]);

        $baris = collect($this->dataCatatan($user))->firstWhere('id', $note->id);

        $this->assertNotNull($baris, 'Catatan tidak ada di data modal.');
        $this->assertSame('Kirim laporan ke kantor', $baris['content']);
        $this->assertArrayHasKey('html', $baris, 'Bentuk siap tampil isi catatan tidak ikut dikirim.');
        $this->assertSame('Budi', $baris['to'], 'Tujuan catatan harus ikut ditampilkan.');
        $this->assertNotNull($baris['due'], 'Tenggat harus ikut ditampilkan.');
        $this->assertFalse($baris['done']);
    }

    public function test_isi_catatan_tidak_bisa_menutup_tag_script(): void
    {
        $user = $this->user();
        $this->catatan($user, ['content' => 'awas </script><script>alert(1)</script>']);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        // Props Inertia ditulis di atribut data-page (di-escape HTML), jadi tidak
        // ada "</script>" mentah yang bisa menutup tag apa pun.
        $this->assertStringNotContainsString('</script><script>alert(1)', $html,
            'Isi catatan bisa keluar dari blok data dan menjalankan skrip sendiri.');

        $data = $this->dataCatatan($user);
        $this->assertStringNotContainsString('alert(1)', $data[0]['html'],
            'Skrip di isi catatan lama tidak boleh ikut ditampilkan.');
        $this->assertStringContainsString('awas', $data[0]['content']);
    }
}
