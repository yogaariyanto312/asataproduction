<?php

namespace App\Filament\Widgets;

use App\Models\ProductionLog;
use Filament\Widgets\ChartWidget;

/**
 * Tren produksi tujuh hari terakhir: total unit dan jumlah entri berdampingan.
 */
class TrenProduksiHarian extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Tren Produksi';

    protected ?string $description = '7 hari terakhir';

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $mulai = now()->subDays(6)->toDateString();

        $baris = ProductionLog::query()
            ->selectRaw('DATE(production_date) as tanggal, SUM(total_qty) as unit, COUNT(*) as entri')
            ->where('production_date', '>=', $mulai)
            ->groupBy('tanggal')
            ->get()
            ->keyBy('tanggal');

        $label = [];
        $unit  = [];
        $entri = [];

        for ($i = 6; $i >= 0; $i--) {
            $hari    = now()->subDays($i);
            $tanggal = $hari->toDateString();
            $row     = $baris->get($tanggal);

            $label[] = $hari->locale('id')->isoFormat('ddd D/M');
            $unit[]  = (float) ($row->unit ?? 0);
            $entri[] = (int) ($row->entri ?? 0);
        }

        return [
            'labels' => $label,
            'datasets' => [
                [
                    'label' => 'Total unit',
                    'data' => $unit,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.18)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Jumlah entri',
                    'data' => $entri,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => false,
                    'tension' => 0.35,
                ],
            ],
        ];
    }
}
