<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk semua jawaban HTTP.
 *
 * Kode sisi peramban memang bisa dibaca siapa saja lewat Inspect — itu wajar,
 * berkasnya harus sampai ke peramban supaya bisa jalan. Yang perlu dijaga bukan
 * kerahasiaan kodenya, melainkan apa yang boleh dilakukan halaman ini saat
 * dibuka: jangan bisa dibingkai situs lain, jangan menebak-nebak tipe berkas,
 * jangan membocorkan alamat halaman ke situs luar, dan jangan mau diakses
 * lewat HTTP polos.
 */
class SecurityHeaders
{
    /**
     * Berapa lama peramban diminta mengingat "situs ini hanya lewat HTTPS".
     *
     * Enam bulan: cukup panjang untuk melindungi, tapi tidak mengunci selama
     * setahun kalau suatu saat sertifikatnya bermasalah.
     */
    private const HSTS_DETIK = 15552000;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set(
            'Permissions-Policy',
            'publickey-credentials-get=(), identity-credentials-get=()'
        );

        // Peramban dilarang menebak tipe berkas dari isinya. Tanpa ini, berkas
        // yang diunggah pengguna bisa diperlakukan sebagai HTML lalu dijalankan.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Halaman tidak boleh dibingkai situs lain (clickjacking): korban
        // mengira menekan tombol di situs penyerang, padahal menekan tombol di
        // aplikasi ini yang dibingkai tembus pandang.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Alamat halaman internal tidak ikut terkirim ke situs luar.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // frame-ancestors: versi modern dari X-Frame-Options.
        // object-src/base-uri: menutup dua jalur penyisipan yang klasik.
        // Sengaja tidak mengatur script-src — aplikasi ini memakai banyak skrip
        // inline, dan aturan yang terlalu ketat justru mematikan halamannya.
        $aturan = [
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
        ];

        // upgrade-insecure-requests HANYA saat halaman ini sendiri dibuka lewat
        // HTTPS. Kalau dipasang di server yang cuma melayani HTTP — seperti
        // server pabrik di alamat IP — peramban memaksa semua CSS, JS, dan
        // gambar diminta lewat https://, padahal port 443 tidak ada yang
        // mendengarkan. Hasilnya seluruh aset gagal dimuat dan halaman tampil
        // telanjang tanpa gaya. Aturan ini juga tidak ada gunanya di sana:
        // tidak ada HTTPS yang bisa dituju.
        if ($request->isSecure()) {
            array_unshift($aturan, 'upgrade-insecure-requests');
        }

        $response->headers->set('Content-Security-Policy', implode('; ', $aturan));

        // Versi PHP tidak perlu diumumkan — itu petunjuk gratis untuk mencari
        // celah yang cocok dengan versinya.
        $response->headers->remove('X-Powered-By');

        // asata: passkey/kredensial browser tidak dipakai — matikan permintaannya.
        $response->headers->set(
            'Permissions-Policy',
            'publickey-credentials-get=(), identity-credentials-get=()'
        );

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=' . self::HSTS_DETIK);
        }

        return $response;
    }
}
