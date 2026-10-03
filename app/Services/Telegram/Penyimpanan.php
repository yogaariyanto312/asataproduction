<?php

namespace App\Services\Telegram;

use App\Models\ActivityLog;
use App\Models\GambarKerja;
use App\Models\SchedulePhoto;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menyimpan berkas yang dikirim lewat Telegram.
 *
 * Bentuk penyimpanannya dibuat sama persis dengan yang dipakai halaman web
 * (folder, nama berkas, kolom yang diisi), supaya berkas dari bot tidak jadi
 * warga kelas dua yang perlu penanganan khusus di tempat lain.
 */
class Penyimpanan
{
    public static function jadwal(string $isi, string $ekstensi, string $tanggal, User $pengguna): string
    {
        $disk = Storage::disk('public');
        $path = "schedule-photos/{$tanggal}/tg_" . time() . '.' . $ekstensi;

        // Satu tanggal hanya punya satu jadwal; yang lama dibuang supaya tidak
        // menumpuk berkas yatim di penyimpanan.
        // whereDate, bukan where: kolomnya di-cast 'date' sehingga nilainya bisa
        // tersimpan lengkap dengan jam di sebagian database, dan pencocokan
        // string biasa meleset tanpa suara.
        $baris = SchedulePhoto::whereDate('target_date', $tanggal)->first();

        if ($baris) {
            $disk->delete($baris->file_path);
        }

        $disk->put($path, $isi);

        ($baris ?? new SchedulePhoto(['target_date' => $tanggal]))
            ->fill(['target_date' => $tanggal, 'file_path' => $path, 'uploaded_by' => $pengguna->id])
            ->save();

        ActivityLog::record('create', "Unggah jadwal lewat Telegram untuk {$tanggal}");

        return '✅ Jadwal tersimpan.' . "\n\n"
             . 'Tanggal: ' . Teks::tebal(Tanggal::panjang($tanggal)) . "\n"
             . 'Bisa dilihat di menu ' . Teks::tebal('Target Produksi') . ' pada tanggal tersebut.';
    }

    /**
     * Simpan satu berkas gambar kerja.
     *
     * $sesi memuat judul, kategori, seri, kva, tahun — bentuknya disamakan
     * dengan yang dihasilkan halaman web supaya berkas dari Telegram masuk ke
     * kelompok yang sama, bukan jadi kumpulan terpisah.
     */
    public static function gambarKerja(string $isi, string $ekstensi, array $sesi, User $pengguna): string
    {
        $judul    = $sesi['judul'];
        $seri     = $sesi['seri']  ?: null;
        $kva      = $sesi['kva']   ?: null;
        $tahun    = $sesi['tahun'] ?: null;
        $kategori = $sesi['kategori'] ?? 'pln';

        $disk = Storage::disk('public');
        $path = 'gambar-kerja/' . Str::uuid() . '.' . $ekstensi;

        $disk->put($path, $isi);

        // Urutan meneruskan berkas yang sudah ada pada KELOMPOK yang sama
        // (judul + seri + kva + tahun), persis seperti unggahan lewat web.
        $urutan = GambarKerja::where('judul', $judul)
            ->where('seri', $seri)
            ->where('kva', $kva)
            ->where('tahun', $tahun)
            ->max('urutan') + 1;

        GambarKerja::create([
            'judul'         => $judul,
            'seri'          => $seri,
            'kva'           => $kva,
            'tahun'         => $tahun,
            'kategori_seri' => $kategori,
            'file_path'     => $path,
            'file_type'     => $ekstensi === 'pdf' ? 'pdf' : 'image',
            'keterangan'    => 'Diunggah lewat Telegram',
            'uploaded_by'   => $pengguna->id,
            'urutan'        => $urutan,
        ]);

        $label = $judul . ($seri ? ' · ' . $seri . ($kva ? "({$kva})" : '') : '');
        ActivityLog::record('create', "Upload gambar kerja lewat Telegram: {$label}");

        $baris = [
            '✅ Gambar kerja tersimpan.',
            '',
            'Judul: ' . Teks::tebal($judul),
            'Kategori: ' . Teks::tebal(\App\Services\Telegram\BotPerintah::KATEGORI[$kategori] ?? $kategori),
        ];

        if ($seri) {
            $baris[] = 'Seri: ' . Teks::tebal($seri) . ($kva ? ' · ' . Teks::tebal($kva . ' KVA') : '');
        }

        if ($tahun) {
            $baris[] = 'Tahun: ' . Teks::tebal((string) $tahun);
        }

        $baris[] = 'Berkas ke-' . $urutan . ' pada kelompok ini.';
        $baris[] = '';
        $baris[] = '<i>Kirim berkas lagi untuk menambah, atau ' . Teks::kode('/batal') . ' untuk selesai.</i>';

        return implode("
", $baris);
    }
}
