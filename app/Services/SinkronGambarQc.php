<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Sinkron Gambar Kerja dari aplikasi lama (qc.yogaariyanto.com) ke asata. SATU ARAH dan hanya MENAMBAH:
 *  - qc hanya dibaca (SELECT + salin berkas); tidak ada tulisan apa pun ke database atau folder qc;
 *  - baris yang sudah ada di asata (kunci: seri + kVA + urutan) tidak ditimpa; bila kuncinya sama tetapi berkasnya
 *    beda, dicatat sebagai KONFLIK untuk diputuskan manual (tidak diubah otomatis);
 *  - berkas yang sudah ada di asata tidak ditimpa; baris yang dihapus di qc tidak ikut dihapus.
 * Sumber kebenaran logikanya: deployment/clone_gambar_kerja.py (skrip sekali jalan), kini otomatis dan teruji.
 */
class SinkronGambarQc
{
    public const BERKAS_HASIL = 'sinkron-qc.json';
    public const BERKAS_CEK = 'sinkron-qc-cek.json';
    public const BERKAS_ATURAN = 'sinkron-qc-aturan.json';

    public static function otomatis(): bool
    {
        return (self::baca(self::BERKAS_ATURAN)['otomatis'] ?? true) === true;
    }

    public static function aturOtomatis(bool $aktif): void
    {
        if (! Storage::disk('local')->put(self::BERKAS_ATURAN, json_encode(['otomatis' => $aktif]))) {
            throw new \RuntimeException('Pengaturan sinkron tidak dapat disimpan.');
        }
    }

    /** Folder akar aplikasi qc, atau null bila tidak ditemukan/tidak diatur. */
    public static function akarQc(): ?string
    {
        $atur = env('QC_SYNC_PATH');
        if ($atur) {
            return is_dir($atur) ? rtrim($atur, '/\\') : null;
        }

        // Di hosting: aplikasi qc berdampingan dengan asata di akun yang sama.
        $dasar = str_replace('\\', '/', base_path());
        if (str_contains($dasar, 'asataproduction.yogaariyanto.com')) {
            $qc = str_replace('asataproduction.yogaariyanto.com', 'qc.yogaariyanto.com', $dasar);

            return is_dir($qc) ? $qc : null;
        }

        return null;
    }

    /** Sambungan baca-saja ke database qc, dari .env milik qc. */
    public static function sambunganQc(string $akar): ConnectionInterface
    {
        $env = [];
        foreach (@file($akar . '/.env', FILE_IGNORE_NEW_LINES) ?: [] as $baris) {
            if (preg_match('/^(DB_[A-Z_]+)=(.*)$/', trim($baris), $m)) {
                $env[$m[1]] = trim($m[2], "\"' ");
            }
        }
        if (empty($env['DB_DATABASE'])) {
            throw new \RuntimeException('Pengaturan database qc tidak terbaca.');
        }

        config(['database.connections.qc_baca' => [
            'driver' => 'mysql',
            'host' => $env['DB_HOST'] ?? '127.0.0.1',
            'port' => $env['DB_PORT'] ?? '3306',
            'database' => $env['DB_DATABASE'],
            'username' => $env['DB_USERNAME'] ?? 'root',
            'password' => $env['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [],
        ]]);
        DB::purge('qc_baca');
        $koneksi = DB::connection('qc_baca');
        // Pengaman: sesi ini tidak boleh menulis apa pun.
        $koneksi->statement('SET SESSION TRANSACTION READ ONLY');

        return $koneksi;
    }

    /**
     * @param  string  $akarStorageQc  folder storage/app/public milik qc (sumber berkas)
     * @return array{waktu:string, sumber:int, sudah_ada:int, baru:int, konflik:array, berkas_disalin:int, berkas_hilang:array, cek:bool}
     */
    public function jalankan(ConnectionInterface $qc, string $akarStorageQc, bool $cek = false): array
    {
        $kolomAsata = Schema::getColumnListing('gambar_kerja');
        $baris = $qc->table('gambar_kerja')->lazyById(200);
        $disk = Storage::disk('public');
        $kunci = fn ($r) => json_encode([mb_strtolower((string) $r->seri), mb_strtolower((string) $r->kva), (int) $r->urutan]);
        $adaSemua = DB::table('gambar_kerja')->select('id', 'seri', 'kva', 'urutan', 'file_path')->get()->keyBy($kunci);
        $pengguna = array_flip(DB::table('users')->pluck('id')->all());
        $produk = array_flip(DB::table('products')->pluck('id')->all());
        // uploaded_by NOT NULL di asata: pengunggah yang tak dikenal diganti developer pertama (cadangan: pengguna pertama).
        $cadangan = DB::table('users')->orderByRaw("role = 'developer' desc")->orderBy('id')->value('id');

        $hasil = ['waktu' => now()->toDateTimeString(), 'sumber' => 0, 'sudah_ada' => 0, 'baru' => 0, 'konflik' => [], 'berkas_disalin' => 0, 'berkas_hilang' => [], 'jadwal_baru' => 0, 'jadwal_sudah_ada' => 0, 'cek' => $cek];

        foreach ($baris as $r) {
            $hasil['sumber']++;
            $key = $kunci($r);
            $r = (array) $r;
            $ada = $adaSemua->get($key);

            if ($ada) {
                if ($ada->file_path === $r['file_path']) {
                    $hasil['sudah_ada']++;
                } else {
                    $hasil['konflik'][] = "{$r['judul']} | seri {$r['seri']} | {$r['kva']} kVA | urutan {$r['urutan']}: asata memakai berkas berbeda dari qc";
                    continue;
                }
            }

            // Salin berkas yang belum ada (tidak menimpa).
            foreach (array_filter([$r['file_path'] ?? null, $r['thumbnail_path'] ?? null]) as $rel) {
                if ($disk->exists($rel)) {
                    continue;
                }
                if (! $this->salinBerkas($akarStorageQc, $rel, $cek)) {
                    $hasil['berkas_hilang'][] = $rel;
                    if ($rel === $r['file_path']) {
                        continue 2;
                    }
                    continue;
                }
                $hasil['berkas_disalin']++;
            }

            if ($ada) {
                continue;
            }
            $hasil['baru']++;
            if ($cek) {
                continue;
            }

            $data = array_intersect_key($r, array_flip($kolomAsata));
            unset($data['id']);
            // Referensi ke tabel lain milik qc belum tentu ada di asata (product_id boleh kosong).
            if (empty($data['uploaded_by']) || ! isset($pengguna[$data['uploaded_by']])) {
                $data['uploaded_by'] = $cadangan;
            }
            if (! empty($data['product_id']) && ! isset($produk[$data['product_id']])) {
                $data['product_id'] = null;
            }
            DB::table('gambar_kerja')->insert($data);
            $adaSemua->put($key, (object) $r);
        }

        $this->jadwal($qc, $akarStorageQc, $cek, $hasil);

        return $hasil;
    }

    /**
     * Foto jadwal Target Produksi (satu foto per minggu, kunci target_date). Hanya minggu ini dan sesudahnya
     * (yang lama toh dihapus tiap Senin oleh jadwal:clear). Minggu yang sudah punya foto tidak ditimpa.
     */
    private function jadwal(ConnectionInterface $qc, string $akarStorageQc, bool $cek, array &$hasil): void
    {
        if (! $qc->getSchemaBuilder()->hasTable('schedule_photos')) {
            return;
        }
        $disk = Storage::disk('public');
        $senin = now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();

        foreach ($qc->table('schedule_photos')->whereDate('target_date', '>=', $senin)->orderBy('target_date')->get() as $r) {
            $r = (array) $r;
            $tgl = substr((string) $r['target_date'], 0, 10);
            $ada = DB::table('schedule_photos')->whereDate('target_date', $tgl)->first();
            if ($ada) {
                if ($ada->file_path === $r['file_path']) {
                    $hasil['jadwal_sudah_ada']++;
                } else {
                    $hasil['konflik'][] = "Foto jadwal minggu {$tgl}: asata memakai berkas berbeda dari qc";
                    continue;
                }
            }

            $rel = $r['file_path'];
            if (! $disk->exists($rel)) {
                if (! $this->salinBerkas($akarStorageQc, $rel, $cek)) {
                    $hasil['berkas_hilang'][] = $rel;

                    continue;
                }
                $hasil['berkas_disalin']++;
            }

            if ($ada) {
                continue;
            }
            $hasil['jadwal_baru']++;
            if (! $cek) {
                $uploader = ! empty($r['uploaded_by']) && DB::table('users')->where('id', $r['uploaded_by'])->exists() ? $r['uploaded_by'] : null;
                DB::table('schedule_photos')->insert(['target_date' => $tgl, 'file_path' => $rel, 'uploaded_by' => $uploader, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /** Terbitkan hanya salinan utuh; berkas sementara dibuang bila sumber masih ditulis atau copy gagal. */
    protected function salinBerkas(string $akar, string $rel, bool $cek): bool
    {
        if (preg_match('~(^/|^[a-z]:|\\\\|(^|/)\.\.(/|$))~i', $rel)) {
            throw new \RuntimeException('Lokasi berkas sumber tidak diizinkan.');
        }
        $dasar = realpath($akar);
        $sumber = realpath($akar . '/' . $rel);
        if (! $dasar || ! $sumber || ! str_starts_with(str_replace('\\', '/', $sumber), str_replace('\\', '/', $dasar) . '/') || ! is_file($sumber)) {
            return false;
        }
        clearstatcache(true, $sumber);
        $ukuran = filesize($sumber);
        if ($ukuran === false || $ukuran === 0) {
            return false;
        }
        if ($cek) {
            return true;
        }
        $disk = Storage::disk('public');
        $disk->makeDirectory(dirname($rel));
        $tujuan = $disk->path($rel);
        $tmp = tempnam(dirname($tujuan), '.sinkron-');
        if ($tmp === false) {
            throw new \RuntimeException('Salinan sementara tidak dapat dibuat.');
        }
        try {
            if (! copy($sumber, $tmp)) {
                throw new \RuntimeException('Berkas gagal disalin.');
            }
            clearstatcache(true, $sumber);
            if (filesize($tmp) !== $ukuran || filesize($sumber) !== $ukuran) {
                return false;
            }
            if (is_file($tujuan)) {
                return true;
            }
            if (! rename($tmp, $tujuan)) {
                throw new \RuntimeException('Salinan gagal diterbitkan.');
            }
            return true;
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    /** Hasil "Cek dulu" disimpan terpisah dari hasil sinkron sungguhan. */
    public static function simpanHasil(array $hasil): void
    {
        Storage::disk('local')->put(empty($hasil['cek']) ? self::BERKAS_HASIL : self::BERKAS_CEK, json_encode($hasil, JSON_UNESCAPED_UNICODE));
    }

    public static function hasilTerakhir(): ?array
    {
        return self::baca(self::BERKAS_HASIL);
    }

    public static function hasilCek(): ?array
    {
        return self::baca(self::BERKAS_CEK);
    }

    private static function baca(string $berkas): ?array
    {
        $disk = Storage::disk('local');

        return $disk->exists($berkas) ? json_decode($disk->get($berkas), true) : null;
    }
}
