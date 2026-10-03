<?php

namespace App\Filament\Widgets;

use App\Models\ProductionLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Entri produksi yang masuk hari ini, memakai tabel bawaan Filament.
 */
class ProduksiTerbaru extends TableWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Input Produksi Terbaru')
            ->description('Entri yang masuk hari ini')
            ->query(
                ProductionLog::query()
                    ->with(['product.category', 'user'])
                    ->whereDate('production_date', now()->toDateString())
                    ->latest('created_at'),
            )
            ->emptyStateHeading('Belum ada input hari ini')
            ->emptyStateDescription('Entri produksi yang dicatat hari ini akan muncul di sini.')
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Jam')
                    ->dateTime('H:i')
                    ->sortable(),

                TextColumn::make('product.name')
                    ->label('Produk')
                    ->weight('bold')
                    ->description(fn (ProductionLog $log): ?string => $log->product?->series_with_kva)
                    ->searchable(),

                TextColumn::make('product.category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('operator_name')
                    ->label('Operator')
                    ->formatStateUsing(
                        fn (?string $state, ProductionLog $log): string => $state
                            ?: ($log->user->name ?? '-'),
                    ),

                TextColumn::make('up_qty')
                    ->label('UP')
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('bt_qty')
                    ->label('BT')
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('total_qty')
                    ->label('Total')
                    ->numeric()
                    ->badge()
                    ->color('info')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('reject_qty')
                    ->label('Reject')
                    ->numeric()
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'danger' : 'gray')
                    ->alignEnd(),
            ]);
    }
}
