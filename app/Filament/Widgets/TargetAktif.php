<?php

namespace App\Filament\Widgets;

use App\Models\ProductionTarget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Progres target produksi yang sedang berjalan.
 *
 * Aktual dihitung dari selisih produksi kumulatif terhadap baseline saat target
 * dibuat — logika yang sama dengan dashboard sebelumnya.
 */
class TargetAktif extends TableWidget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Target Aktif')
            ->description('Progres dihitung sejak target dibuat')
            ->query(
                ProductionTarget::query()
                    ->with('product')
                    ->whereNotNull('product_id'),
            )
            ->emptyStateHeading('Belum ada target')
            ->emptyStateDescription('Target yang disetel akan tampil beserta progresnya di sini.')
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->weight('bold')
                    ->description(fn (ProductionTarget $t): ?string => $t->product?->series_with_kva)
                    ->searchable(),

                TextColumn::make('target_qty')
                    ->label('Target')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('aktual')
                    ->label('Tercapai')
                    ->state(fn (ProductionTarget $t): int => $t->actualProduced())
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('progres')
                    ->label('Progres')
                    ->alignEnd()
                    ->badge()
                    ->state(function (ProductionTarget $t): string {
                        if ((int) $t->target_qty <= 0) {
                            return '—';
                        }

                        $persen = min(round(($t->actualProduced() / $t->target_qty) * 100), 100);

                        return $persen . '%';
                    })
                    ->color(function (ProductionTarget $t): string {
                        if ((int) $t->target_qty <= 0) {
                            return 'gray';
                        }

                        $persen = ($t->actualProduced() / $t->target_qty) * 100;

                        return match (true) {
                            $persen >= 100 => 'success',
                            $persen >= 60  => 'info',
                            $persen >= 30  => 'warning',
                            default        => 'danger',
                        };
                    }),
            ]);
    }
}
