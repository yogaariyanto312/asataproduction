<?php

namespace App\Services\Telegram;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;

/**
 * Perintah /lapor — mencatat hasil produksi dari Telegram.
 *
 * SENGAJA TERBATAS. Halaman Input Produksi punya beberapa aturan halus yang
 * tidak layak ditiru setengah-setengah lewat obrolan:
 *
 *  - produk Channel memakai rumus total = (UP + BT) / 2;
 *  - kategori berseri manual MEMBUAT produk baru otomatis dari seri yang
 *    diketik — salah ketik lewat chat berarti produk sampah yang permanen;
 *  - entri produk+tanggal yang sama DIGABUNG, bukan ditambah sebagai baris baru.
 *
 * Yang ditiru di sini: rumus Channel dan aturan gabung (keduanya wajib, kalau
 * tidak data bot dan data layar akan bercabang). Yang ditolak: kategori berseri
 * manual — diarahkan ke aplikasi, karena risikonya tidak sebanding.
 */
class Pelaporan
{
    public static function catat(string $argumen, User $pengguna): string
    {
        $bagian = preg_split('/\s+/', trim($argumen), -1, PREG_SPLIT_NO_EMPTY);

        if (count($bagian) < 2) {
            return self::caraPakai('Perintahnya belum lengkap.');
        }

        $seri   = array_shift($bagian);
        $produk = self::cariProduk($seri);

        if (is_string($produk)) {
            return $produk;             // pesan galat dari pencarian produk
        }

        if ($produk->category?->has_manual_serial) {
            return '❌ ' . Teks::tebal($produk->name) . " memakai seri manual.\n\n"
                 . "Kategori seperti ini membuat produk baru otomatis dari seri yang diketik, "
                 . "jadi salah ketik lewat chat akan meninggalkan produk sampah yang permanen.\n\n"
                 . 'Catat lewat menu ' . Teks::tebal('Input Produksi') . ' di aplikasi.';
        }

        $angka = self::uraiAngka($bagian);

        if (isset($angka['galat'])) {
            return self::caraPakai($angka['galat']);
        }

        return $produk->isChannel()
            ? self::simpanChannel($produk, $angka, $pengguna)
            : self::simpanBiasa($produk, $angka, $pengguna);
    }

    /* ───────────────────────── Pencarian produk ───────────────────────── */

    private static function cariProduk(string $seri): Product|string
    {
        $cocok = Product::with('category')
            ->where('is_active', true)
            ->where('series', $seri)
            ->get();

        // Kalau tidak persis, coba yang mengandung — orang jarang mengetik lengkap.
        if ($cocok->isEmpty()) {
            $cocok = Product::with('category')
                ->where('is_active', true)
                ->where('series', 'like', '%' . $seri . '%')
                ->limit(6)
                ->get();
        }

        if ($cocok->isEmpty()) {
            return '❌ Produk dengan seri ' . Teks::kode($seri) . " tidak ditemukan.\n\n"
                 . 'Pastikan serinya sudah terdaftar di menu ' . Teks::tebal('Master Produk') . '.';
        }

        if ($cocok->count() > 1) {
            $baris = ['⚠️ Seri ' . Teks::kode($seri) . ' cocok dengan beberapa produk:', ''];

            foreach ($cocok as $p) {
                $baris[] = '· ' . Teks::kode($p->series) . ' — ' . Teks::aman($p->name);
            }

            $baris[] = '';
            $baris[] = 'Tulis serinya lebih lengkap.';

            return implode("\n", $baris);
        }

        return $cocok->first();
    }

    /* ───────────────────────── Penguraian angka ───────────────────────── */

    /**
     * Menerima dua bentuk:
     *   "12 2"                  → qty 12, reject 2
     *   "up 10 bt 8 reject 1"   → khusus Channel
     */
    private static function uraiAngka(array $bagian): array
    {
        $hasil = ['qty' => null, 'up' => null, 'bt' => null, 'reject' => 0];

        // Bentuk berlabel: up/bt/reject.
        $berlabel = false;
        for ($i = 0; $i < count($bagian); $i++) {
            $label = strtolower(rtrim($bagian[$i], ':='));

            if (in_array($label, ['up', 'bt', 'reject', 'rj'], true)) {
                $berlabel = true;
                $nilai    = $bagian[$i + 1] ?? null;

                if (! is_numeric($nilai)) {
                    return ['galat' => 'Nilai untuk ' . Teks::kode($label) . ' bukan angka.'];
                }

                $kunci = $label === 'rj' ? 'reject' : $label;
                $hasil[$kunci] = (float) $nilai;
                $i++;
            }
        }

        if ($berlabel) {
            return $hasil;
        }

        // Bentuk ringkas: <qty> [reject]
        if (! is_numeric($bagian[0] ?? null)) {
            return ['galat' => 'Jumlahnya bukan angka.'];
        }

        $hasil['qty'] = (float) $bagian[0];

        if (isset($bagian[1])) {
            if (! is_numeric($bagian[1])) {
                return ['galat' => 'Jumlah reject bukan angka.'];
            }
            $hasil['reject'] = (float) $bagian[1];
        }

        return $hasil;
    }

    /* ───────────────────────── Penyimpanan ───────────────────────── */

    private static function simpanChannel(Product $produk, array $angka, User $pengguna): string
    {
        if ($angka['up'] === null && $angka['bt'] === null) {
            return '⚠️ ' . Teks::tebal($produk->name) . " adalah produk Channel.\n\n"
                 . "UP dan BT dicatat terpisah, contoh:\n"
                 . Teks::kode('/lapor ' . $produk->series . ' up 10 bt 8') . "\n"
                 . 'Tambahkan ' . Teks::kode('reject 1') . ' bila ada.';
        }

        $up = (int) ($angka['up'] ?? 0);
        $bt = (int) ($angka['bt'] ?? 0);

        if ($up < 0 || $bt < 0 || $angka['reject'] < 0) {
            return self::caraPakai('Jumlah tidak boleh negatif.');
        }

        $lama = self::entriHariIni($produk, $pengguna);

        if ($lama) {
            $upBaru = $lama->up_qty + $up;
            $btBaru = $lama->bt_qty + $bt;

            $lama->update([
                'up_qty'     => $upBaru,
                'bt_qty'     => $btBaru,
                'total_qty'  => ($upBaru + $btBaru) / 2,
                'reject_qty' => $lama->reject_qty + (int) $angka['reject'],
            ]);

            return self::pesanSukses($produk, $lama->fresh(), 'ditambahkan ke entri hari ini');
        }

        $log = ProductionLog::create([
            'product_id'      => $produk->id,
            'user_id'         => $pengguna->id,
            'operator_name'   => $pengguna->name,
            // asata: bot berjalan tanpa sesi login, jadi departemen diisi dari akun tertaut.
            'department'      => $pengguna->department,
            'production_date' => now()->toDateString(),
            'up_qty'          => $up,
            'bt_qty'          => $bt,
            'total_qty'       => ($up + $bt) / 2,
            'reject_qty'      => (int) $angka['reject'],
            'keterangan'      => 'Dicatat lewat Telegram',
        ]);

        ActivityLog::record('create', "Input produksi lewat Telegram: {$produk->name} ({$log->total_qty} unit)", $log);

        return self::pesanSukses($produk, $log, 'dicatat');
    }

    private static function simpanBiasa(Product $produk, array $angka, User $pengguna): string
    {
        $qty = $angka['qty'] ?? $angka['up'];   // "up" tanpa channel dianggap qty biasa

        if ($qty === null) {
            return self::caraPakai('Jumlahnya belum disebutkan.');
        }

        if ($qty < 0 || $angka['reject'] < 0) {
            return self::caraPakai('Jumlah tidak boleh negatif.');
        }

        $lama = self::entriHariIni($produk, $pengguna);

        if ($lama) {
            $lama->update([
                'total_qty'  => $lama->total_qty + $qty,
                'reject_qty' => $lama->reject_qty + (int) $angka['reject'],
            ]);

            return self::pesanSukses($produk, $lama->fresh(), 'ditambahkan ke entri hari ini');
        }

        $log = ProductionLog::create([
            'product_id'      => $produk->id,
            'user_id'         => $pengguna->id,
            'operator_name'   => $pengguna->name,
            // asata: bot berjalan tanpa sesi login, jadi departemen diisi dari akun tertaut.
            'department'      => $pengguna->department,
            'production_date' => now()->toDateString(),
            'up_qty'          => 0,
            'bt_qty'          => 0,
            'total_qty'       => $qty,
            'reject_qty'      => (int) $angka['reject'],
            'keterangan'      => 'Dicatat lewat Telegram',
        ]);

        ActivityLog::record('create', "Input produksi lewat Telegram: {$produk->name} ({$log->total_qty} unit)", $log);

        return self::pesanSukses($produk, $log, 'dicatat');
    }

    /** Aturan gabung yang sama dengan halaman Input Produksi (per departemen di asata). */
    private static function entriHariIni(Product $produk, User $pengguna): ?ProductionLog
    {
        return ProductionLog::where('product_id', $produk->id)
            ->whereDate('production_date', now()->toDateString())
            ->when($pengguna->department !== null, fn ($q) => $q->where('department', $pengguna->department))
            ->when($pengguna->department === null, fn ($q) => $q->whereNull('department'))
            ->first();
    }

    private static function pesanSukses(Product $produk, ProductionLog $log, string $aksi): string
    {
        $baris = [
            '✅ Produksi ' . $aksi . '.',
            '',
            Teks::tebal($produk->series_with_kva ?: $produk->name),
            'Total hari ini: ' . Teks::tebal(Teks::angka((float) $log->total_qty) . ' unit'),
        ];

        if ($produk->isChannel()) {
            $baris[] = "UP {$log->up_qty} · BT {$log->bt_qty}";
        }

        if ($log->reject_qty > 0) {
            $baris[] = 'Reject: ' . Teks::tebal((string) $log->reject_qty);
        }

        return implode("\n", $baris);
    }

    private static function caraPakai(string $sebab): string
    {
        return '❌ ' . Teks::aman($sebab) . "\n\n"
             . Teks::tebal('Cara pakai') . "\n"
             . Teks::kode('/lapor <seri> <qty> [reject]') . "\n"
             . Teks::kode('/lapor <seri> up <n> bt <n> [reject <n>]') . "  (Channel)\n\n"
             . 'Contoh: ' . Teks::kode('/lapor 2601 12 1');
    }
}
