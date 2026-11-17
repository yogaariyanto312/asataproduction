<?php

namespace Tests\Feature\Ref;

use App\Exports\ProductionReportExport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Menu Laporan: layar, ekspor PDF, dan ekspor Excel.
 *
 * Yang dijaga terutama satu hal: ketiganya harus menampilkan data yang sama
 * dengan urutan yang sama — urutan Riwayat Produksi (kategori Channel, Cover,
 * Tangki, lalu sisanya; di dalamnya KVA terkecil dulu, seri jadi penentu).
 * Dulu layarnya satu urutan, PDF & Excel urutan lain, dan Excel bahkan
 * kehilangan kolom nomor urutnya.
 */
class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name'      => 'Admin Laporan',
            'username'  => 'adminlaporan',
            'email'     => 'adminlaporan@uji.test',
            'role'      => 'admin',
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    private function produk(string $kategori, string $nama, string $seri, string $kva): Product
    {
        $cat = Category::firstOrCreate(
            ['name' => $kategori],
            ['is_active' => true, 'has_manual_serial' => false],
        );

        return Product::create([
            'category_id' => $cat->id,
            'type'        => 'regular',
            'name'        => $nama,
            'series'      => $seri,
            'kva'         => $kva,
            'unit'        => 'unit',
            'is_active'   => true,
        ]);
    }

    private function log(Product $produk, string $tanggal, int $up = 1, int $bt = 0, ?string $notes = null): ProductionLog
    {
        return ProductionLog::create([
            'product_id'      => $produk->id,
            'user_id'         => User::first()->id,
            'operator_name'   => 'Operator',
            'production_date' => $tanggal,
            'up_qty'      => $up,
            'bt_qty'      => $bt,
            'total_qty'       => $up + $bt,
            'reject_qty'      => 0,
            'notes'           => $notes,
        ]);
    }

    /** Tiga kategori, KVA sengaja tidak urut & jumlahnya sengaja terbalik. */
    private function dataContoh(string $tanggal): array
    {
        $tangki  = $this->produk('Tangki PLN',  'TANGKI 50',   'T-50',  '50');
        $cover   = $this->produk('Cover PLN',   'COVER 200',   'C-200', '200');
        $cover2  = $this->produk('Cover PLN',   'COVER 100',   'C-100', '100');
        $channel = $this->produk('Channel PLN', 'CHANNEL 160', 'H-160', '160');

        // Tangki dibuat paling banyak: kalau urutannya masih "terbanyak di
        // atas", dia yang akan muncul duluan.
        $this->log($tangki,  $tanggal, 50, 0, 'NO.001-050');
        $this->log($cover,   $tanggal, 5,  0, 'NO.100-104');
        $this->log($cover2,  $tanggal, 3,  0, 'NO.200-202');
        $this->log($channel, $tanggal, 1,  0, 'NO.900-900');

        return compact('tangki', 'cover', 'cover2', 'channel');
    }

    /** Urutan kemunculan beberapa teks di dalam satu halaman. */
    private function urutanDi(string $html, array $potongan): array
    {
        $posisi = [];
        foreach ($potongan as $teks) {
            $p = strpos($html, $teks);
            $this->assertNotFalse($p, "Tidak ketemu di halaman: {$teks}");
            $posisi[$teks] = $p;
        }

        asort($posisi);

        return array_keys($posisi);
    }

    /* ── Layar ──────────────────────────────────────────────────────────── */

    public function test_halaman_laporan_terbuka(): void
    {
        $this->actingAs($this->admin())->get(route('reports.index'))->assertOk();
    }

    public function test_tamu_tidak_bisa_membuka_laporan(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->get(route('reports.export-excel'))->assertRedirect(route('login'));
        $this->get(route('reports.export-pdf'))->assertRedirect(route('login'));
    }

    public function test_kategori_urut_channel_cover_tangki(): void
    {
        $admin = $this->admin();
        $this->dataContoh(now()->startOfMonth()->toDateString());

        $html = $this->actingAs($admin)->get(route('reports.index'))->assertOk()->getContent();

        // Nama kategorinya juga muncul di ringkasan atas halaman, jadi yang
        // dipakai menandai urutan adalah seri produknya.
        $this->assertSame(
            ['H-160', 'C-100', 'C-200', 'T-50'],
            $this->urutanDi($html, ['T-50', 'C-200', 'C-100', 'H-160']),
            'Kartu kategori harus urut seperti di Riwayat Produksi.'
        );
    }

    public function test_di_dalam_kategori_kva_terkecil_dulu(): void
    {
        $admin = $this->admin();
        $this->dataContoh(now()->startOfMonth()->toDateString());

        $html = $this->actingAs($admin)->get(route('reports.index'))->assertOk()->getContent();

        $this->assertSame(
            ['C-100', 'C-200'],
            $this->urutanDi($html, ['C-200', 'C-100']),
            'KVA lebih kecil harus di atas, bukan yang jumlahnya terbanyak.'
        );
    }

    public function test_seri_jadi_penentu_saat_kva_sama(): void
    {
        $admin = $this->admin();
        $tanggal = now()->startOfMonth()->toDateString();

        $b = $this->produk('Cover PLN', 'COVER B', 'C-BBB', '100');
        $a = $this->produk('Cover PLN', 'COVER A', 'C-AAA', '100');

        $this->log($b, $tanggal, 9);   // dibuat duluan & jumlahnya lebih banyak
        $this->log($a, $tanggal, 1);

        $html = $this->actingAs($admin)->get(route('reports.index'))->assertOk()->getContent();

        $this->assertSame(['C-AAA', 'C-BBB'], $this->urutanDi($html, ['C-BBB', 'C-AAA']));
    }

    public function test_hanya_bulan_yang_diminta_yang_dihitung(): void
    {
        $admin = $this->admin();
        $bulan = now()->startOfMonth();

        $p = $this->produk('Cover PLN', 'COVER X', 'C-X', '100');

        $this->log($p, $bulan->copy()->subDay()->toDateString(), 7);       // bulan lalu
        $this->log($p, $bulan->copy()->endOfMonth()->toDateString(), 3);   // hari terakhir bulan ini
        $this->log($p, $bulan->copy()->addMonth()->toDateString(), 5);     // bulan depan

        $rekap = \App\Http\Controllers\ReportController::rekapBulanan((int) $bulan->month, (int) $bulan->year);

        $this->assertCount(1, $rekap);
        $this->assertSame(3, (int) $rekap->first()->grand_total,
            'Tanggal di luar bulannya ikut terhitung.');
    }

    public function test_nomor_urut_merangkum_sebulan_penuh(): void
    {
        $admin = $this->admin();
        $bulan = now()->startOfMonth();

        $p = $this->produk('Cover PLN', 'COVER Y', 'C-Y', '100');

        // Bulan lalu — tidak boleh ikut terhitung.
        $this->log($p, $bulan->copy()->subDay()->toDateString(), 1, 0, 'NO.900-910');

        $this->log($p, $bulan->copy()->toDateString(), 10, 0, 'NO.001-010');
        $this->log($p, $bulan->copy()->addDays(5)->toDateString(), 15, 0, 'NO.011-025');
        $this->log($p, $bulan->copy()->addDays(9)->toDateString(), 15, 0, 'NO.026-040');

        $rekap = \App\Http\Controllers\ReportController::rekapBulanan((int) $bulan->month, (int) $bulan->year);

        $this->assertSame('NO.001-040', $rekap->first()->nomor_urut,
            'Nomor urut harus dari awal bulan sampai catatan terakhir, bukan catatan terakhir saja.');
    }

    public function test_nomor_urut_nyambung_dengan_jumlah_unitnya(): void
    {
        $admin = $this->admin();
        $bulan = now()->startOfMonth();

        $p = $this->produk('Tangki PLN', 'TANGKI Z', 'T-Z', '100');

        $this->log($p, $bulan->copy()->toDateString(), 10, 0, 'NO.001-010');
        $this->log($p, $bulan->copy()->addDay()->toDateString(), 12, 0, 'NO.011-022');

        $baris = \App\Http\Controllers\ReportController::rekapBulanan((int) $bulan->month, (int) $bulan->year)->first();

        // Inilah yang dulu terlihat janggal: rentangnya satu hari, totalnya sebulan.
        preg_match('/NO\.(\d+)-(\d+)/', $baris->nomor_urut, $cocok);
        $banyakNomor = (int) $cocok[2] - (int) $cocok[1] + 1;

        $this->assertSame(22, (int) $baris->total_up);
        $this->assertSame(22, $banyakNomor,
            'Banyaknya nomor urut harus sejalan dengan jumlah unit sebulan.');
    }

    public function test_nomor_urut_up_dan_bt_dipisah(): void
    {
        $admin = $this->admin();
        $bulan = now()->startOfMonth();

        $p = $this->produk('Channel PLN', 'CHANNEL N', 'H-N', '100');

        $this->log($p, $bulan->copy()->toDateString(), 6, 6, "UP NO.863-868
BT NO.899-904");
        $this->log($p, $bulan->copy()->addDay()->toDateString(), 3, 13, "UP NO.893-895
BT NO.915-927");

        $rekap = \App\Http\Controllers\ReportController::rekapBulanan((int) $bulan->month, (int) $bulan->year);

        $this->assertSame("UP NO.863-895
BT NO.899-927", $rekap->first()->nomor_urut);
    }

    public function test_bulan_di_luar_akal_tidak_menggeser_periode(): void
    {
        $admin = $this->admin();

        // month=99 dulu membuat Carbon melompat ke tahun berikutnya.
        $html = $this->actingAs($admin)
            ->get(route('reports.index', ['month' => 99, 'year' => 2026]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('2026', $html);
        $this->assertStringNotContainsString('2027', $html);
    }

    /* ── Ekspor PDF ─────────────────────────────────────────────────────── */

    public function test_pdf_terunduh_sebagai_berkas_pdf(): void
    {
        $admin = $this->admin();
        $this->dataContoh(now()->startOfMonth()->toDateString());

        $res = $this->actingAs($admin)->get(route('reports.export-pdf'));

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringContainsString('laporan-produksi-', (string) $res->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_isi_pdf_urut_sama_dengan_layar(): void
    {
        $admin = $this->admin();
        $bulan = now()->startOfMonth();
        $this->dataContoh($bulan->toDateString());

        $report = \App\Http\Controllers\ReportController::rekapBulanan((int) $bulan->month, (int) $bulan->year);

        $html = view('reports.pdf', [
            'report'    => $report,
            'month'     => (int) $bulan->month,
            'year'      => (int) $bulan->year,
            'monthName' => $bulan->translatedFormat('F'),
        ])->render();

        $this->assertSame(
            ['H-160', 'C-100', 'C-200', 'T-50'],
            $this->urutanDi($html, ['T-50', 'C-200', 'C-100', 'H-160']),
            'Urutan baris PDF harus sama dengan layarnya.'
        );
    }

    public function test_pdf_memberi_pembatas_kategori_dan_nomor_urut(): void
    {
        $bulan = now()->startOfMonth();
        $this->admin();
        $this->dataContoh($bulan->toDateString());

        $html = view('reports.pdf', [
            'report'    => \App\Http\Controllers\ReportController::rekapBulanan((int) $bulan->month, (int) $bulan->year),
            'month'     => (int) $bulan->month,
            'year'      => (int) $bulan->year,
            'monthName' => $bulan->translatedFormat('F'),
        ])->render();

        $this->assertStringContainsString('class="kelompok"', $html, 'Baris pembatas kategori tidak ada.');
        $this->assertStringContainsString('NO.900-900', $html, 'Kolom nomor urut kosong.');
    }

    /* ── Ekspor Excel ───────────────────────────────────────────────────── */

    public function test_excel_terunduh(): void
    {
        Excel::fake();

        $admin = $this->admin();
        $this->dataContoh(now()->startOfMonth()->toDateString());

        $this->actingAs($admin)->get(route('reports.export-excel'))->assertOk();

        Excel::assertDownloaded('laporan-produksi-' . now()->translatedFormat('F') . '-' . now()->year . '.xlsx');
    }

    /** Semua nilai sel jadi satu larik datar, untuk memeriksa urutan. */
    private function selDatar(array $baris): array
    {
        $out = [];
        foreach ($baris as $b) {
            foreach ($b as $sel) {
                $out[] = (string) $sel;
            }
        }

        return $out;
    }

    /** Cari nomor baris (mulai 1) yang sel pertamanya persis $teks. */
    private function cariBaris(array $baris, string $teks): ?int
    {
        foreach ($baris as $i => $b) {
            if (isset($b[0]) && (string) $b[0] === $teks) {
                return $i + 1;
            }
        }

        return null;
    }

    public function test_isi_excel_urut_sama_dengan_layar(): void
    {
        $bulan = now()->startOfMonth();
        $this->admin();
        $this->dataContoh($bulan->toDateString());

        $datar = $this->selDatar((new ProductionReportExport((int) $bulan->month, (int) $bulan->year))->array());

        $urut = array_values(array_filter($datar, fn ($v) => in_array($v, ['H-160', 'C-100', 'C-200', 'T-50'], true)));

        $this->assertSame(['H-160', 'C-100', 'C-200', 'T-50'], $urut,
            'Urutan baris Excel harus sama dengan layarnya.');
    }

    public function test_excel_dipecah_jadi_tabel_per_kategori(): void
    {
        $bulan   = now()->startOfMonth();
        $tanggal = $bulan->toDateString();
        $this->admin();
        $this->dataContoh($tanggal);

        // Kategori Channel kedua: harus jadi tabelnya sendiri.
        $swasta = $this->produk('Channel Swasta', 'CHANNEL SWASTA', 'H-SW', '75');
        $this->log($swasta, $tanggal, 2, 2, 'NO.010-011');

        $baris = (new ProductionReportExport((int) $bulan->month, (int) $bulan->year))->array();

        foreach (['Channel PLN', 'Channel Swasta', 'Cover PLN', 'Tangki PLN'] as $kategori) {
            $this->assertNotNull($this->cariBaris($baris, $kategori),
                "Tidak ada tabel untuk kategori {$kategori}.");
        }

        // Tiap kategori punya judul kolom sendiri.
        $judulKolom = array_filter($baris, fn ($b) => isset($b[0]) && $b[0] === 'Produk');
        $this->assertCount(4, $judulKolom, 'Tiap kategori harus punya judul kolomnya sendiri.');
    }

    public function test_tabel_kategori_urut_channel_dulu(): void
    {
        $bulan   = now()->startOfMonth();
        $tanggal = $bulan->toDateString();
        $this->admin();
        $this->dataContoh($tanggal);

        $swasta = $this->produk('Channel Swasta', 'CHANNEL SWASTA', 'H-SW', '75');
        $this->log($swasta, $tanggal, 2, 2);

        $baris = (new ProductionReportExport((int) $bulan->month, (int) $bulan->year))->array();

        $urutan = [
            $this->cariBaris($baris, 'Channel PLN'),
            $this->cariBaris($baris, 'Channel Swasta'),
            $this->cariBaris($baris, 'Cover PLN'),
            $this->cariBaris($baris, 'Tangki PLN'),
        ];

        $terurut = $urutan;
        sort($terurut);

        $this->assertSame($terurut, $urutan,
            'Tabel kategori harus urut Channel dulu, baru Cover, baru Tangki.');
    }

    public function test_tiap_kategori_punya_subtotal_sendiri(): void
    {
        $bulan   = now()->startOfMonth();
        $tanggal = $bulan->toDateString();
        $this->admin();
        $data = $this->dataContoh($tanggal);

        // Cover: 5 + 3 = 8 unit
        $baris = (new ProductionReportExport((int) $bulan->month, (int) $bulan->year))->array();

        $nomor = $this->cariBaris($baris, 'Subtotal Cover PLN');
        $this->assertNotNull($nomor, 'Subtotal per kategori tidak ada.');

        $subtotal = $baris[$nomor - 1];
        $this->assertSame(8, (int) $subtotal[4], 'Subtotal UP kategori Cover salah.');
        $this->assertSame(8.0, (float) $subtotal[6], 'Subtotal Grand Total kategori Cover salah.');
    }

    public function test_ada_total_keseluruhan_di_akhir(): void
    {
        $bulan = now()->startOfMonth();
        $this->admin();
        $this->dataContoh($bulan->toDateString());

        $baris = (new ProductionReportExport((int) $bulan->month, (int) $bulan->year))->array();

        $nomor = $this->cariBaris($baris, 'TOTAL KESELURUHAN');
        $this->assertNotNull($nomor, 'Baris total keseluruhan tidak ada.');
        $this->assertSame(count($baris), $nomor, 'Total keseluruhan harus jadi baris terakhir.');

        // 50 + 5 + 3 + 1 = 59
        $this->assertSame(59, (int) $baris[$nomor - 1][4]);
    }

    public function test_excel_membawa_kolom_yang_sama_dengan_layar(): void
    {
        $bulan = now()->startOfMonth();
        $this->admin();
        $this->dataContoh($bulan->toDateString());

        $baris = (new ProductionReportExport((int) $bulan->month, (int) $bulan->year))->array();

        $judul = null;
        foreach ($baris as $b) {
            if (isset($b[0]) && $b[0] === 'Produk') {
                $judul = $b;
                break;
            }
        }

        $this->assertSame(
            ['Produk', 'Seri', 'KVA', 'Satuan', 'UP', 'BT', 'Grand Total', 'No. Urut'],
            $judul,
            'Kolom Excel tidak sesuai. Kategori sudah jadi judul tabel, jadi tidak perlu jadi kolom lagi.'
        );

        // Kolom nomor urut dulu tidak pernah ikut terbawa ke Excel.
        $this->assertContains('NO.900-900', $this->selDatar($baris));
    }

    public function test_excel_menghitung_jumlah_yang_sama_dengan_layar(): void
    {
        $bulan = now()->startOfMonth();
        $this->admin();
        $data = $this->dataContoh($bulan->toDateString());

        // Produk yang sama dicatat dua kali — harus tergabung jadi satu baris.
        $this->log($data['channel'], $bulan->copy()->addDay()->toDateString(), 4, 2);

        $baris   = (new ProductionReportExport((int) $bulan->month, (int) $bulan->year))->array();
        $channel = array_values(array_filter($baris, fn ($b) => isset($b[1]) && $b[1] === 'H-160'));

        $this->assertCount(1, $channel, 'Satu produk harus satu baris.');
        $this->assertSame(5, (int) $channel[0][4]);
        $this->assertSame(2, (int) $channel[0][5]);
        $this->assertSame(7.0, (float) $channel[0][6]);
    }

    public function test_excel_kosong_tanpa_data_tidak_error(): void
    {
        $this->admin();

        $baris = (new ProductionReportExport(1, 2020))->array();

        $this->assertNotEmpty($baris, 'Berkas kosong pun tetap harus punya judul.');
        $this->assertStringContainsString('Tidak ada data', implode(' ', $this->selDatar($baris)));
    }

    public function test_berkas_excel_benar_benar_bisa_dibuat_dan_dibaca(): void
    {
        $bulan   = now()->startOfMonth();
        $tanggal = $bulan->toDateString();
        $this->admin();
        $this->dataContoh($tanggal);

        $swasta = $this->produk('Channel Swasta', 'CHANNEL SWASTA', 'H-SW', '75');
        $this->log($swasta, $tanggal, 2, 2);

        // Dirender sungguhan — penataan kolom & penggabungan sel ikut dijalankan.
        $isi  = \Maatwebsite\Excel\Facades\Excel::raw(
            new ProductionReportExport((int) $bulan->month, (int) $bulan->year),
            \Maatwebsite\Excel\Excel::XLSX
        );

        $this->assertStringStartsWith('PK', $isi, 'Berkas xlsx tidak terbentuk.');

        $berkas = tempnam(sys_get_temp_dir(), 'uji') . '.xlsx';
        file_put_contents($berkas, $isi);

        try {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($berkas)->getActiveSheet();

            $this->assertSame($bulan->translatedFormat('F') . ' ' . $bulan->year, $sheet->getTitle());

            $semua = [];
            foreach ($sheet->toArray() as $b) {
                foreach ($b as $sel) {
                    if ($sel !== null && $sel !== '') {
                        $semua[] = (string) $sel;
                    }
                }
            }

            // Baris di berkas harus sejajar dengan baris yang disusun. Baris
            // pemisah yang benar-benar kosong dulu tidak ikut tertulis, dan
            // penataannya (penggabungan sel, warna) mendarat di baris yang
            // salah — baris data malah ikut tergabung jadi terlihat kosong.
            $isiSheet = $sheet->toArray();
            $disusun  = (new ProductionReportExport((int) $bulan->month, (int) $bulan->year))->array();

            $this->assertCount(count($disusun), $isiSheet,
                'Jumlah baris di berkas beda dengan yang disusun — penataannya akan meleset.');

            $nomorBand = $this->cariBaris($disusun, 'Channel Swasta');
            $this->assertSame('Channel Swasta', (string) $isiSheet[$nomorBand - 1][0],
                'Judul kategori tidak mendarat di baris yang sama.');

            // Baris tepat di bawah judul kolom harus baris data yang utuh.
            $nomorJudul = $this->cariBaris($disusun, 'Produk');
            $barisData  = $isiSheet[$nomorJudul];
            $this->assertNotEmpty($barisData[1], 'Kolom Seri di baris data ikut kosong.');
            $this->assertNotEmpty($barisData[4], 'Kolom UP di baris data ikut kosong.');

            $this->assertContains('Channel PLN', $semua, 'Judul tabel kategori tidak sampai ke berkasnya.');
            $this->assertContains('Channel Swasta', $semua);
            $this->assertContains('Subtotal Cover PLN', $semua);
            $this->assertContains('TOTAL KESELURUHAN', $semua);
            $this->assertNotEmpty($sheet->getMergeCells(), 'Judul kategori seharusnya sel yang digabung.');
        } finally {
            @unlink($berkas);
        }
    }

    /** Muat ulang hasil ekspor sebagai lembar kerja sungguhan. */
    private function lembar(int $month, int $year): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $isi = \Maatwebsite\Excel\Facades\Excel::raw(
            new ProductionReportExport($month, $year),
            \Maatwebsite\Excel\Excel::XLSX
        );

        $berkas = tempnam(sys_get_temp_dir(), 'uji') . '.xlsx';
        file_put_contents($berkas, $isi);

        try {
            return \PhpOffice\PhpSpreadsheet\IOFactory::load($berkas)->getActiveSheet();
        } finally {
            @unlink($berkas);
        }
    }

    public function test_angka_nol_tetap_tertulis_bukan_sel_kosong(): void
    {
        $bulan    = now()->startOfMonth();
        $operator = $this->admin();

        // Cover & Tangki tidak memakai UP/BT — angkanya benar-benar 0. Sel
        // bernilai 0 sempat tidak ikut tertulis karena dianggap sama dengan
        // kosong, jadi kolomnya tampil melompong di Excel.
        $produk = $this->produk('Cover PLN', 'COVER NOL', 'C-NOL', '100');

        ProductionLog::create([
            'product_id'      => $produk->id,
            'user_id'         => $operator->id,
            'operator_name'   => 'Operator',
            'production_date' => $bulan->toDateString(),
            'up_qty'          => 0,
            'bt_qty'          => 0,
            'total_qty'       => 4,
            'reject_qty'      => 0,
        ]);

        $sheet = $this->lembar((int) $bulan->month, (int) $bulan->year);

        $nomor = null;
        foreach ($sheet->toArray() as $i => $baris) {
            if (($baris[1] ?? null) === 'C-NOL') {
                $nomor = $i + 1;
                break;
            }
        }

        $this->assertNotNull($nomor, 'Baris produk Cover tidak ketemu.');
        $this->assertSame('0', (string) $sheet->getCell("E{$nomor}")->getFormattedValue(),
            'Kolom UP bernilai 0 malah tampil kosong.');
        $this->assertSame('0', (string) $sheet->getCell("F{$nomor}")->getFormattedValue());
        $this->assertSame('4', (string) $sheet->getCell("G{$nomor}")->getFormattedValue());
    }

    public function test_bilangan_bulat_tidak_berekor_pemisah_desimal(): void
    {
        $bulan   = now()->startOfMonth();
        $tanggal = $bulan->toDateString();
        $operator = $this->admin();
        $produk   = $this->produk('Cover PLN', 'COVER BULAT', 'C-BULAT', '100');

        ProductionLog::create([
            'product_id'      => $produk->id,
            'user_id'         => $operator->id,
            'operator_name'   => 'Operator',
            'production_date' => $tanggal,
            'up_qty'          => 0,
            'bt_qty'          => 0,
            'total_qty'       => 30,
            'reject_qty'      => 0,
        ]);

        $sheet = $this->lembar((int) $bulan->month, (int) $bulan->year);

        $nomor = null;
        foreach ($sheet->toArray() as $i => $baris) {
            if (($baris[1] ?? null) === 'C-BULAT') {
                $nomor = $i + 1;
                break;
            }
        }

        $this->assertSame('30', (string) $sheet->getCell("G{$nomor}")->getFormattedValue(),
            'Bilangan bulat tidak boleh ditampilkan sebagai "30," atau "30.000".');
    }

    public function test_setengahan_channel_tidak_dibulatkan(): void
    {
        $bulan    = now()->startOfMonth();
        $operator = $this->admin();
        $produk   = $this->produk('Channel PLN', 'CHANNEL SETENGAH', 'H-STG', '100');

        ProductionLog::create([
            'product_id'      => $produk->id,
            'user_id'         => $operator->id,
            'operator_name'   => 'Operator',
            'production_date' => $bulan->toDateString(),
            'up_qty'          => 3,
            'bt_qty'          => 2,
            'total_qty'       => 2.5,          // channel: (UP + BT) / 2
            'reject_qty'      => 0,
        ]);

        $sheet = $this->lembar((int) $bulan->month, (int) $bulan->year);

        $nomor = null;
        foreach ($sheet->toArray() as $i => $baris) {
            if (($baris[1] ?? null) === 'H-STG') {
                $nomor = $i + 1;
                break;
            }
        }

        $this->assertSame('2.5', (string) $sheet->getCell("G{$nomor}")->getFormattedValue(),
            'Setengah unit milik channel tidak boleh dibulatkan.');
    }

    public function test_perataan_kolom_seragam_di_semua_baris(): void
    {
        $bulan   = now()->startOfMonth();
        $tanggal = $bulan->toDateString();
        $this->admin();
        $this->dataContoh($tanggal);

        $swasta = $this->produk('Channel Swasta', 'CHANNEL SWASTA', 'H-SW', '75');
        $this->log($swasta, $tanggal, 2, 2, 'NO.010-011');

        $sheet  = $this->lembar((int) $bulan->month, (int) $bulan->year);
        $daftar = $sheet->toArray();

        $diperiksa = 0;

        foreach ($daftar as $i => $baris) {
            // Hanya baris data: kolom Seri terisi dan bukan judul kolom.
            if (empty($baris[1]) || $baris[0] === 'Produk') {
                continue;
            }

            $n = $i + 1;
            $diperiksa++;

            $this->assertSame('left', $sheet->getStyle("A{$n}")->getAlignment()->getHorizontal(),
                "Kolom Produk di baris {$n} tidak rata kiri.");

            foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $kolom) {
                $this->assertSame('center', $sheet->getStyle("{$kolom}{$n}")->getAlignment()->getHorizontal(),
                    "Kolom {$kolom} di baris {$n} tidak rata tengah.");
            }

            $this->assertSame('left', $sheet->getStyle("H{$n}")->getAlignment()->getHorizontal(),
                "Kolom nomor urut di baris {$n} tidak rata kiri.");
        }

        $this->assertGreaterThan(4, $diperiksa, 'Terlalu sedikit baris yang diperiksa.');
    }

    /* ── Laporan harian ─────────────────────────────────────────────────── */

    public function test_laporan_harian_urut_seperti_riwayat(): void
    {
        $admin   = $this->admin();
        $tanggal = now()->toDateString();
        $this->dataContoh($tanggal);

        $html = $this->actingAs($admin)
            ->get(route('reports.daily', ['date' => $tanggal]))
            ->assertOk()->getContent();

        $this->assertSame(
            ['H-160', 'C-100', 'C-200', 'T-50'],
            $this->urutanDi($html, ['T-50', 'C-200', 'C-100', 'H-160'])
        );
    }

    public function test_pdf_harian_terunduh(): void
    {
        $admin   = $this->admin();
        $tanggal = now()->toDateString();
        $this->dataContoh($tanggal);

        $res = $this->actingAs($admin)->get(route('reports.daily-pdf', ['date' => $tanggal]));

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }
}
