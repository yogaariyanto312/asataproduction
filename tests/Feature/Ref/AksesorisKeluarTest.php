<?php

namespace Tests\Feature\Ref;

use App\Models\Accessory;
use App\Models\Category;
use App\Models\Product;
use App\Models\RoleMenuPermission;
use App\Models\User;
use App\Support\MenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu Aksesoris Keluar: simpan, ubah, hapus, saring, export, dan saran nomor urut.
 */
class AksesorisKeluarTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'operator'): User
    {
        MenuAccess::flush();

        return User::factory()->create(['role' => $role]);
    }

    private function produk(string $series, ?string $kva = '100'): Product
    {
        $kategori = Category::firstOrCreate(
            ['name' => 'Kategori Aksesoris'],
            ['is_active' => true, 'has_manual_serial' => false],
        );

        return Product::create([
            'category_id' => $kategori->id,
            'type'        => 'regular',
            'name'        => 'PRODUK ' . $series,
            'series'      => $series,
            'kva'         => $kva,
            'is_active'   => true,
        ]);
    }

    private function dataValid(array $ganti = []): array
    {
        return array_merge([
            'accessory_date' => today()->toDateString(),
            'name'           => 'BOX',
            'qty'            => 2,
            'unit'           => 'pcs',
        ], $ganti);
    }

    /** Nama entri yang benar-benar masuk daftar untuk filter tertentu. */
    private function namaHasil(User $user, array $filter): array
    {
        $rows = $this->actingAs($user)
            ->get(route('accessories.index', $filter))
            ->assertOk()
            ->viewData('page')['props']['rows']['data'];

        return array_column($rows, 'name');
    }

    // ── Simpan ────────────────────────────────────────────────────────────────

    public function test_operator_bisa_menyimpan_aksesoris(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('accessories.store'), $this->dataValid(['serial_number' => 'NO.015-016']))
            ->assertRedirect(route('accessories.index'))
            ->assertSessionHasNoErrors();

        $a = Accessory::firstOrFail();
        $this->assertSame('BOX', $a->name);
        $this->assertSame(2, $a->qty);
        $this->assertSame($user->id, $a->user_id);
        $this->assertSame($user->name, $a->operator_name, 'Nama penginput ikut dicatat.');
    }

    public function test_satuan_selalu_tersimpan_huruf_kecil(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('accessories.store'), $this->dataValid(['unit' => 'Unit']))
            ->assertSessionHasNoErrors();

        $this->assertSame('unit', Accessory::firstOrFail()->unit,
            'Ejaan "Unit" harus disamakan supaya daftar tidak menampilkan dua bentuk.');
    }

    public function test_jumlah_minimal_satu_dan_tanggal_wajib(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('accessories.store'), $this->dataValid(['qty' => 0]))
            ->assertSessionHasErrors('qty');

        $this->actingAs($user)
            ->post(route('accessories.store'), $this->dataValid(['accessory_date' => '']))
            ->assertSessionHasErrors('accessory_date');

        $this->assertSame(0, Accessory::count());
    }

    public function test_satuan_di_luar_pilihan_ditolak(): void
    {
        $this->actingAs($this->user())
            ->post(route('accessories.store'), $this->dataValid(['unit' => 'lusin']))
            ->assertSessionHasErrors('unit');
    }

    public function test_produk_yang_tidak_ada_ditolak(): void
    {
        $this->actingAs($this->user())
            ->post(route('accessories.store'), $this->dataValid(['product_id' => 999999]))
            ->assertSessionHasErrors('product_id');
    }

    // ── Ubah & hapus ──────────────────────────────────────────────────────────

    public function test_ubah_data_aksesoris(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('accessories.store'), $this->dataValid());
        $a = Accessory::firstOrFail();

        $this->actingAs($user)
            ->put(route('accessories.update', $a), $this->dataValid(['name' => 'TUTUP', 'qty' => 5]))
            ->assertRedirect(route('accessories.index'));

        $a->refresh();
        $this->assertSame('TUTUP', $a->name);
        $this->assertSame(5, $a->qty);
    }

    public function test_hapus_data_aksesoris(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('accessories.store'), $this->dataValid());
        $a = Accessory::firstOrFail();

        $this->actingAs($user)->delete(route('accessories.destroy', $a))->assertRedirect(route('accessories.index'));

        $this->assertSame(0, Accessory::count());
    }

    // ── Hak akses ─────────────────────────────────────────────────────────────

    public function test_role_tanpa_izin_tambah_ditolak(): void
    {
        RoleMenuPermission::create(['role' => 'operator', 'menu_key' => 'aksesoris.create', 'allowed' => false]);
        MenuAccess::flush();

        $this->actingAs($this->user())
            ->post(route('accessories.store'), $this->dataValid())
            ->assertForbidden();

        $this->assertSame(0, Accessory::count());
    }

    public function test_role_tanpa_izin_hapus_ditolak(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('accessories.store'), $this->dataValid());
        $a = Accessory::firstOrFail();

        RoleMenuPermission::create(['role' => 'operator', 'menu_key' => 'aksesoris.delete', 'allowed' => false]);
        MenuAccess::flush();

        $this->actingAs($user)->delete(route('accessories.destroy', $a))->assertForbidden();
        $this->assertSame(1, Accessory::count());
    }

    public function test_role_tanpa_izin_export_ditolak(): void
    {
        RoleMenuPermission::create(['role' => 'operator', 'menu_key' => 'aksesoris.export', 'allowed' => false]);
        MenuAccess::flush();

        $this->actingAs($this->user())->get(route('accessories.export'))->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('accessories.index'))->assertRedirect(route('login'));
    }

    // ── Filter & pencarian ────────────────────────────────────────────────────

    public function test_pencarian_menyaring_berdasarkan_nama_seri_dan_keterangan(): void
    {
        $user = $this->user('developer');
        $produk = $this->produk('26B0091000');

        Accessory::create($this->dataValid(['name' => 'BOX', 'product_id' => $produk->id]) + ['user_id' => $user->id]);
        Accessory::create($this->dataValid(['name' => 'TUTUP', 'keterangan' => 'titipan gudang']) + ['user_id' => $user->id]);

        $this->assertSame(['BOX'], $this->namaHasil($user, ['search' => 'BOX']));
        $this->assertSame(['BOX'], $this->namaHasil($user, ['search' => '26B0091000']), 'Cari lewat seri produk.');
        $this->assertSame(['TUTUP'], $this->namaHasil($user, ['search' => 'titipan']), 'Cari lewat keterangan.');
    }

    public function test_filter_bulan_dan_tahun(): void
    {
        $user = $this->user('developer');

        Accessory::create($this->dataValid(['name' => 'LAMA', 'accessory_date' => '2024-03-10']) + ['user_id' => $user->id]);
        Accessory::create($this->dataValid(['name' => 'BARU', 'accessory_date' => '2026-09-10']) + ['user_id' => $user->id]);

        $this->assertSame(['BARU'], $this->namaHasil($user, ['year' => 2026, 'month' => 9]));
    }

    public function test_pencarian_tidak_menembus_filter_tahun(): void
    {
        $user = $this->user('developer');

        Accessory::create($this->dataValid(['name' => 'BOX', 'accessory_date' => '2024-03-10']) + ['user_id' => $user->id]);
        Accessory::create($this->dataValid(['name' => 'BOX', 'accessory_date' => '2026-09-10']) + ['user_id' => $user->id]);

        $jumlah = Accessory::query()->search('BOX')->whereYear('accessory_date', 2026)->count();

        $this->assertSame(1, $jumlah, 'Pencarian harus tetap terkurung di dalam filter tahun.');
    }

    // ── Saran nomor urut ──────────────────────────────────────────────────────

    public function test_saran_nomor_urut_terpisah_per_seri(): void
    {
        $user = $this->user('developer');
        $a = $this->produk('2051');
        $b = $this->produk('2052');

        Accessory::create($this->dataValid(['name' => 'BOX', 'product_id' => $a->id, 'serial_number' => 'NO.014-015']) + ['user_id' => $user->id]);
        Accessory::create($this->dataValid(['name' => 'BOX', 'product_id' => $b->id, 'serial_number' => 'NO.003']) + ['user_id' => $user->id]);

        $data = $this->actingAs($user)->get(route('accessories.index'))->assertOk()->viewData('page')['props']['lastSerials'];

        $this->assertSame(15, $data['BOX|2051']['last_number'], 'Seri 2051 berhenti di 15.');
        $this->assertSame(3, $data['BOX|2052']['last_number'], 'Seri 2052 punya rentetan sendiri.');
    }

    public function test_saran_nomor_urut_memakai_entri_terbaru(): void
    {
        $user = $this->user('developer');
        $produk = $this->produk('2051');

        Accessory::create($this->dataValid(['name' => 'BOX', 'product_id' => $produk->id, 'serial_number' => 'NO.005', 'accessory_date' => '2026-09-01']) + ['user_id' => $user->id]);
        Accessory::create($this->dataValid(['name' => 'BOX', 'product_id' => $produk->id, 'serial_number' => 'NO.020', 'accessory_date' => '2026-09-15']) + ['user_id' => $user->id]);

        $data = $this->actingAs($user)->get(route('accessories.index'))->assertOk()->viewData('page')['props']['lastSerials'];

        $this->assertSame(20, $data['BOX|2051']['last_number'], 'Yang dipakai harus entri paling baru.');
    }

    public function test_query_daftar_tidak_bertambah_mengikuti_jumlah_data(): void
    {
        $user = $this->user('developer');
        $produk = $this->produk('2051');

        $isi = function (int $dari, int $sampai) use ($user, $produk) {
            for ($i = $dari; $i <= $sampai; $i++) {
                Accessory::create($this->dataValid([
                    'name' => 'BOX ' . $i, 'product_id' => $produk->id, 'serial_number' => 'NO.' . $i,
                ]) + ['user_id' => $user->id]);
            }
        };

        $hitungQuery = function () use ($user) {
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            $this->actingAs($user)->get(route('accessories.index'))->assertOk();
            $n = count(\DB::getQueryLog());
            \DB::disableQueryLog();

            return $n;
        };

        $isi(1, 5);
        $sedikit = $hitungQuery();

        $isi(6, 60);
        $banyak = $hitungQuery();

        fwrite(STDERR, "query: 5 entri -> {$sedikit}, 60 entri -> {$banyak}\n");

        $this->assertLessThanOrEqual($sedikit, $banyak,
            'Jumlah query tidak boleh ikut naik saat data bertambah; kalau naik berarti N+1.');
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function test_export_mengikuti_filter_yang_aktif(): void
    {
        $user = $this->user('developer');

        Accessory::create($this->dataValid(['name' => 'LAMA', 'accessory_date' => '2024-03-10']) + ['user_id' => $user->id]);
        Accessory::create($this->dataValid(['name' => 'BARU', 'accessory_date' => '2026-09-10']) + ['user_id' => $user->id]);

        $export = new \App\Exports\AccessoryExport(
            Accessory::query()->whereYear('accessory_date', 2026)->get()
        );

        $baris = $export->collection();
        $this->assertCount(1, $baris);
        $this->assertSame('BARU', $baris->first()['Aksesoris']);
    }

    public function test_kolom_export_tidak_memuat_kolom_mati(): void
    {
        $judul = (new \App\Exports\AccessoryExport(collect()))->headings();

        $this->assertNotContains('Penerima', $judul, 'Kolom tanpa isian di form seharusnya sudah dibuang.');
        $this->assertNotContains('Tujuan', $judul);
        $this->assertNotContains('Departemen', $judul);
        $this->assertSame(
            ['Tanggal', 'Aksesoris', 'Seri Terkait', 'KVA', 'No. Urut', 'Jumlah', 'Satuan', 'Keterangan', 'Diinput'],
            $judul
        );
    }

    public function test_unduhan_export_berhasil(): void
    {
        $user = $this->user('developer');
        Accessory::create($this->dataValid() + ['user_id' => $user->id]);

        $res = $this->actingAs($user)->get(route('accessories.export'));
        $res->assertOk();
        $this->assertStringContainsString('aksesoris-keluar', $res->headers->get('content-disposition'));
    }

    // ── Tampilan ──────────────────────────────────────────────────────────────

    public function test_daftar_punya_tampilan_kartu_untuk_layar_kecil(): void
    {
        // asata memakai Inertia: tabel & daftar kartu sama-sama dirender dari
        // prop `rows`, dan CSS menukar keduanya di bawah lebar lg (1024px).
        $user = $this->user('developer');
        Accessory::create($this->dataValid(['name' => 'BOX UJI']) + ['user_id' => $user->id]);

        $rows = $this->actingAs($user)->get(route('accessories.index'))->assertOk()
            ->viewData('page')['props']['rows']['data'];
        $this->assertSame('BOX UJI', $rows[0]['name']);

        $jsx = file_get_contents(resource_path('js/Pages/Accessories/Index.jsx'));
        $this->assertStringContainsString('au-aks-tabel', $jsx, 'Tabel layar lebar tidak ada.');
        $this->assertStringContainsString('au-aks-kartu-list', $jsx, 'Daftar kartu untuk HP tidak ada.');

        $css = file_get_contents(resource_path('css/asata-ui.css'));
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 1023px\)\s*\{\s*\.au-aks-tabel \{ display: none; \}\s*\.au-aks-kartu-list \{ display: flex; \}/',
            $css,
            'Di bawah lg tabel harus disembunyikan dan kartu ditampilkan.'
        );
    }
}
