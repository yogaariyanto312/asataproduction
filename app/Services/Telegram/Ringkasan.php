<?php

namespace App\Services\Telegram;

use App\Models\ProductionLog;
use App\Models\ProductionTarget;
use App\Models\Replacement;
use App\Models\SchedulePhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Jawaban untuk perintah yang hanya membaca data.
 *
 * Angkanya sengaja dihitung dengan rumus yang sama persis dengan yang dipakai
 * layar aplikasi (mis. ProductionTarget::actualProduced) — kalau bot dan layar
 * menyebut angka berbeda, yang terjadi bukan "dua sudut pandang", melainkan
 * hilangnya kepercayaan pada keduanya.
 */
class Ringkasan
{
    /** Batas daftar supaya pesannya tidak melampaui batas panjang Telegram. */
    private const MAKS_BARIS = 15;

    public static function produksiHariIni(?string $tanggal = null): string
    {
        $tanggal = $tanggal ?? now()->toDateString();

        $logs = ProductionLog::with('product.category')
            ->whereDate('production_date', $tanggal)
            ->get();

        $judul = '📊 ' . Teks::tebal('Produksi ' . Tanggal::pendek($tanggal));

        if ($logs->isEmpty()) {
            return $judul . "\n\nBelum ada data produksi.";
        }

        $totalQty    = (float) $logs->sum('total_qty');
        $totalReject = (int) $logs->sum('reject_qty');
        $rate        = self::rejectRate($totalQty, $totalReject);

        $baris = [$judul, ''];

        foreach ($logs->groupBy(fn ($l) => $l->product?->category?->name ?? 'Lainnya') as $kategori => $isi) {
            $qty    = (float) $isi->sum('total_qty');
            $reject = (int) $isi->sum('reject_qty');

            $baris[] = '📦 ' . Teks::tebal($kategori) . ' — ' . Teks::angka($qty) . ' unit'
                     . ($reject > 0 ? " (✕{$reject})" : '');
        }

        $baris[] = '';
        $baris[] = 'Total: ' . Teks::tebal(Teks::angka($totalQty) . ' unit');
        $baris[] = 'Reject: ' . Teks::tebal(Teks::angka($totalReject)) . " · {$rate}% " . Teks::lampu($rate);

        return implode("\n", $baris);
    }

    public static function targetAktif(): string
    {
        $targets = ProductionTarget::with('product')->whereNotNull('product_id')->get();

        if ($targets->isEmpty()) {
            return '🎯 ' . Teks::tebal('Target Produksi') . "\n\nBelum ada target yang diatur.";
        }

        // Satu query untuk semua produk sekaligus — bukan satu per target.
        $kumulatif = ProductionLog::select('product_id', DB::raw('SUM(total_qty) as total'))
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $baris   = ['🎯 ' . Teks::tebal('Target Produksi'), ''];
        $selesai = 0;

        $terurut = $targets->sortBy(fn ($t) => $t->actualProduced((int) ($kumulatif[$t->product_id] ?? 0)) >= $t->target_qty);

        foreach ($terurut->take(self::MAKS_BARIS) as $t) {
            $aktual = $t->actualProduced((int) ($kumulatif[$t->product_id] ?? 0));
            $target = (int) $t->target_qty;
            $persen = $target > 0 ? min(round($aktual / $target * 100), 100) : 0;
            $tuntas = $aktual >= $target;

            if ($tuntas) $selesai++;

            $nama = $t->product?->series_with_kva ?: ($t->product?->name ?? '-');

            $baris[] = ($tuntas ? '✅ ' : '▫️ ') . Teks::tebal($nama);
            $baris[] = "   {$aktual} / {$target} ({$persen}%)" . ($tuntas ? '' : ' · sisa ' . ($target - $aktual));
        }

        if ($targets->count() > self::MAKS_BARIS) {
            $baris[] = '';
            $baris[] = '_' . ($targets->count() - self::MAKS_BARIS) . ' target lainnya tidak ditampilkan._';
        }

        $baris[] = '';
        $baris[] = "Tuntas: " . Teks::tebal("{$selesai} dari {$targets->count()}");

        return implode("\n", $baris);
    }

    public static function rejectTertinggi(?string $tanggal = null): string
    {
        $tanggal = $tanggal ?? now()->toDateString();

        $logs = ProductionLog::with('product')
            ->whereDate('production_date', $tanggal)
            ->where('reject_qty', '>', 0)
            ->get();

        $judul = '⚠️ ' . Teks::tebal('Reject ' . Tanggal::pendek($tanggal));

        if ($logs->isEmpty()) {
            return $judul . "\n\nTidak ada reject hari ini. 🟢";
        }

        $perProduk = $logs
            ->groupBy(fn ($l) => $l->product?->series_with_kva ?: ($l->product?->name ?? '-'))
            ->map(fn ($isi) => [
                'reject' => (int) $isi->sum('reject_qty'),
                'qty'    => (float) $isi->sum('total_qty'),
            ])
            ->sortByDesc('reject');

        $baris = [$judul, ''];

        foreach ($perProduk->take(self::MAKS_BARIS) as $nama => $angka) {
            $rate = self::rejectRate($angka['qty'], $angka['reject']);

            $baris[] = Teks::lampu($rate) . ' ' . Teks::tebal($nama);
            $baris[] = "   {$angka['reject']} reject dari " . Teks::angka($angka['qty'] + $angka['reject']) . " ({$rate}%)";
        }

        return implode("\n", $baris);
    }

    public static function penggantiBelumSelesai(): string
    {
        $daftar = Replacement::with('product')
            ->whereNull('completed_at')
            ->orderBy('replacement_date')
            ->get();

        $judul = '🔧 ' . Teks::tebal('Barang Pengganti Belum Selesai');

        if ($daftar->isEmpty()) {
            return $judul . "\n\nTidak ada yang tertunda. 🟢";
        }

        $baris = [$judul, ''];

        foreach ($daftar->take(self::MAKS_BARIS) as $r) {
            $nama = $r->product?->series_with_kva ?: ($r->product?->name ?? '-');

            $baris[] = '▫️ ' . Teks::tebal($nama) . ' × ' . Teks::angka((float) $r->qty);
            $baris[] = '   ' . Tanggal::pendek($r->replacement_date)
                     . ($r->recipient ? ' · ' . Teks::aman($r->recipient) : '');
        }

        if ($daftar->count() > self::MAKS_BARIS) {
            $baris[] = '';
            $baris[] = '_' . ($daftar->count() - self::MAKS_BARIS) . ' lainnya tidak ditampilkan._';
        }

        $baris[] = '';
        $baris[] = 'Total tertunda: ' . Teks::tebal((string) $daftar->count());

        return implode("\n", $baris);
    }

    /**
     * Jadwal untuk sebuah tanggal, dikirim sebagai berkasnya langsung supaya
     * tidak perlu membuka aplikasi hanya untuk melihat selembar jadwal.
     *
     * @return array<int,array> balasan siap kirim
     */
    public static function jadwal(?string $tanggal): array
    {
        // Selalu dibulatkan ke Senin: foto jadwal berlaku SEMINGGU dan
        // disimpan dengan kunci tanggal Senin, sama seperti halaman
        // Target Produksi. Lihat Tanggal::awalMinggu().
        $tanggal = Tanggal::awalMinggu($tanggal);

        // whereDate, bukan where: kolomnya di-cast 'date' sehingga nilainya
        // bisa tersimpan lengkap dengan jam di sebagian database.
        $foto    = SchedulePhoto::whereDate('target_date', $tanggal)->first();
        $judul   = '📅 ' . Teks::tebal('Jadwal ' . Tanggal::rentangMinggu($tanggal));

        if (! $foto) {
            return [[
                'jenis' => 'teks',
                'teks'  => $judul . "\n\nBelum ada jadwal untuk tanggal ini.\n\n"
                         . 'Unggah dengan ' . Teks::kode('/jadwal upload ' . $tanggal),
            ]];
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($foto->file_path)) {
            return [[
                'jenis' => 'teks',
                'teks'  => $judul . "\n\nBerkasnya tercatat tapi sudah tidak ada di penyimpanan.",
            ]];
        }

        $pdf = str_ends_with(strtolower($foto->file_path), '.pdf');

        return [[
            'jenis' => $pdf ? 'dokumen' : 'foto',
            'isi'   => $disk->get($foto->file_path),
            'nama'  => basename($foto->file_path),
            'teks'  => $judul,
        ]];
    }

    /** Rumusnya disamakan dengan laporan harian: reject / (qty + reject). */
    private static function rejectRate(float $qty, int $reject): float
    {
        $penyebut = $qty + $reject;

        return $penyebut > 0 ? round($reject / $penyebut * 100, 1) : 0.0;
    }
}
