<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Penyaring isi catatan.
 *
 * Isi catatan sekarang ditulis lewat editor bertoolbar, jadi yang tersimpan
 * berupa HTML. HTML itu datang dari peramban pengguna dan tidak boleh dipercaya
 * apa adanya: yang disimpan hanya tag & gaya yang memang dipakai toolbar-nya.
 * Semua sisanya dibuang (tapi teksnya dipertahankan), sehingga isi catatan
 * aman ditampilkan tanpa di-escape lagi.
 *
 * Catatan lama tersimpan sebagai teks biasa. Supaya tetap terbaca, teks tanpa
 * tag diubah jadi HTML saat ditampilkan — bukan saat disimpan.
 */
class HtmlCatatan
{
    /** Tag yang boleh disimpan. Sisanya dibuka bungkusnya, isinya tetap ada. */
    private const TAG_BOLEH = [
        'p', 'br', 'div',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'blockquote',
    ];

    /** Tag yang isinya ikut dibuang, bukan cuma bungkusnya. */
    private const TAG_BUANG_TOTAL = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg'];

    /** Nilai text-align yang diizinkan (dipakai tombol rata kiri/tengah/kanan). */
    private const RATA_BOLEH = ['left', 'center', 'right', 'justify'];

    /**
     * Saring HTML dari editor sebelum disimpan.
     */
    public static function bersihkan(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);
        if ($html === '') {
            return null;
        }

        // Tidak ada tag sama sekali: biarkan tersimpan sebagai teks biasa.
        if (strip_tags($html) === $html) {
            return $html;
        }

        $doc = new DOMDocument();
        $sebelumnya = libxml_use_internal_errors(true);

        // <?xml encoding>: tanpa ini DOMDocument menganggap isinya ISO-8859-1
        // dan huruf beraksen jadi rusak. JANGAN ditambah <meta charset>: di
        // libxml 2.11+ elemen itu menjadi akar dan seluruh isi sesudahnya hilang.
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="qc-akar">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($sebelumnya);

        // XPath, bukan getElementById: pengenalan atribut id tanpa DTD berbeda
        // antar versi libxml, dan kalau akarnya tidak ketemu seluruh format
        // catatan ikut terbuang lewat jalur cadangan di bawah.
        $akar = (new DOMXPath($doc))->query('//div[@id="qc-akar"]')->item(0);
        if (! $akar) {
            return e(strip_tags($html)) ?: null;
        }

        self::saringAnak($akar);

        $hasil = '';
        foreach ($akar->childNodes as $anak) {
            $hasil .= $doc->saveHTML($anak);
        }

        $hasil = trim($hasil);

        // Editor kosong menyisakan bungkus tanpa isi — anggap tidak ada catatan.
        return self::kosong($hasil) ? null : $hasil;
    }

    /**
     * HTML siap tampil.
     *
     * Dipakai untuk isi yang sudah tersimpan: catatan baru sudah berupa HTML,
     * catatan lama masih teks biasa dan barisnya harus tetap terjaga.
     */
    public static function tampil(?string $isi): string
    {
        if ($isi === null || trim($isi) === '') {
            return '';
        }

        if (strip_tags($isi) === $isi) {
            return nl2br(e($isi));
        }

        return (string) self::bersihkan($isi);
    }

    /**
     * Teks polos — untuk cuplikan, pencarian, dan ekspor.
     */
    public static function teks(?string $isi): string
    {
        if ($isi === null) {
            return '';
        }

        // Tag blok diganti spasi supaya kata terakhir satu baris tidak menempel
        // ke kata pertama baris berikutnya.
        $teks = preg_replace('~<(br|/p|/div|/li|/h[1-6])[^>]*>~i', ' ', $isi);

        return trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags((string) $teks), ENT_QUOTES, 'UTF-8')));
    }

    private static function kosong(string $html): bool
    {
        $tanpaBr = preg_replace('~<br[^>]*>~i', '', $html);

        return trim(html_entity_decode(strip_tags((string) $tanpaBr), ENT_QUOTES, 'UTF-8')) === ''
            && ! str_contains(strtolower($html), '<br');
    }

    private static function saringAnak(DOMNode $induk): void
    {
        // Disalin dulu: daftar anak ikut berubah saat simpulnya dipindah/dibuang.
        foreach (iterator_to_array($induk->childNodes) as $anak) {
            self::saringSimpul($anak);
        }
    }

    private static function saringSimpul(DOMNode $simpul): void
    {
        if ($simpul instanceof DOMText) {
            return;
        }

        if (! $simpul instanceof DOMElement) {
            // Komentar, CDATA, dan sejenisnya tidak perlu ikut tersimpan.
            $simpul->parentNode?->removeChild($simpul);

            return;
        }

        $tag = strtolower($simpul->nodeName);

        if (in_array($tag, self::TAG_BUANG_TOTAL, true)) {
            $simpul->parentNode?->removeChild($simpul);

            return;
        }

        if (! in_array($tag, self::TAG_BOLEH, true)) {
            self::saringAnak($simpul);
            self::bukaBungkus($simpul);

            return;
        }

        self::saringAtribut($simpul, $tag);
        self::saringAnak($simpul);
    }

    /** Buang bungkusnya, isinya dinaikkan ke induk. */
    private static function bukaBungkus(DOMElement $simpul): void
    {
        $induk = $simpul->parentNode;
        if (! $induk) {
            return;
        }

        while ($simpul->firstChild) {
            $induk->insertBefore($simpul->firstChild, $simpul);
        }

        $induk->removeChild($simpul);
    }

    private static function saringAtribut(DOMElement $simpul, string $tag): void
    {
        $rata = null;

        if (in_array($tag, ['p', 'div', 'li', 'blockquote'], true)) {
            $gaya = $simpul->getAttribute('style');
            if ($gaya !== '' && preg_match('~text-align\s*:\s*([a-z]+)~i', $gaya, $cocok)) {
                $nilai = strtolower($cocok[1]);
                if (in_array($nilai, self::RATA_BOLEH, true)) {
                    $rata = $nilai;
                }
            }
        }

        // Semua atribut dibuang — termasuk on* dan href yang bisa dipakai
        // menjalankan skrip — lalu hanya perataan yang dikembalikan.
        foreach (iterator_to_array($simpul->attributes ?? []) as $atribut) {
            $simpul->removeAttribute($atribut->nodeName);
        }

        if ($rata !== null) {
            $simpul->setAttribute('style', 'text-align:' . $rata);
        }
    }
}
