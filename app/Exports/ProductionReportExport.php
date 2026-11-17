<?php

namespace App\Exports;

use App\Http\Controllers\ReportController;
use App\Support\UrutanProduksi;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Rekap produksi bulanan dalam bentuk Excel.
 *
 * Isinya dipecah jadi satu tabel per kategori (Channel-PLN, Channel Swasta,
 * Cover-PLN, dan seterusnya) dengan subtotal masing-masing, bukan satu daftar
 * panjang yang kategorinya cuma jadi kolom. Datanya diambil dari sumber yang
 * sama dengan layar Laporan, jadi isi dan urutannya tidak bisa berselisih.
 *
 * WithStrictNullComparison penting: tanpa itu angka 0 dianggap sama dengan
 * "kosong" dan tidak ikut ditulis, sehingga kolom UP/BT pada kategori yang
 * memang tidak memakainya (Cover, Tangki) tampil kosong melompong.
 */
class ProductionReportExport implements FromArray, WithTitle, WithEvents, WithStrictNullComparison, ShouldAutoSize
{
    private const KOLOM = ['Produk', 'Seri', 'KVA', 'Satuan', 'UP', 'BT', 'Grand Total', 'No. Urut'];

    private const KOLOM_TERAKHIR = 'H';

    /** Warna dasar, disamakan dengan tema aplikasi. */
    private const BIRU_TUA  = '1E40AF';
    private const BIRU_MUDA = 'DBEAFE';
    private const ABU_MUDA  = 'F1F5F9';

    private ?array $baris = null;

    /** Nomor baris yang perlu diberi gaya khusus, dicatat saat menyusun. */
    private array $barisKategori   = [];
    private array $barisJudulKolom = [];
    private array $barisSubtotal   = [];
    private array $barisData       = [];
    private ?int $barisTotal       = null;

    /**
     * Baris pemisah.
     *
     * Sengaja berisi sel kosong, bukan larik kosong: baris yang benar-benar
     * kosong tidak ikut ditulis ke berkas, sehingga nomor baris yang dicatat
     * di sini meleset dan penataannya (penggabungan sel, warna) mendarat di
     * baris yang salah.
     */
    private function pemisah(): array
    {
        return array_fill(0, count(self::KOLOM), '');
    }

    public function __construct(
        private int $month,
        private int $year,
        // asata: filter departemen (developer).
        private ?string $deptFilter = null,
    ) {}

    public function array(): array
    {
        return $this->baris ??= $this->susun();
    }

    public function title(): string
    {
        return Carbon::create(null, $this->month)->translatedFormat('F') . ' ' . $this->year;
    }

    private function susun(): array
    {
        $rekap    = ReportController::rekapBulanan($this->month, $this->year, $this->deptFilter);
        $kelompok = UrutanProduksi::kelompokkanPerKategori($rekap);

        $baris = [];
        $baris[] = ['LAPORAN PRODUKSI — ' . $this->title()];
        $baris[] = $this->pemisah();

        if ($kelompok->isEmpty()) {
            $baris[] = ['Tidak ada data produksi pada periode ini.'];

            return $baris;
        }

        foreach ($kelompok as $namaKategori => $isi) {
            $baris[] = [$namaKategori];
            $this->barisKategori[] = count($baris);

            $baris[] = self::KOLOM;
            $this->barisJudulKolom[] = count($baris);

            foreach ($isi as $item) {
                $baris[] = [
                    $item->product->name ?? '-',
                    $item->product->series ?? '-',
                    $item->product->kva ?? '-',
                    $item->product->unit ?? 'unit',
                    (int) $item->total_up,
                    (int) $item->total_bt,
                    (float) $item->grand_total,
                    trim((string) $item->nomor_urut) ?: '-',
                ];
                $this->barisData[] = count($baris);
            }

            $baris[] = [
                'Subtotal ' . $namaKategori, '', '', '',
                (int) $isi->sum('total_up'),
                (int) $isi->sum('total_bt'),
                (float) $isi->sum('grand_total'),
                '',
            ];
            $this->barisSubtotal[] = count($baris);

            $baris[] = $this->pemisah();
        }

        $baris[] = [
            'TOTAL KESELURUHAN', '', '', '',
            (int) $rekap->sum('total_up'),
            (int) $rekap->sum('total_bt'),
            (float) $rekap->sum('grand_total'),
            '',
        ];
        $this->barisTotal = count($baris);

        return $baris;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet  = $event->sheet->getDelegate();
                $akhir  = self::KOLOM_TERAKHIR;

                // Pastikan baris sudah disusun (nomor barisnya dicatat di sana).
                $this->array();

                /* Judul laporan */
                $sheet->mergeCells("A1:{$akhir}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                /* Kepala tiap kategori */
                foreach ($this->barisKategori as $r) {
                    $sheet->mergeCells("A{$r}:{$akhir}{$r}");
                    $gaya = $sheet->getStyle("A{$r}:{$akhir}{$r}");
                    $gaya->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
                    $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BIRU_TUA);
                    $sheet->getRowDimension($r)->setRowHeight(20);
                }

                /* Judul kolom tiap tabel */
                foreach ($this->barisJudulKolom as $r) {
                    $gaya = $sheet->getStyle("A{$r}:{$akhir}{$r}");
                    $gaya->getFont()->setBold(true);
                    $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BIRU_MUDA);
                    $gaya->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                /* Subtotal per kategori */
                foreach ($this->barisSubtotal as $r) {
                    $sheet->mergeCells("A{$r}:D{$r}");
                    $gaya = $sheet->getStyle("A{$r}:{$akhir}{$r}");
                    $gaya->getFont()->setBold(true);
                    $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ABU_MUDA);
                }

                /* Total keseluruhan */
                if ($this->barisTotal) {
                    $r = $this->barisTotal;
                    $sheet->mergeCells("A{$r}:D{$r}");
                    $gaya = $sheet->getStyle("A{$r}:{$akhir}{$r}");
                    $gaya->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
                    $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BIRU_TUA);
                    $sheet->getRowDimension($r)->setRowHeight(20);
                }

                /* Perataan dibuat seragam untuk semua baris isi: nama produk
                   dan nomor urut rata kiri (teks panjang), sisanya rata tengah.
                   Kalau tidak diatur, Excel merata-kirikan teks dan
                   merata-kanankan angka sendiri — dalam satu tabel jadi
                   terlihat campur aduk. */
                $barisIsi = array_merge($this->barisData, $this->barisSubtotal, array_filter([$this->barisTotal]));

                foreach ($barisIsi as $r) {
                    $sheet->getStyle("A{$r}:{$akhir}{$r}")
                          ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                    $sheet->getStyle("A{$r}")
                          ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    $sheet->getStyle("B{$r}:G{$r}")
                          ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // "General": 30 tetap "30" dan 57,5 tetap "57,5". Format
                    // berdesimal seperti #,##0.### menempelkan pemisah desimal
                    // pada bilangan bulat ("30,"), dan #,##0 malah membulatkan
                    // setengahan milik channel.
                    $sheet->getStyle("E{$r}:G{$r}")->getNumberFormat()->setFormatCode('General');

                    // Nomor urut bisa dua baris (UP & BT).
                    $sheet->getStyle("{$akhir}{$r}")->getAlignment()
                          ->setWrapText(true)
                          ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                /* Garis tabel pada baris isi & judul kolomnya */
                foreach (array_merge($this->barisJudulKolom, $this->barisData, $this->barisSubtotal) as $r) {
                    $sheet->getStyle("A{$r}:{$akhir}{$r}")
                          ->getBorders()->getAllBorders()
                          ->setBorderStyle(Border::BORDER_THIN)
                          ->getColor()->setRGB('CBD5E1');
                }

                $sheet->getColumnDimension(self::KOLOM_TERAKHIR)->setAutoSize(false);
                $sheet->getColumnDimension(self::KOLOM_TERAKHIR)->setWidth(34);
            },
        ];
    }
}
