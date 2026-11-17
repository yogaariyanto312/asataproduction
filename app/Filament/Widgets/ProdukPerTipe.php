<?php

namespace App\Filament\Widgets;

use App\Models\ProductionLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Komposisi produksi bulan berjalan per tipe produk (Channel / Cover / Tangki).
 * Pengelompokan memakai kata pertama nama produk, sama seperti dashboard lama.
 */
class ProdukPerTipe extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Produk per Tipe';

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getDescription(): ?string
    {
        return now()->locale('id')->isoFormat('MMMM YYYY');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $warna = ['#3b82f6', '#10b981', '#f59e0b', '#a855f7', '#ef4444', '#06b6d4'];

        $tipe = ProductionLog::query()
            ->select('product_id', DB::raw('SUM(total_qty) as total'))
            ->whereBetween('production_date', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ])
            ->groupBy('product_id')
            ->with('product:id,name')
            ->get()
            ->groupBy(fn ($row) => strtoupper(explode(' ', trim($row->product->name ?? 'Lainnya'))[0]))
            ->map(fn ($rows) => (float) $rows->sum('total'))
            ->sortDesc();

        return [
            'labels' => $tipe->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Unit',
                    'data' => $tipe->values()->all(),
                    'backgroundColor' => array_slice($warna, 0, max($tipe->count(), 1)),
                    'borderWidth' => 0,
                ],
            ],
        ];
    }
}
