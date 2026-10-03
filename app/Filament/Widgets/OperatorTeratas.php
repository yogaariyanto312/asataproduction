<?php

namespace App\Filament\Widgets;

use App\Models\ProductionLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Lima operator dengan hasil terbanyak bulan berjalan.
 */
class OperatorTeratas extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Operator Teratas';

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getDescription(): ?string
    {
        return now()->locale('id')->isoFormat('MMMM YYYY');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
        ];
    }

    protected function getData(): array
    {
        $baris = ProductionLog::query()
            ->whereBetween('production_date', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ])
            ->whereNotNull('operator_name')
            ->where('operator_name', '!=', '')
            ->select('operator_name', DB::raw('SUM(total_qty) as total'))
            ->groupBy('operator_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return [
            'labels' => $baris->pluck('operator_name')->all(),
            'datasets' => [
                [
                    'label' => 'Total unit',
                    'data' => $baris->pluck('total')->map(fn ($v) => (float) $v)->all(),
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 6,
                ],
            ],
        ];
    }
}
