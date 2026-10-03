<?php

namespace Tests\Unit\Ref;

use App\Support\NomorUrut;
use PHPUnit\Framework\TestCase;

/**
 * Rentang nomor urut sebulan.
 *
 * Yang diharapkan: nomor awal diambil dari catatan paling awal di bulan itu,
 * nomor akhir dari catatan terakhir — bukan rentang satu hari saja.
 */
class NomorUrutTest extends TestCase
{
    public function test_satu_catatan_tampil_apa_adanya(): void
    {
        $this->assertSame('NO.596-625', NomorUrut::rentang(['NO.596-625']));
    }

    public function test_beberapa_hari_digabung_jadi_satu_rentang(): void
    {
        $hasil = NomorUrut::rentang([
            'NO.001-010',
            'NO.011-025',
            'NO.026-040',
        ]);

        $this->assertSame('NO.001-040', $hasil,
            'Rentangnya harus dari nomor pertama bulan itu sampai yang terakhir.');
    }

    public function test_up_dan_bt_dihitung_sendiri_sendiri(): void
    {
        $hasil = NomorUrut::rentang([
            "BT NO.887-898",
            "UP NO.863-868\nBT NO.899-904",
            "UP NO.893-895\nBT NO.915-927",
        ]);

        $this->assertSame("UP NO.863-895\nBT NO.887-927", $hasil);
    }

    public function test_up_selalu_ditulis_sebelum_bt(): void
    {
        // Catatan hari pertama bisa saja cuma berisi BT.
        $hasil = NomorUrut::rentang(["BT NO.010-012", "UP NO.001-003"]);

        $this->assertStringStartsWith('UP ', $hasil);
        $this->assertStringContainsString("\nBT ", $hasil);
    }

    public function test_urutan_catatan_tidak_mempengaruhi_hasil(): void
    {
        $maju   = NomorUrut::rentang(['NO.001-010', 'NO.011-025']);
        $mundur = NomorUrut::rentang(['NO.011-025', 'NO.001-010']);

        $this->assertSame($maju, $mundur);
    }

    public function test_nomor_yang_bolong_tetap_dirangkum_awal_sampai_akhir(): void
    {
        // Ada hari yang terlewat; yang diminta tetap "dari sekian sampai sekian".
        $this->assertSame('NO.001-040', NomorUrut::rentang(['NO.001-010', 'NO.031-040']));
    }

    public function test_lebar_angka_dipertahankan(): void
    {
        $this->assertSame('NO.0001-0400', NomorUrut::rentang(['NO.0001-0010', 'NO.0390-0400']));
        $this->assertSame('NO.007-009', NomorUrut::rentang(['NO.7-9']),
            'Nomor pendek tetap diberi nol di depan seperti format form input.');
    }

    public function test_nomor_tunggal_tanpa_rentang(): void
    {
        $this->assertSame('NO.007-007', NomorUrut::rentang(['NO.007']));
        $this->assertSame('NO.007-012', NomorUrut::rentang(['NO.007', 'NO.012']));
    }

    public function test_catatan_kosong_menghasilkan_kosong(): void
    {
        $this->assertSame('', NomorUrut::rentang([]));
        $this->assertSame('', NomorUrut::rentang([null, '', '   ']));
    }

    public function test_catatan_bebas_dipertahankan_kalau_bukan_nomor_urut(): void
    {
        $hasil = NomorUrut::rentang(['dikerjakan ulang', 'dikerjakan ulang']);

        $this->assertSame('dikerjakan ulang', $hasil, 'Catatan biasa tidak boleh hilang, dan tidak digandakan.');
    }

    public function test_catatan_bebas_diabaikan_kalau_ada_nomor_urut(): void
    {
        $hasil = NomorUrut::rentang(["NO.001-010", "catatan tambahan", "NO.011-020"]);

        $this->assertSame('NO.001-020', $hasil);
    }

    public function test_spasi_dan_huruf_kecil_tetap_terbaca(): void
    {
        $this->assertSame('NO.001-020', NomorUrut::rentang(['no. 001 - 010', 'No.011-020']));
    }
}
