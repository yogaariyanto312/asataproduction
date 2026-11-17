<?php

namespace Tests\Unit\Ref;

use App\Support\UrutanProduksi;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Aturan urutan data produksi yang dipakai bersama oleh Riwayat Produksi,
 * layar Laporan, PDF, dan Excel.
 */
class UrutanProduksiTest extends TestCase
{
    /** Baris tiruan: cukup punya product->category->name, kva, dan series. */
    private function baris(string $kategori, string $kva, string $seri): object
    {
        return (object) [
            'product' => (object) [
                'kva'      => $kva,
                'series'   => $seri,
                'category' => (object) ['name' => $kategori],
            ],
        ];
    }

    private function seri(Collection $hasil): array
    {
        return $hasil->map(fn ($b) => $b->product->series)->all();
    }

    public function test_nama_kategori_dipadatkan_jadi_label(): void
    {
        $this->assertSame('Channel', UrutanProduksi::label('Channel PLN'));
        $this->assertSame('Cover', UrutanProduksi::label('COVER Swasta'));
        $this->assertSame('Tangki', UrutanProduksi::label('tangki type'));
    }

    public function test_kategori_tak_dikenal_memakai_namanya_sendiri(): void
    {
        $this->assertSame('Aksesoris', UrutanProduksi::label('Aksesoris'));
        $this->assertSame('Lainnya', UrutanProduksi::label(null));
        $this->assertSame('Lainnya', UrutanProduksi::label(''));
    }

    public function test_urutan_kategori_channel_cover_tangki_lalu_sisanya(): void
    {
        $hasil = UrutanProduksi::urutkan(collect([
            $this->baris('Aksesoris', '10', 'X'),
            $this->baris('Tangki PLN', '10', 'T'),
            $this->baris('Cover PLN', '10', 'C'),
            $this->baris('Channel PLN', '10', 'H'),
        ]));

        $this->assertSame(['H', 'C', 'T', 'X'], $this->seri($hasil));
    }

    public function test_kva_diurutkan_sebagai_angka_bukan_teks(): void
    {
        // Sebagai teks, "1000" berada sebelum "200".
        $hasil = UrutanProduksi::urutkan(collect([
            $this->baris('Cover PLN', '1000', 'seribu'),
            $this->baris('Cover PLN', '200', 'duaratus'),
        ]));

        $this->assertSame(['duaratus', 'seribu'], $this->seri($hasil));
    }

    public function test_seri_jadi_penentu_saat_kva_sama(): void
    {
        $hasil = UrutanProduksi::urutkan(collect([
            $this->baris('Cover PLN', '100', 'C-B'),
            $this->baris('Cover PLN', '100', 'C-A'),
        ]));

        $this->assertSame(['C-A', 'C-B'], $this->seri($hasil));
    }

    public function test_baris_tanpa_produk_tidak_bikin_error(): void
    {
        $kosong = (object) ['product' => null];

        $hasil = UrutanProduksi::urutkan(collect([
            $this->baris('Cover PLN', '100', 'C'),
            $kosong,
        ]));

        $this->assertCount(2, $hasil);
    }

    public function test_pengelompokan_urut_dan_isinya_juga_urut(): void
    {
        $kelompok = UrutanProduksi::kelompokkan(collect([
            $this->baris('Tangki PLN', '50', 'T-50'),
            $this->baris('Cover Swasta', '200', 'C-200'),
            $this->baris('Cover PLN', '100', 'C-100'),
            $this->baris('Channel PLN', '160', 'H-160'),
        ]));

        $this->assertSame(['Channel', 'Cover', 'Tangki'], $kelompok->keys()->all());
        $this->assertSame(['C-100', 'C-200'], $this->seri($kelompok['Cover']));
    }

    public function test_kategori_dengan_kata_kunci_berbeda_tetap_satu_kelompok(): void
    {
        $kelompok = UrutanProduksi::kelompokkan(collect([
            $this->baris('Cover PLN', '100', 'A'),
            $this->baris('Cover Swasta', '200', 'B'),
            $this->baris('Cover Type', '300', 'C'),
        ]));

        $this->assertSame(['Cover'], $kelompok->keys()->all());
        $this->assertCount(3, $kelompok['Cover']);
    }
}
