<?php

namespace Tests\Unit\Ref;

use App\Support\HtmlCatatan;
use PHPUnit\Framework\TestCase;

/**
 * Penyaring isi catatan.
 *
 * Isinya datang dari editor di peramban, jadi yang diuji bukan cuma "rapi",
 * tapi juga "tidak bisa dipakai menyelipkan skrip".
 */
class HtmlCatatanTest extends TestCase
{
    public function test_tag_toolbar_dipertahankan(): void
    {
        $hasil = HtmlCatatan::bersihkan('<p><strong>Tebal</strong> dan <em>miring</em> dan <u>garis bawah</u></p>');

        $this->assertStringContainsString('<strong>Tebal</strong>', $hasil);
        $this->assertStringContainsString('<em>miring</em>', $hasil);
        $this->assertStringContainsString('<u>garis bawah</u>', $hasil);
    }

    public function test_daftar_bernomor_dan_berbutir_dipertahankan(): void
    {
        $hasil = HtmlCatatan::bersihkan('<ul><li>satu</li></ul><ol><li>dua</li></ol>');

        $this->assertStringContainsString('<ul><li>satu</li></ul>', $hasil);
        $this->assertStringContainsString('<ol><li>dua</li></ol>', $hasil);
    }

    public function test_skrip_dibuang_beserta_isinya(): void
    {
        $hasil = HtmlCatatan::bersihkan('<p>Halo</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('script', strtolower((string) $hasil));
        $this->assertStringNotContainsString('alert(1)', (string) $hasil);
        $this->assertStringContainsString('Halo', $hasil);
    }

    public function test_atribut_kejadian_dibuang(): void
    {
        $hasil = HtmlCatatan::bersihkan('<p onclick="curi()" onmouseover="x()">Teks</p>');

        $this->assertStringNotContainsString('onclick', (string) $hasil);
        $this->assertStringNotContainsString('onmouseover', (string) $hasil);
        $this->assertStringContainsString('Teks', $hasil);
    }

    public function test_tautan_dan_gambar_dibuka_bungkusnya_teksnya_tetap(): void
    {
        $hasil = HtmlCatatan::bersihkan('<p><a href="javascript:alert(1)">klik</a></p><img src="x" onerror="alert(1)">');

        $this->assertStringNotContainsString('javascript:', (string) $hasil);
        $this->assertStringNotContainsString('<img', (string) $hasil);
        $this->assertStringNotContainsString('onerror', (string) $hasil);
        $this->assertStringContainsString('klik', $hasil, 'Teks tautannya tidak boleh ikut hilang.');
    }

    public function test_perataan_paragraf_diizinkan_tapi_gaya_lain_tidak(): void
    {
        $hasil = HtmlCatatan::bersihkan(
            '<p style="text-align:center; position:fixed; background:url(x)">Tengah</p>'
        );

        $this->assertStringContainsString('text-align:center', $hasil);
        $this->assertStringNotContainsString('position', (string) $hasil);
        $this->assertStringNotContainsString('background', (string) $hasil);
    }

    public function test_perataan_yang_tidak_dikenal_ditolak(): void
    {
        $hasil = HtmlCatatan::bersihkan('<p style="text-align:expression(alert(1))">Teks</p>');

        $this->assertStringNotContainsString('style', (string) $hasil);
    }

    public function test_editor_kosong_dianggap_tidak_ada_isi(): void
    {
        $this->assertNull(HtmlCatatan::bersihkan('<p></p>'));
        $this->assertNull(HtmlCatatan::bersihkan('<div>   </div>'));
        $this->assertNull(HtmlCatatan::bersihkan(''));
        $this->assertNull(HtmlCatatan::bersihkan(null));
    }

    public function test_baris_kosong_yang_disengaja_tetap_dihitung_ada_isi(): void
    {
        $this->assertNotNull(HtmlCatatan::bersihkan('<p><br></p>'),
            'Baris kosong memang ditulis pengguna, jangan dianggap catatan kosong.');
    }

    public function test_huruf_beraksen_tidak_rusak(): void
    {
        $hasil = HtmlCatatan::bersihkan('<p>Selesai — periksa ulang “kabel” ya</p>');

        $this->assertStringContainsString('—', $hasil);
        $this->assertStringContainsString('“kabel”', $hasil);
    }

    public function test_catatan_lama_berupa_teks_biasa_tetap_utuh(): void
    {
        $teks = "Baris satu\nBaris dua";

        $this->assertSame($teks, HtmlCatatan::bersihkan($teks),
            'Catatan tanpa tag tidak perlu diapa-apakan saat disimpan.');
    }

    public function test_catatan_lama_ditampilkan_dengan_barisnya(): void
    {
        $hasil = HtmlCatatan::tampil("Baris satu\nBaris dua");

        $this->assertStringContainsString('<br', $hasil, 'Baris barunya harus tetap kelihatan.');
        $this->assertStringContainsString('Baris dua', $hasil);
    }

    public function test_tanda_kurung_di_catatan_teks_biasa_di_escape_saat_tampil(): void
    {
        $hasil = HtmlCatatan::tampil('suhu < 5 & tekanan > 3');

        $this->assertStringContainsString('&lt; 5', $hasil);
        $this->assertStringContainsString('&gt; 3', $hasil);
        $this->assertStringContainsString('&amp;', $hasil);
    }

    public function test_skrip_di_catatan_lama_tidak_ikut_tampil(): void
    {
        $hasil = HtmlCatatan::tampil('awas <script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $hasil);
        $this->assertStringNotContainsString('alert(1)', $hasil);
        $this->assertStringContainsString('awas', $hasil);
    }

    public function test_isi_html_disaring_lagi_saat_ditampilkan(): void
    {
        // Pertahanan lapis kedua: kalau ada isi lama yang lolos ke basis data,
        // tetap tidak ikut tampil.
        $hasil = HtmlCatatan::tampil('<p>Halo</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('alert(1)', $hasil);
        $this->assertStringContainsString('Halo', $hasil);
    }

    public function test_versi_teks_polos_untuk_cuplikan(): void
    {
        $hasil = HtmlCatatan::teks('<ul><li>satu</li><li>dua</li></ul>');

        $this->assertSame('satu dua', $hasil);
    }

    public function test_versi_teks_polos_tidak_menempelkan_kata(): void
    {
        $this->assertSame('Halo dunia', HtmlCatatan::teks('<p>Halo</p><p>dunia</p>'));
    }
}
