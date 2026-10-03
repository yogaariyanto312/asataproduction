<?php

namespace Tests\Feature\Ref;

use App\Models\Changelog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Halaman "Tentang Aplikasi".
 *
 * Yang dijaga: versi aplikasi mengikuti Riwayat Update (dulu ditulis tangan
 * "Versi 1.0" dan tidak pernah berubah), dan daftar riwayatnya dibatasi 6
 * entri yang terlihat.
 */
class HalamanTentangTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'developer'): User
    {
        return User::create([
            'name'      => ucfirst($role) . ' Uji',
            'username'  => $role . 'tentang',
            'email'     => $role . '.tentang@uji.test',
            'role'      => $role,
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    private function entri(array $isi = []): Changelog
    {
        return Changelog::create(array_merge([
            'type'    => 'improvement',
            'version' => 'v1.0',
            'title'   => 'Entri uji',
        ], $isi));
    }

    /** asata: halaman Inertia — data dari props, tampilan dari About.jsx. */
    private function props(?User $user = null): array
    {
        return $this->actingAs($user ?? $this->user())
            ->get(route('about'))
            ->assertOk()
            ->viewData('page')['props'];
    }

    private function jsx(): string
    {
        return file_get_contents(resource_path('js/Pages/About.jsx'));
    }

    /* ───────────────── Versi aplikasi ───────────────── */

    public function test_versi_mengikuti_entri_riwayat_terbaru(): void
    {
        $this->entri(['version' => 'v1.0', 'title' => 'Rilis awal']);
        $this->entri(['version' => 'v1.7', 'title' => 'Perubahan terbaru']);

        $this->assertSame('v1.7', Changelog::versiAplikasi());
    }

    /** Kolom version boleh kosong — entri begitu dilewati, bukan menghapus versi. */
    public function test_entri_tanpa_versi_dilewati(): void
    {
        $this->entri(['version' => 'v2.3', 'title' => 'Ada versinya']);
        $this->entri(['version' => null,  'title' => 'Tanpa versi']);
        $this->entri(['version' => '',    'title' => 'Versi kosong']);

        $this->assertSame('v2.3', Changelog::versiAplikasi(),
            'entri tanpa versi tidak boleh membuat versi aplikasi ikut hilang');
    }

    /** Versi disimpan sebagai teks bebas: "1.4" dan "v1.4" sama-sama mungkin. */
    public function test_versi_tanpa_huruf_v_tetap_ditampilkan_rapi(): void
    {
        $this->entri(['version' => '3.2']);

        $this->assertSame('v3.2', Changelog::versiAplikasi());
    }

    public function test_versi_jatuh_ke_bawaan_kalau_riwayat_kosong(): void
    {
        $this->assertSame(Changelog::VERSI_AWAL, Changelog::versiAplikasi());
    }

    /**
     * Beberapa entri bisa lahir di detik yang sama (mis. saat mencatat beberapa
     * perubahan sekaligus), jadi id ikut menentukan mana yang paling baru.
     */
    public function test_urutan_memakai_id_saat_waktunya_sama(): void
    {
        $waktu = now()->subDay();

        $lama = $this->entri(['version' => 'v4.0']);
        $baru = $this->entri(['version' => 'v4.1']);

        Changelog::whereIn('id', [$lama->id, $baru->id])->update(['created_at' => $waktu]);

        $this->assertSame('v4.1', Changelog::versiAplikasi());
    }

    public function test_halaman_menampilkan_versi_dari_riwayat_bukan_angka_tetap(): void
    {
        $this->entri(['version' => 'v9.9', 'title' => 'Versi paling baru']);

        $this->assertSame('v9.9', $this->props()['versi']);
        $this->assertStringNotContainsString('Versi 1.0', $this->jsx(),
            'angka versi tidak boleh ditulis tangan lagi di halaman');

        // Dua tempat menampilkan versi: lencana di judul dan kotak "Versi App".
        $this->assertSame(2, substr_count($this->jsx(), 'data-versi-app'),
            'kedua tempat versi harus memakai sumber yang sama');
        $this->assertSame(2, substr_count($this->jsx(), '{versi}'));
    }

    public function test_halaman_tetap_terbuka_saat_riwayat_masih_kosong(): void
    {
        $props = $this->props();

        $this->assertSame(Changelog::VERSI_AWAL, $props['versi']);
        $this->assertCount(0, $props['changelogs']);
        $this->assertStringContainsString('Belum ada riwayat update.', $this->jsx());
    }

    /* ───────────────── Daftar riwayat ───────────────── */

    /**
     * Semua entri tetap dikirim ke layar — yang dibatasi hanya tingginya, supaya
     * sisanya bisa digulir. Kalau nanti diganti jadi memotong di server (limit),
     * entri lama akan hilang diam-diam dan test ini yang memberi tahu.
     */
    public function test_semua_entri_dikirim_lalu_dibatasi_lewat_gulir(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            $this->entri(['title' => "Entri nomor {$i}", 'version' => "v1.{$i}"]);
        }

        $judul = collect($this->props()['changelogs'])->pluck('title')->all();

        for ($i = 1; $i <= 9; $i++) {
            $this->assertContains("Entri nomor {$i}", $judul,
                "entri ke-{$i} harus tetap ada di halaman, hanya tersembunyi oleh gulir");
        }
        $this->assertCount(9, $judul);
        $this->assertStringContainsString('const TAMPIL = 6;', $this->jsx());
        $this->assertStringContainsString('<div data-entri=""', $this->jsx());
    }

    /** Batang gulirnya disembunyikan, tapi isinya tetap bisa digulir. */
    public function test_daftar_riwayat_bisa_digulir_dengan_batang_tersembunyi(): void
    {
        $this->assertStringContainsString('data-riwayat="" className="au-ab-riwayat no-scrollbar"', $this->jsx());
        $this->assertMatchesRegularExpression('/\.au-ab-riwayat \{[^}]*overflow-y: auto/',
            file_get_contents(resource_path('css/asata-ui.css')));
    }

    /* ───────────────── Hak akses ───────────────── */

    /** Form tambah & tombol hapus hanya untuk developer. */
    public function test_hanya_developer_yang_melihat_form_riwayat(): void
    {
        $this->entri();

        $this->assertTrue($this->props($this->user('developer'))['canManage']);

        $this->flushSession();
        $this->assertFalse($this->props($this->user('admin'))['canManage']);

        // Form & tombol hapus hanya dirender bila canManage.
        $this->assertStringContainsString('{canManage && form ? (', $this->jsx());
        $this->assertStringContainsString('id="changelog-form"', $this->jsx());
        $this->assertStringContainsString('{canManage ? (', $this->jsx());
    }

}
