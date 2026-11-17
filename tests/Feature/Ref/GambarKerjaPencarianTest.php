<?php

namespace Tests\Feature\Ref;

use App\Models\GambarKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pencarian di menu Gambar Kerja.
 *
 * Hasil disaring sambil mengetik (tanpa menekan Enter), sama seperti Riwayat
 * Produksi. Sisi layar menukar isi #gk-list-area dari hasil permintaan yang
 * sama, jadi penyaringannya tetap dikerjakan server.
 */
class GambarKerjaPencarianTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'developer']);
    }

    private function gambar(string $judul, ?string $seri, ?string $kva, int $tahun = 2026): GambarKerja
    {
        return GambarKerja::create([
            'judul'       => $judul,
            'seri'        => $seri,
            'kva'         => $kva,
            'tahun'       => $tahun,
            'file_path'   => 'gambar-kerja/' . md5($judul . $seri) . '.pdf',
            'file_type'   => 'pdf',
            'uploaded_by' => User::first()?->id ?? $this->user()->id,
            'urutan'      => 1,
        ]);
    }

    /** Judul grup yang lolos saringan. */
    private function hasil(User $user, array $filter): array
    {
        return collect($this->actingAs($user)
            ->get(route('gambar-kerja.index', $filter))
            ->assertOk()
            ->viewData('page')['props']['years'])
            ->flatMap(fn ($y) => collect($y['groups'])->pluck('judul'))
            ->all();
    }

    public function test_halaman_siap_untuk_pencarian_langsung(): void
    {
        // asata: kotak cari memicu reload parsial Inertia (hanya prop `years`)
        // setelah jeda mengetik — padanan data-live-search referensi.
        $this->actingAs($this->user())->get(route('gambar-kerja.index'))->assertOk();

        $jsx = file_get_contents(resource_path('js/Pages/GambarKerja/Index.jsx'));
        $this->assertStringContainsString("setTimeout(() => muat(q, urutRef.current), 350)", $jsx, 'Belum disaring sambil mengetik.');
        $this->assertStringContainsString("only: ['years', 'search', 'sort']", $jsx, 'Yang diambil ulang cukup bagian daftar.');
    }

    public function test_cari_berdasarkan_judul(): void
    {
        $user = $this->user();
        $this->gambar('COVER TANGKI', '26B0091000', '50');
        $this->gambar('CHANNEL BESAR', '26C0251001', '100');

        $this->assertSame(['COVER TANGKI'], $this->hasil($user, ['search' => 'COVER']));
    }

    public function test_cari_berdasarkan_seri(): void
    {
        $user = $this->user();
        $this->gambar('COVER TANGKI', '26B0091000', '50');
        $this->gambar('CHANNEL BESAR', '26C0251001', '100');

        $this->assertSame(['CHANNEL BESAR'], $this->hasil($user, ['search' => '26C0251001']));
        $this->assertSame(['CHANNEL BESAR'], $this->hasil($user, ['search' => '0251']), 'Potongan seri juga harus ketemu.');
    }

    public function test_cari_berdasarkan_kva(): void
    {
        $user = $this->user();

        // KVA pembeda sengaja dipilih yang tidak muncul di dalam seri mana pun:
        // pencarian memakai pencocokan sebagian, jadi angka seperti "100" wajar
        // ikut menjaring seri 26B0091000.
        $this->gambar('COVER TANGKI', '26B0091000', '50');
        $this->gambar('CHANNEL BESAR', '26C0257001', '630');

        $this->assertSame(['CHANNEL BESAR'], $this->hasil($user, ['search' => '630']));
    }

    public function test_pencarian_angka_menjaring_seri_maupun_kva(): void
    {
        $user = $this->user();
        $this->gambar('COVER TANGKI', '26B0091000', '50');

        // "100" ada di dalam seri 26B0091000 — memang seharusnya ikut ketemu.
        $this->assertSame(['COVER TANGKI'], $this->hasil($user, ['search' => '100']));
    }

    public function test_pencarian_tanpa_hasil_mengosongkan_daftar(): void
    {
        $user = $this->user();
        $this->gambar('COVER TANGKI', '26B0091000', '50');

        $this->assertSame([], $this->hasil($user, ['search' => 'tidak-ada-ini']));
    }

    public function test_pencarian_tidak_mengganggu_urutan_yang_dipilih(): void
    {
        $user = $this->user();
        $this->gambar('COVER A', '26B0002000', '250');
        $this->gambar('COVER B', '26B0001000', '100');

        // Diurut KVA terkecil, keduanya cocok dengan kata "COVER".
        $urut = $this->hasil($user, ['search' => 'COVER', 'sort' => 'kva']);

        $this->assertSame(['COVER B', 'COVER A'], $urut, 'Urutan pilihan user harus tetap dipakai saat mencari.');
    }

    public function test_bagian_daftar_ikut_terkirim_pada_permintaan_ajax(): void
    {
        $user = $this->user();
        $this->gambar('COVER TANGKI', '26B0091000', '50');
        $this->gambar('CHANNEL BESAR', '26C0251001', '100');

        $hal = $this->actingAs($user)->get(route('gambar-kerja.index'))->viewData('page');

        // Reload parsial Inertia: hanya prop daftar yang dikirim ulang.
        $res = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true', 'X-Inertia-Version' => $hal['version'],
                'X-Inertia-Partial-Component' => 'GambarKerja/Index',
                'X-Inertia-Partial-Data' => 'years,search,sort',
            ])
            ->get(route('gambar-kerja.index', ['search' => 'COVER']))
            ->assertOk()->json();

        $this->assertArrayHasKey('years', $res['props']);
        $this->assertArrayNotHasKey('pollUrl', $res['props'], 'Yang dikirim ulang cukup bagian daftar.');
        $judul = collect($res['props']['years'])->flatMap(fn ($y) => collect($y['groups'])->pluck('judul'))->all();
        $this->assertSame(['COVER TANGKI'], $judul, 'Hasil yang tidak cocok tidak boleh ikut di bagian daftar.');
    }

    public function test_butuh_login(): void
    {
        $this->get(route('gambar-kerja.index', ['search' => 'apa']))->assertRedirect(route('login'));
        $this->getJson(route('api.gambar-kerja.poll'))->assertUnauthorized();
    }

    // ── Penyegaran otomatis ──────────────────────────────────────────────────

    public function test_penanda_perubahan_ikut_berubah_saat_ada_unggahan_baru(): void
    {
        $user = $this->user();
        $this->gambar('COVER TANGKI', '26B0091000', '50');

        $awal = $this->actingAs($user)->getJson(route('api.gambar-kerja.poll'))->assertOk()->json();
        $this->assertSame(1, $awal['count']);

        // Orang lain (mis. developer) mengunggah gambar baru.
        $this->gambar('CHANNEL BARU', '26C0251001', '100');

        $sesudah = $this->actingAs($user)->getJson(route('api.gambar-kerja.poll'))->assertOk()->json();

        $this->assertSame(2, $sesudah['count'], 'Jumlah harus ikut naik supaya layar tahu ada yang baru.');
        $this->assertNotSame($awal, $sesudah);
    }

    public function test_penanda_perubahan_tetap_sama_kalau_tidak_ada_yang_berubah(): void
    {
        $user = $this->user();
        $this->gambar('COVER TANGKI', '26B0091000', '50');

        $a = $this->actingAs($user)->getJson(route('api.gambar-kerja.poll'))->assertOk()->json();
        $b = $this->actingAs($user)->getJson(route('api.gambar-kerja.poll'))->assertOk()->json();

        $this->assertSame($a, $b, 'Tanpa perubahan, layar tidak boleh ikut menyegar.');
    }

    public function test_penanda_ikut_berubah_saat_gambar_dihapus(): void
    {
        $user = $this->user();
        $this->gambar('COVER TANGKI', '26B0091000', '50');
        $kedua = $this->gambar('CHANNEL BARU', '26C0251001', '100');

        $awal = $this->actingAs($user)->getJson(route('api.gambar-kerja.poll'))->assertOk()->json();
        $kedua->delete();
        $sesudah = $this->actingAs($user)->getJson(route('api.gambar-kerja.poll'))->assertOk()->json();

        $this->assertSame(1, $sesudah['count']);
        $this->assertNotSame($awal['count'], $sesudah['count']);
    }

    public function test_halaman_menyebutkan_alamat_pemantau(): void
    {
        $props = $this->actingAs($this->user())->get(route('gambar-kerja.index'))->assertOk()->viewData('page')['props'];

        $this->assertSame(route('api.gambar-kerja.poll'), $props['pollUrl'],
            'Halaman harus memberi tahu layar ke mana harus memantau perubahan.');
    }
}
