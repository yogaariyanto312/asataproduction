<?php

namespace App\Services\Telegram;

use App\Models\BotSetting;
use App\Models\Note;
use App\Models\ProductionLog;
use App\Models\ProductionTarget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pesan yang dikirim bot atas inisiatif sendiri, tanpa diminta.
 *
 * Semua pembangun pesan di sini mengembalikan teks (atau null bila memang tidak
 * ada yang perlu dikabarkan) dan TIDAK mengirim apa pun — pengirimannya
 * dikerjakan Penyiar. Dengan begitu isi pesannya bisa diuji tanpa jaringan, dan
 * "tidak ada yang perlu dikirim" bisa dibedakan dari "gagal mengirim".
 */
class Pemberitahuan
{
    /**
     * Target di bawah angka ini saat pengecekan sore dianggap perlu diingatkan.
     * Dipilih 70%: masih mungkin dikejar, tapi sudah cukup jauh untuk pantas
     * diberitahukan.
     */
    public const AMBANG_TARGET = 70;

    /* ───────────────────────── Ringkasan pagi ───────────────────────── */

    public static function ringkasanPagi(): ?string
    {
        $targets = self::targetBelumTuntas();
        $catatan = self::catatanJatuhTempo();

        // Kalau tidak ada target tertunggak dan tidak ada catatan jatuh tempo,
        // pesannya tidak berisi apa pun yang perlu ditindaklanjuti — lebih baik
        // diam daripada menambah satu notifikasi rutin yang lalu diabaikan.
        if ($targets->isEmpty() && $catatan->isEmpty()) {
            return null;
        }

        $baris = [
            '🌅 ' . Teks::tebal('Ringkasan Pagi'),
            Teks::aman(Tanggal::panjang(now()->toDateString())),
            '',
        ];

        if ($targets->isNotEmpty()) {
            $baris[] = Teks::tebal('Target yang belum tuntas');

            foreach ($targets->take(8) as $t) {
                $baris[] = '▫️ ' . Teks::aman($t['nama']) . " — sisa {$t['sisa']} dari {$t['target']}";
            }

            $baris[] = '';
        }

        if ($catatan->isNotEmpty()) {
            $baris[] = Teks::tebal('Catatan jatuh tempo hari ini');

            foreach ($catatan->take(8) as $c) {
                $untuk   = $c->targetUser?->name ?? $c->user?->name;
                $baris[] = '▫️ ' . Teks::aman($c->title) . ($untuk ? ' — ' . Teks::aman($untuk) : '');
            }
        }

        return implode("\n", $baris);
    }

    /* ───────────────────────── Pengingat pribadi ───────────────────────── */

    /**
     * Catatan jatuh tempo, dikirim ke masing-masing orangnya.
     *
     * @return array<int,array{chat_id:string,teks:string}>
     */
    public static function pengingatCatatan(): array
    {
        $catatan = self::catatanJatuhTempo();

        if ($catatan->isEmpty()) {
            return [];
        }

        $tertaut = User::whereNotNull('telegram_user_id')
            ->where('is_active', true)
            ->whereIn('role', PenautanAkun::PERAN)
            ->pluck('telegram_user_id', 'id');

        $pesan = [];

        // Dikelompokkan per orang: satu pesan berisi semua catatannya, bukan
        // satu notifikasi per catatan.
        foreach ($catatan->groupBy(fn ($c) => $c->target_user_id ?: $c->user_id) as $userId => $miliknya) {
            $chatId = $tertaut[$userId] ?? null;

            if (! $chatId) {
                continue;   // orangnya belum menautkan Telegram
            }

            $baris = ['🔔 ' . Teks::tebal('Catatan jatuh tempo hari ini'), ''];

            foreach ($miliknya as $c) {
                $baris[] = '▫️ ' . Teks::tebal($c->title);

                if ($c->content) {
                    $baris[] = '   ' . Teks::aman(str($c->content)->stripTags()->limit(100));
                }
            }

            $pesan[] = ['chat_id' => (string) $chatId, 'teks' => implode("\n", $baris)];
        }

        return $pesan;
    }

    /* ───────────────────────── Peringatan target ───────────────────────── */

    public static function targetMeleset(): ?string
    {
        $tertinggal = self::targetBelumTuntas()
            ->filter(fn ($t) => $t['persen'] < self::AMBANG_TARGET);

        if ($tertinggal->isEmpty()) {
            return null;
        }

        $baris = [
            '⏰ ' . Teks::tebal('Target masih tertinggal'),
            'Masih ada waktu hari ini untuk mengejar.',
            '',
        ];

        foreach ($tertinggal->take(10) as $t) {
            $baris[] = '▫️ ' . Teks::tebal($t['nama']);
            $baris[] = "   {$t['aktual']} / {$t['target']} ({$t['persen']}%) · sisa {$t['sisa']}";
        }

        return implode("\n", $baris);
    }

    /* ───────────────────────── Laporan mingguan ───────────────────────── */

    public static function laporanMingguan(): ?string
    {
        $akhir     = now()->subDay()->endOfDay();          // sampai kemarin
        $mulai     = now()->subDays(7)->startOfDay();
        $mulaiLalu = now()->subDays(14)->startOfDay();
        $akhirLalu = now()->subDays(8)->endOfDay();

        $iniQty    = self::jumlahQty($mulai, $akhir);
        $laluQty   = self::jumlahQty($mulaiLalu, $akhirLalu);
        $iniReject = self::jumlahReject($mulai, $akhir);

        if ($iniQty <= 0 && $laluQty <= 0) {
            return null;
        }

        $selisih = $laluQty > 0 ? round(($iniQty - $laluQty) / $laluQty * 100, 1) : null;
        $arah    = match (true) {
            $selisih === null => 'belum ada pembanding',
            $selisih > 0      => "naik {$selisih}% dari minggu lalu",
            $selisih < 0      => 'turun ' . abs($selisih) . '% dari minggu lalu',
            default           => 'sama dengan minggu lalu',
        };

        $rate = ($iniQty + $iniReject) > 0
            ? round($iniReject / ($iniQty + $iniReject) * 100, 1)
            : 0.0;

        $baris = [
            '🗓️ ' . Teks::tebal('Laporan Mingguan'),
            Teks::aman(Tanggal::pendek($mulai->toDateString()) . ' – ' . Tanggal::pendek($akhir->toDateString())),
            '',
            'Total: ' . Teks::tebal(Teks::angka($iniQty) . ' unit'),
            'Minggu lalu: ' . Teks::angka($laluQty) . ' unit (' . Teks::aman($arah) . ')',
            'Reject: ' . Teks::tebal(Teks::angka($iniReject)) . " · {$rate}% " . Teks::lampu($rate),
        ];

        $terburuk = self::produkPalingBermasalah($mulai, $akhir);

        if ($terburuk) {
            $baris[] = '';
            $baris[] = Teks::tebal('Paling banyak reject');
            $baris[] = '▫️ ' . Teks::aman($terburuk['nama']) . " — {$terburuk['reject']} reject";
        }

        return implode("\n", $baris);
    }

    /* ───────────────────────── Bahan bersama ───────────────────────── */

    /** @return \Illuminate\Support\Collection<int,array> */
    private static function targetBelumTuntas()
    {
        $targets = ProductionTarget::with('product')->whereNotNull('product_id')->get();

        if ($targets->isEmpty()) {
            return collect();
        }

        $kumulatif = ProductionLog::select('product_id', DB::raw('SUM(total_qty) as total'))
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        return $targets
            ->map(function ($t) use ($kumulatif) {
                $aktual = $t->actualProduced((int) ($kumulatif[$t->product_id] ?? 0));
                $target = (int) $t->target_qty;

                return [
                    'nama'   => $t->product?->series_with_kva ?: ($t->product?->name ?? '-'),
                    'aktual' => $aktual,
                    'target' => $target,
                    'sisa'   => max($target - $aktual, 0),
                    'persen' => $target > 0 ? (int) round($aktual / $target * 100) : 100,
                ];
            })
            ->filter(fn ($t) => $t['aktual'] < $t['target'])
            ->sortBy('persen')
            ->values();
    }

    /** Catatan yang jatuh tempo hari ini atau sudah lewat, dan belum selesai. */
    private static function catatanJatuhTempo()
    {
        return Note::with(['user:id,name', 'targetUser:id,name'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', now()->toDateString())
            ->where('is_done', false)
            ->orderBy('due_date')
            ->get();
    }

    private static function jumlahQty($mulai, $akhir): float
    {
        return (float) ProductionLog::whereBetween('production_date', [$mulai, $akhir])->sum('total_qty');
    }

    private static function jumlahReject($mulai, $akhir): int
    {
        return (int) ProductionLog::whereBetween('production_date', [$mulai, $akhir])->sum('reject_qty');
    }

    private static function produkPalingBermasalah($mulai, $akhir): ?array
    {
        $log = ProductionLog::with('product')
            ->whereBetween('production_date', [$mulai, $akhir])
            ->where('reject_qty', '>', 0)
            ->get()
            ->groupBy(fn ($l) => $l->product?->series_with_kva ?: ($l->product?->name ?? '-'))
            ->map(fn ($isi) => (int) $isi->sum('reject_qty'))
            ->sortDesc();

        if ($log->isEmpty()) {
            return null;
        }

        return ['nama' => $log->keys()->first(), 'reject' => $log->first()];
    }

    /** Chat tujuan pesan rutin: grup laporan, kalau kosong ikut grup log. */
    public static function chatLaporan(?BotSetting $setting = null): ?string
    {
        $setting = $setting ?? BotSetting::instance();

        return $setting->telegram_report_chat_id ?: $setting->telegram_chat_id;
    }
}
