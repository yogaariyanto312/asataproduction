<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\ProductionLog;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Kartu ringkasan produksi — memakai StatsOverviewWidget bawaan Filament,
 * lengkap dengan grafik mini di dalam kartu ("Adding a chart to a stat") dan
 * penanda naik/turun dibanding periode sebelumnya.
 */
class RingkasanProduksi extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Ringkasan Produksi';

    protected int | array | null $columns = 4;

    /** Total unit per hari untuk 7 hari terakhir, termasuk hari yang kosong. */
    private function tujuhHari(string $kolom = 'total_qty'): array
    {
        $mulai = now()->subDays(6)->startOfDay();

        $baris = ProductionLog::query()
            ->selectRaw('DATE(production_date) as tanggal, SUM(' . $kolom . ') as jumlah')
            ->where('production_date', '>=', $mulai->toDateString())
            ->groupBy('tanggal')
            ->pluck('jumlah', 'tanggal');

        $deret = [];
        for ($i = 6; $i >= 0; $i--) {
            $tanggal = now()->subDays($i)->toDateString();
            $deret[] = (float) ($baris[$tanggal] ?? 0);
        }

        return $deret;
    }

    /** Selisih dua angka sebagai persen, null bila pembandingnya nol. */
    private function selisih(float $sekarang, float $sebelum): ?float
    {
        if ($sebelum <= 0) {
            return null;
        }

        return round((($sekarang - $sebelum) / $sebelum) * 100, 1);
    }

    /** Ubah selisih persen menjadi teks + ikon + warna kartu. */
    private function tren(?float $persen, string $periode): array
    {
        if ($persen === null) {
            return ['Belum ada pembanding ' . $periode, null, 'gray'];
        }

        if (abs($persen) < 0.05) {
            return ['Sama dengan ' . $periode, Heroicon::Minus, 'gray'];
        }

        $naik = $persen > 0;

        return [
            number_format(abs($persen), 1, ',', '.') . '% ' . ($naik ? 'naik' : 'turun') . ' dari ' . $periode,
            $naik ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown,
            $naik ? 'success' : 'danger',
        ];
    }

    private function angka(float $nilai): string
    {
        return number_format($nilai, fmod($nilai, 1) == 0.0 ? 0 : 1, ',', '.');
    }

    protected function getStats(): array
    {
        $hariIni  = now()->toDateString();
        $kemarin  = now()->subDay()->toDateString();

        $totalHariIni = (float) ProductionLog::whereDate('production_date', $hariIni)->sum('total_qty');
        $entriHariIni = ProductionLog::whereDate('production_date', $hariIni)->count();
        $totalKemarin = (float) ProductionLog::whereDate('production_date', $kemarin)->sum('total_qty');

        [$teksHarian, $ikonHarian, $warnaHarian] = $this->tren(
            $this->selisih($totalHariIni, $totalKemarin),
            'kemarin',
        );

        // Bulan ini vs bulan lalu
        $awalBulan     = now()->startOfMonth();
        $awalBulanLalu = now()->subMonthNoOverflow()->startOfMonth();

        $totalBulanIni = (float) ProductionLog::whereBetween('production_date', [
            $awalBulan->toDateString(), now()->endOfMonth()->toDateString(),
        ])->sum('total_qty');

        $totalBulanLalu = (float) ProductionLog::whereBetween('production_date', [
            $awalBulanLalu->toDateString(),
            $awalBulanLalu->copy()->endOfMonth()->toDateString(),
        ])->sum('total_qty');

        [$teksBulanan, $ikonBulanan, $warnaBulanan] = $this->tren(
            $this->selisih($totalBulanIni, $totalBulanLalu),
            'bulan lalu',
        );

        // Grafik mini bulanan: enam bulan terakhir
        $deretBulanan = [];
        for ($i = 5; $i >= 0; $i--) {
            $bulan = now()->subMonthsNoOverflow($i);
            $deretBulanan[] = (float) ProductionLog::whereBetween('production_date', [
                $bulan->copy()->startOfMonth()->toDateString(),
                $bulan->copy()->endOfMonth()->toDateString(),
            ])->sum('total_qty');
        }

        // Reject hari ini
        $rejectHariIni = (float) ProductionLog::whereDate('production_date', $hariIni)->sum('reject_qty');
        $persenReject  = ($totalHariIni + $rejectHariIni) > 0
            ? round(($rejectHariIni / ($totalHariIni + $rejectHariIni)) * 100, 1)
            : 0.0;

        $produkAktif = Product::where('is_active', true)->count();
        $seriAktif   = Product::where('is_active', true)
            ->whereNotNull('series')->where('series', '!=', '')
            ->distinct()->count(DB::raw('series'));

        return [
            Stat::make('Total Unit Hari Ini', $this->angka($totalHariIni))
                ->description($entriHariIni . ' entri · ' . $teksHarian)
                ->descriptionIcon($ikonHarian)
                ->descriptionColor($warnaHarian)
                ->color($warnaHarian)
                ->chart($this->tujuhHari())
                ->icon(Heroicon::Squares2x2),

            Stat::make('Total Bulan Ini', $this->angka($totalBulanIni))
                ->description(now()->locale('id')->isoFormat('MMMM YYYY') . ' · ' . $teksBulanan)
                ->descriptionIcon($ikonBulanan)
                ->descriptionColor($warnaBulanan)
                ->color($warnaBulanan)
                ->chart($deretBulanan)
                ->icon(Heroicon::Cube),

            Stat::make('Reject Hari Ini', $this->angka($rejectHariIni))
                ->description(number_format($persenReject, 1, ',', '.') . '% dari produksi hari ini')
                ->descriptionIcon($rejectHariIni > 0 ? Heroicon::ExclamationTriangle : Heroicon::CheckCircle)
                ->descriptionColor($rejectHariIni > 0 ? 'warning' : 'success')
                ->color($rejectHariIni > 0 ? 'warning' : 'success')
                ->chart($this->tujuhHari('reject_qty'))
                ->icon(Heroicon::ExclamationTriangle),

            Stat::make('Produk Aktif', number_format($produkAktif, 0, ',', '.'))
                ->description($seriAktif . ' nomor seri terdaftar')
                ->descriptionIcon(Heroicon::Tag)
                ->color('info')
                ->icon(Heroicon::Tag),
        ];
    }
}
