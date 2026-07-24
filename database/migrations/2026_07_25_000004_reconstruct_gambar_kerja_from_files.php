<?php

use App\Models\ActivityLog;
use App\Models\GambarKerja;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * PEMULIHAN DATA: baris tabel `gambar_kerja` hilang akibat `php artisan migrate:fresh`
 * yang pernah dijalankan manual di server (file PDF fisik tetap selamat di
 * storage/app/public/gambar-kerja). Migrasi ini merekonstruksi baris DB dari file
 * yang tersisa + riwayat `activity_logs` (judul · seri(kva)), dipetakan berdasar
 * kedekatan waktu upload.
 *
 * Idempoten & aman:
 *  - Dilewati bila tabel gambar_kerja SUDAH berisi (tak menimpa data valid).
 *  - Dilewati bila folder/file tak ada (mis. environment lokal tanpa file).
 *  - Hanya insert untuk file_path yang belum ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (GambarKerja::count() > 0) return;               // sudah ada data — jangan ganggu

        $dir = storage_path('app/public/gambar-kerja');
        if (! is_dir($dir)) return;

        $files = array_filter(glob($dir . '/*'), 'is_file'); // pdf/gambar (subfolder thumbnails diabaikan)
        if (empty($files)) return;

        // Pengunggah pemulihan: developer (fallback user pertama). Kolom uploaded_by NOT NULL.
        $uploaderId = User::where('role', 'developer')->value('id') ?? User::orderBy('id')->value('id');
        if (! $uploaderId) return; // belum ada user sama sekali

        // Log upload → judul, seri, kva, timestamp
        $logs = ActivityLog::where('description', 'like', 'Upload gambar kerja:%')
            ->orderBy('created_at')->get(['description', 'created_at'])
            ->map(function ($l) {
                $label = trim(str_replace('Upload gambar kerja:', '', $l->description));
                $parts = array_map('trim', explode('·', $label));
                $judul = $parts[0] ?? 'Gambar Kerja';
                $seri = null; $kva = null;
                if (! empty($parts[1])) {
                    if (preg_match('/^(.*?)\((.*?)\)\s*$/', $parts[1], $m)) { $seri = trim($m[1]) ?: null; $kva = trim($m[2]) ?: null; }
                    else { $seri = $parts[1] ?: null; }
                }
                return ['judul' => $judul, 'seri' => $seri, 'kva' => $kva, 'ts' => strtotime($l->created_at)];
            })->all();

        $bestLog = function (int $mtime) use ($logs) {
            $best = null; $bestScore = PHP_INT_MAX;
            foreach ($logs as $lg) {
                $diff  = $lg['ts'] - $mtime;
                $score = $diff >= -5 ? $diff : (abs($diff) + 100000);
                if ($score < $bestScore) { $bestScore = $score; $best = $lg; }
            }
            return $best;
        };

        $rows = [];
        foreach ($files as $f) {
            $ext   = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            $mtime = filemtime($f);
            $lg    = $bestLog($mtime) ?? ['judul' => 'Gambar Kerja (pulihan)', 'seri' => null, 'kva' => null];

            $seri     = $lg['seri'];
            $tahun    = ($seri && preg_match('/^(\d{2})/', $seri, $m)) ? 2000 + (int) $m[1] : null;
            $labelLow = strtolower($lg['judul'] . ' ' . (string) $seri);
            $kategori = str_contains($labelLow, 'swasta') ? 'swasta' : (str_contains($labelLow, 'type') ? 'typetest' : 'pln');

            $rows[] = [
                'judul'         => $lg['judul'],
                'seri'          => $seri,
                'kva'           => $lg['kva'],
                'kategori_seri' => $kategori,
                'tahun'         => $tahun,
                'file_path'     => 'gambar-kerja/' . basename($f),
                'file_type'     => in_array($ext, ['jpg', 'jpeg', 'png']) ? 'image' : 'pdf',
            ];
        }

        // urutan per grup (judul,seri,kva,tahun)
        $counter = [];
        foreach ($rows as $r) {
            $key = "{$r['judul']}|{$r['seri']}|{$r['kva']}|{$r['tahun']}";
            $counter[$key] = ($counter[$key] ?? 0) + 1;

            if (GambarKerja::where('file_path', $r['file_path'])->exists()) continue;

            GambarKerja::create($r + ['urutan' => $counter[$key], 'uploaded_by' => $uploaderId]);
        }
    }

    public function down(): void
    {
        // No-op: data pemulihan, tak dikembalikan.
    }
};
