<?php

namespace Tests\Feature\Ref;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Reject di Riwayat Produksi (permintaan Yoga 2026-09-30):
 *
 * 1. Keterangan reject (kategori + catatan) tampil di baris Riwayat — dulu
 *    hanya "✕ 1" tanpa penjelasan.
 * 2. Total 0 = tidak ada nomor urut. Dulu nomor lama menempel ("NO.001-001"
 *    pada entri 0 unit) karena form edit tidak pernah menghapusnya.
 * 3. Reject unit dari produksi sebelumnya: entri lama berkurang, nomor paling
 *    akhirnya dilepas (bisa dipakai unit pengganti), reject dicatat di hari
 *    ditemukan.
 * 4. Reject ikut digabung saat input masuk ke entri produk+tanggal yang sama.
 */
class ProduksiRejectTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    protected function setUp(): void
    {
        parent::setUp();
        $this->op = User::create([
            'name' => 'Agoy', 'username' => 'agoy', 'email' => 'agoy@uji.test',
            'role' => 'operator', 'is_active' => true, 'password' => Hash::make('rahasia123'),
        ]);
    }

    private function produk(string $type = 'regular', string $seri = '26Q0432051'): Product
    {
        $kat = Category::firstOrCreate(['name' => $type === 'channel' ? 'Channel Uji' : 'Tangki Uji'],
            ['is_active' => true, 'has_manual_serial' => false]);

        return Product::create([
            'category_id' => $kat->id, 'type' => $type, 'name' => $type === 'channel' ? 'CHANNEL' : 'TANGKI',
            'series' => $seri, 'kva' => '2300', 'is_active' => true,
        ]);
    }

    private function log(Product $p, string $tgl, float $total, ?string $nomor, array $lain = []): ProductionLog
    {
        return ProductionLog::create(array_merge([
            'product_id' => $p->id, 'user_id' => $this->op->id, 'operator_name' => 'Agoy',
            'production_date' => $tgl, 'total_qty' => $total, 'up_qty' => 0, 'bt_qty' => 0,
            'notes' => $nomor, 'reject_qty' => 0,
        ], $lain));
    }

    private function rejectUnit(ProductionLog $log, array $isi = [])
    {
        return $this->actingAs($this->op)->post(route('production.reject-unit', $log), array_merge([
            'jumlah' => 1, 'tanggal' => today()->toDateString(),
            'reject_category' => 'desain', 'reject_notes' => 'Karoseri menggembung',
        ], $isi));
    }

    /* ── 1. Keterangan reject tampil ─────────────────────────────────────── */

    public function test_keterangan_reject_tampil_di_riwayat(): void
    {
        $this->log($this->produk(), today()->toDateString(), 0, null, [
            'reject_qty' => 1, 'reject_category' => 'desain',
            'reject_notes' => 'Pada bagian karoseri gendut sebelah tolong di repair',
        ]);

        // asata: detail reject dikirim lewat props baris riwayat.
        $item = $this->actingAs($this->op)->get(route('production.index'))->assertOk()
            ->viewData('page')['props']['days'][0]['categories'][0]['items'][0];

        $this->assertSame('Desain / Spesifikasi', $item['rejectLabel']);
        $this->assertSame('Pada bagian karoseri gendut sebelah tolong di repair', $item['rejectNotes']);
    }

    public function test_baris_tanpa_reject_tidak_menampilkan_detail(): void
    {
        $this->log($this->produk(), today()->toDateString(), 3, 'NO.001-003');

        $this->actingAs($this->op)->get(route('production.index'))->assertDontSee('data-detail-reject', false);
    }

    /* ── 2. Total 0 = tanpa nomor urut ───────────────────────────────────── */

    public function test_edit_total_jadi_nol_mengosongkan_nomor_urut(): void
    {
        $p   = $this->produk();
        $log = $this->log($p, today()->toDateString(), 1, 'NO.001-001');

        $this->actingAs($this->op)->put(route('production.update', $log), [
            'product_id' => $p->id, 'production_date' => today()->toDateString(),
            'total_qty' => 0, 'notes' => 'NO.001-001', 'reject_qty' => 1,
        ])->assertRedirect();

        $this->assertNull($log->fresh()->notes);
    }

    public function test_edit_dengan_total_tetap_mempertahankan_nomor(): void
    {
        $p   = $this->produk();
        $log = $this->log($p, today()->toDateString(), 2, 'NO.010-011');

        $this->actingAs($this->op)->put(route('production.update', $log), [
            'product_id' => $p->id, 'production_date' => today()->toDateString(),
            'total_qty' => 2, 'notes' => 'NO.010-011',
        ])->assertRedirect();

        $this->assertSame('NO.010-011', $log->fresh()->notes);
    }

    public function test_input_baru_total_nol_tidak_menyimpan_nomor(): void
    {
        $p = $this->produk();

        $this->actingAs($this->op)->post(route('production.store'), [
            'product_id' => $p->id, 'production_date' => today()->toDateString(),
            'total_qty' => 0, 'notes' => 'NO.005-005', 'reject_qty' => 1,
        ]);

        $this->assertNull(ProductionLog::first()->notes);
    }

    public function test_channel_sisi_nol_dibuang_dari_nomor(): void
    {
        $p   = $this->produk('channel', '26C0999');
        $log = $this->log($p, today()->toDateString(), 2, "UP NO.001-002\nBT NO.001-002", ['up_qty' => 2, 'bt_qty' => 2]);

        $this->actingAs($this->op)->put(route('production.update', $log), [
            'product_id' => $p->id, 'production_date' => today()->toDateString(),
            'up_qty' => 2, 'bt_qty' => 0, 'total_qty' => 1, 'notes' => "UP NO.001-002\nBT NO.001-002",
        ])->assertRedirect();

        $this->assertSame('UP NO.001-002', $log->fresh()->notes);
    }

    /* ── 3. Reject unit dari hari sebelumnya ─────────────────────────────── */

    /** Kasus Yoga: seri 2051 kemarin 1 unit NO.025, hari ini ketahuan cacat. */
    public function test_reject_unit_kemarin_dicatat_hari_ini(): void
    {
        $p       = $this->produk();
        $kemarin = today()->subDay()->toDateString();
        $asal    = $this->log($p, $kemarin, 1, 'NO.025-025');

        $this->rejectUnit($asal)->assertRedirect(route('production.index'))->assertSessionHas('success');

        $asal->refresh();
        $this->assertEquals(0, $asal->total_qty, 'entri kemarin berkurang');
        $this->assertNull($asal->notes, 'nomor 25 dilepas');
        $this->assertSame(0, (int) $asal->reject_qty, 'reject tidak dicatat di hari produksi');

        $hariIni = ProductionLog::where('product_id', $p->id)->whereDate('production_date', today())->first();
        $this->assertNotNull($hariIni, 'entri reject muncul di hari ditemukan');
        $this->assertEquals(0, $hariIni->total_qty);
        $this->assertSame(1, (int) $hariIni->reject_qty);
        $this->assertSame('desain', $hariIni->reject_category);
        $this->assertSame('Karoseri menggembung', $hariIni->reject_notes);
        $this->assertStringContainsString('NO.025', $hariIni->keterangan);
        $this->assertStringContainsString(today()->subDay()->format('d/m/Y'), $hariIni->keterangan);
    }

    public function test_hanya_nomor_paling_akhir_yang_dilepas(): void
    {
        $asal = $this->log($this->produk(), today()->subDays(2)->toDateString(), 5, 'NO.023-027');

        $this->rejectUnit($asal, ['jumlah' => 2]);

        $asal->refresh();
        $this->assertEquals(3, $asal->total_qty);
        $this->assertSame('NO.023-025', $asal->notes);
        $this->assertStringContainsString('NO.026-027', ProductionLog::whereDate('production_date', today())->first()->keterangan);
    }

    /** Catatan hasil gabung (beberapa baris): dikurangi dari baris terakhir dulu. */
    public function test_nomor_dilepas_melintasi_beberapa_baris(): void
    {
        $asal = $this->log($this->produk(), today()->subDay()->toDateString(), 4, "NO.001-002\nNO.010-011");

        $this->rejectUnit($asal, ['jumlah' => 3]);

        $this->assertSame('NO.001-001', $asal->fresh()->notes);
        $this->assertStringContainsString('NO.002, 010-011', ProductionLog::whereDate('production_date', today())->first()->keterangan);
    }

    /** Hari ini sudah ada entri produk yang sama → reject digabung, bukan baris baru. */
    public function test_reject_digabung_ke_entri_hari_ini_yang_sudah_ada(): void
    {
        $p    = $this->produk();
        $asal = $this->log($p, today()->subDay()->toDateString(), 2, 'NO.030-031');
        $ini  = $this->log($p, today()->toDateString(), 3, 'NO.032-034');

        $this->rejectUnit($asal);

        $this->assertSame(2, ProductionLog::count(), 'tidak ada baris ketiga');
        $ini->refresh();
        $this->assertEquals(3, $ini->total_qty, 'produksi hari ini tidak berubah');
        $this->assertSame(1, (int) $ini->reject_qty);
        $this->assertSame('NO.032-034', $ini->notes);
    }

    public function test_ditemukan_di_hari_yang_sama_cukup_satu_entri(): void
    {
        $asal = $this->log($this->produk(), today()->toDateString(), 2, 'NO.001-002');

        $this->rejectUnit($asal);

        $this->assertSame(1, ProductionLog::count());
        $asal->refresh();
        $this->assertEquals(1, $asal->total_qty);
        $this->assertSame(1, (int) $asal->reject_qty);
        $this->assertSame('NO.001-001', $asal->notes);
    }

    /** Nomor yang dilepas kembali disarankan untuk input berikutnya (unit pengganti). */
    public function test_nomor_yang_dilepas_disarankan_lagi(): void
    {
        $p    = $this->produk();
        $asal = $this->log($p, today()->subDay()->toDateString(), 1, 'NO.025-025');
        $this->log($p, today()->subDays(3)->toDateString(), 2, 'NO.023-024');

        $this->rejectUnit($asal);

        $saran = $this->actingAs($this->op)->getJson(route('api.production.last-serial', ['product_id' => $p->id]))->json('notes');
        $this->assertSame('NO.023-024', $saran, 'nomor terakhir yang terpakai kini 24, jadi berikutnya 25 lagi');
    }

    public function test_jumlah_melebihi_isi_entri_ditolak(): void
    {
        $asal = $this->log($this->produk(), today()->subDay()->toDateString(), 1, 'NO.025-025');

        $this->rejectUnit($asal, ['jumlah' => 2])->assertSessionHasErrors('jumlah');
        $this->assertEquals(1, $asal->fresh()->total_qty);
    }

    public function test_tanggal_sebelum_produksi_ditolak(): void
    {
        $asal = $this->log($this->produk(), today()->toDateString(), 1, 'NO.025-025');

        $this->rejectUnit($asal, ['tanggal' => today()->subDay()->toDateString()])->assertSessionHasErrors('tanggal');
    }

    public function test_channel_ditolak(): void
    {
        $asal = $this->log($this->produk('channel', '26C0888'), today()->subDay()->toDateString(), 2, null, ['up_qty' => 2, 'bt_qty' => 2]);

        $this->rejectUnit($asal)->assertStatus(422);
    }

    public function test_tombol_reject_unit_tampil_sesuai_izin(): void
    {
        $this->log($this->produk(), today()->subDay()->toDateString(), 1, 'NO.025-025');

        $props = $this->actingAs($this->op)->get(route('production.index'))->viewData('page')['props'];
        $this->assertTrue($props['can']['reject']);
        $this->assertTrue($props['days'][0]['categories'][0]['items'][0]['canReject']);

        $visitor = User::create([
            'name' => 'Tamu', 'username' => 'tamu', 'email' => 'tamu@uji.test',
            'role' => 'visitor', 'is_active' => true, 'password' => Hash::make('rahasia123'),
        ]);
        $this->flushSession();
        $props = $this->actingAs($visitor)->get(route('production.index'))->viewData('page')['props'];
        $this->assertFalse($props['can']['reject'], 'visitor tidak boleh melihat tombol reject unit');

        $this->actingAs($visitor)->post(route('production.reject-unit', ProductionLog::first()), ['jumlah' => 1, 'tanggal' => today()->toDateString()])
            ->assertForbidden();
    }

    public function test_tombol_tidak_tampil_untuk_entri_nol_unit_dan_channel(): void
    {
        $this->log($this->produk(), today()->toDateString(), 0, null, ['reject_qty' => 1]);
        $this->log($this->produk('channel', '26C0777'), today()->toDateString(), 2, null, ['up_qty' => 2, 'bt_qty' => 2]);

        $this->actingAs($this->op)->get(route('production.index'))
            ->assertOk()
            ->assertDontSee('<button type="button" data-reject-unit', false);
    }

    /* ── 4. Reject ikut digabung saat input ─────────────────────────────── */

    public function test_input_reject_ke_entri_yang_sudah_ada_tidak_hilang(): void
    {
        $p   = $this->produk();
        $log = $this->log($p, today()->toDateString(), 3, 'NO.001-003');

        $this->actingAs($this->op)->post(route('production.store'), [
            'product_id' => $p->id, 'production_date' => today()->toDateString(),
            'total_qty' => 0, 'reject_qty' => 2, 'reject_category' => 'mesin', 'reject_notes' => 'Las bocor',
        ]);

        $log->refresh();
        $this->assertSame(1, ProductionLog::count());
        $this->assertSame(2, (int) $log->reject_qty);
        $this->assertSame('mesin', $log->reject_category);
        $this->assertSame('Las bocor', $log->reject_notes);
    }
}
