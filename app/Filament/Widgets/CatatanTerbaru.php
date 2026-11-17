<?php

namespace App\Filament\Widgets;

use App\Models\Note;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Catatan yang dibuat oleh atau ditujukan kepada pengguna yang sedang login.
 * Urutannya sama dengan dashboard sebelumnya: yang belum selesai dan paling
 * dekat tenggatnya lebih dulu.
 */
class CatatanTerbaru extends TableWidget
{
    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $userId = auth()->id();

        return $table
            ->heading('Catatan')
            ->description('Terbaru untuk Anda')
            ->query(
                Note::query()
                    ->with(['user:id,name', 'targetUser:id,name'])
                    ->where(fn ($q) => $q->where('user_id', $userId)
                        ->orWhere('target_user_id', $userId))
                    ->orderByRaw('is_done ASC, CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC, due_date ASC, created_at DESC'),
            )
            ->emptyStateHeading('Belum ada catatan')
            ->emptyStateDescription('Catatan untuk Anda akan muncul di sini.')
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('title')
                    ->label('Catatan')
                    ->weight('bold')
                    ->description(fn (Note $n): ?string => $n->content
                        ? str(\App\Support\HtmlCatatan::teks($n->content))->limit(70)->toString()
                        : null)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('user.name')
                    ->label('Dari')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('due_date')
                    ->label('Tenggat')
                    ->date('d M Y')
                    ->placeholder('Tanpa tenggat')
                    ->sortable(),

                TextColumn::make('is_done')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Selesai' : 'Berjalan')
                    ->color(fn ($state): string => $state ? 'success' : 'warning'),
            ]);
    }
}
