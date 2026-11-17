<?php
/*
 * Endpoint versi realtime (PRD 14). SENGAJA tanpa Laravel: hanya membaca berkas kecil di storage/app/rt, jadi
 * ~1-3 ms per permintaan. Mengembalikan nomor versi tiap topik yang diminta; 304 bila tidak ada yang berubah.
 * Dilindungi token bertanda tangan (dibuat App\Support\Realtime::token). Tidak membocorkan isi data, hanya nomor.
 * Pemakaian: rt.php?t=<token>&p=produksi,chat,notif
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

$dir = dirname(__DIR__) . '/storage/app/rt/';
$bagian = explode('.', (string) ($_GET['t'] ?? ''));
$kunci = is_file($dir . 'kunci') ? trim((string) @file_get_contents($dir . 'kunci')) : '';

if (count($bagian) !== 3 || $kunci === '' || ! ctype_digit($bagian[0]) || ! ctype_digit($bagian[1]) || (int) $bagian[1] < time()
    || ! hash_equals(hash_hmac('sha256', $bagian[0] . '.' . $bagian[1], $kunci), $bagian[2])) {
    http_response_code(403);
    exit('{}');
}

$uid = $bagian[0];
$global = ['produksi', 'target', 'catatan', 'gambar'];
$pengguna = ['chat', 'notif'];
$hasil = [];
foreach (explode(',', (string) ($_GET['p'] ?? '')) as $p) {
    if (in_array($p, $global, true)) {
        $hasil[$p] = trim((string) @file_get_contents($dir . $p . '.v')) ?: '0';
    } elseif (in_array($p, $pengguna, true)) {
        $hasil[$p] = trim((string) @file_get_contents($dir . $p . '.' . $uid . '.v')) ?: '0';
    }
}

$badan = json_encode($hasil, JSON_FORCE_OBJECT);
$etag = '"' . md5($badan) . '"';
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
echo $badan;
