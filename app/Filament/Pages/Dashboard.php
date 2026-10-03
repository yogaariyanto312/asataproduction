<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Dashboard as FilamentDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Dashboard panel.
 *
 * Filament menerjemahkan judul bawaannya menjadi "Dasbor" karena locale aplikasi
 * id. Menu di sidebar aplikasi memakai kata "Dashboard", jadi judulnya disamakan
 * di sini supaya tidak terasa dua nama untuk halaman yang sama.
 */
class Dashboard extends FilamentDashboard
{
    public function getTitle(): string | Htmlable
    {
        return 'Dashboard';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Ringkasan produksi ' . now()->locale('id')->isoFormat('dddd, D MMMM YYYY');
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    /**
     * "Aksi Cepat" dari dashboard lama, kini menjadi tombol di kepala halaman.
     * Disaring di server agar tidak menawarkan menu yang memang tidak boleh
     * diakses peran tersebut.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $user    = auth()->user();
        $tombol  = [];

        if ($user && ! $user->isSupervisor()) {
            $tombol[] = Action::make('input-produksi')
                ->label('Input Produksi')
                ->icon('heroicon-o-plus')
                ->url(route('production.create'));
        }

        if ($user && $user->isPrivileged()) {
            $tombol[] = Action::make('laporan-harian')
                ->label('Laporan Harian')
                ->icon('heroicon-o-document-chart-bar')
                ->color('gray')
                ->url(route('reports.daily'));
        }

        $tombol[] = Action::make('riwayat-produksi')
            ->label('Riwayat Produksi')
            ->icon('heroicon-o-clipboard-document-list')
            ->color('gray')
            ->url(route('production.index'));

        return $tombol;
    }
}
