<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Services\SinkronGambarQc;

/** Tombol "Sinkronkan sekarang" / "Cek dulu" di Settings (khusus developer). Logikanya ada di perintah gambar:sinkron-qc. */
class SinkronQcController extends Controller
{
    public static function data(): array
    {
        return [
            'url' => route('developer.sinkron-qc'),
            'aturanUrl' => route('developer.sinkron-qc.aturan'),
            'aktif' => (bool) SinkronGambarQc::akarQc(),
            'otomatis' => SinkronGambarQc::otomatis(),
            'interval_menit' => 1,
            'terakhir' => SinkronGambarQc::hasilTerakhir(),
            'cek' => SinkronGambarQc::hasilCek(),
        ];
    }

    public function status()
    {
        return response()->json(self::data());
    }

    public function aturan(Request $request)
    {
        $data = $request->validate(['otomatis' => ['required', 'boolean']]);
        SinkronGambarQc::aturOtomatis((bool) $data['otomatis']);
        return back()->with('success', $data['otomatis'] ? 'Sinkron otomatis aktif, sumber diperiksa tiap menit.' : 'Sinkron otomatis dinonaktifkan. Sinkron manual tetap tersedia.');
    }

    public function jalankan(Request $request)
    {
        @set_time_limit(300);
        $cek = $request->boolean('cek');

        $kode = Artisan::call('gambar:sinkron-qc', $cek ? ['--cek' => true] : []);
        $keluar = trim(Artisan::output());
        $h = $cek ? SinkronGambarQc::hasilCek() : SinkronGambarQc::hasilTerakhir();

        // Pesan toast satu kalimat; rincian (konflik dll.) tampil di kartu Settings.
        if ($kode !== 0 || ! $h || isset($h['galat'])) {
            return back()->with('error', $kode !== 0 ? ($keluar ?: 'Sinkron gagal.') : 'Hasil sinkron belum tersedia.');
        }
        $pesan = sprintf('%s: %d gambar dan %d foto jadwal %s, %d konflik.', $cek ? 'Cek selesai' : 'Sinkron selesai', $h['baru'], $h['jadwal_baru'] ?? 0, $cek ? 'akan ditambah' : 'ditambah', count($h['konflik']));

        return back()->with('success', $pesan);
    }
}
