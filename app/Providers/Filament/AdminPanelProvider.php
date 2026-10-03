<?php

namespace App\Providers\Filament;

use App\Support\MenuAccess;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use App\Http\Middleware\AuthenticateFilamentPanel;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->brandName('Asata Production')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::hex('#2563eb'),
                // Latar panel jadi slate (biru gelap), bukan abu netral —
                // menyamai --au-shell/--au-surface milik aplikasi.
                'gray'    => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Aplikasi memakai tema gelap; panel disamakan agar perpindahan
            // dari halaman lain tidak terasa berganti aplikasi.
            ->defaultThemeMode(ThemeMode::Dark)
            // Panel ini hanya berisi Dashboard. Menu lain tetap milik aplikasi,
            // jadi sidebarnya diisi tautan balik ke sana. Dibangun lewat closure
            // (bukan array) karena hak akses baru bisa dibaca saat ada request —
            // saat provider di-boot belum ada pengguna yang login.
            ->navigation(fn (NavigationBuilder $builder) => $builder
                ->item(
                    NavigationItem::make('Dashboard')
                        ->url(fn () => Dashboard::getUrl())
                        ->icon('heroicon-o-home')
                        ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.dashboard'))
                        ->sort(0),
                )
                ->group('Menu Aplikasi', $this->tautanAplikasi()))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // Sejajar dengan bootstrap/app.php: membuat "logout semua
                // perangkat" ikut membatalkan sesi panel ini.
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Panel ini tidak memanggil ->login(): satu-satunya pintu login
            // adalah /login milik aplikasi. AuthenticateFilamentPanel tetap
            // mewarisi Authenticate milik Filament agar canAccessPanel()
            // (role + is_active) benar-benar ditegakkan.
            ->authMiddleware([
                AuthenticateFilamentPanel::class,
            ]);
    }

    /**
     * Tautan balik ke menu aplikasi.
     *
     * Dashboard sudah menjadi halaman panel ini, jadi menu itu dilewati; sisanya
     * dirender sebagai NavigationItem biasa yang mengarah ke route aplikasi.
     * Sumbernya sama dengan sidebar aplikasi (config/menus.php + MenuAccess),
     * sehingga hak aksesnya tidak pernah berbeda.
     *
     * @return array<NavigationItem>
     */
    protected function tautanAplikasi(): array
    {
        $ikon = [
            'notes'            => 'heroicon-o-pencil-square',
            'targets'          => 'heroicon-o-chart-bar',
            'chatting'         => 'heroicon-o-chat-bubble-left-right',
            'gambar-kerja'     => 'heroicon-o-document-text',
            'production'       => 'heroicon-o-plus-circle',
            'riwayat-produksi' => 'heroicon-o-clipboard-document-list',
            'barang-pengganti' => 'heroicon-o-arrow-path',
            'aksesoris'        => 'heroicon-o-archive-box',
            'master-produk'    => 'heroicon-o-cube',
            'kategori'         => 'heroicon-o-tag',
            'laporan'          => 'heroicon-o-document-chart-bar',
            'management'       => 'heroicon-o-users',
            'permissions'      => 'heroicon-o-lock-closed',
            'tutorial'         => 'heroicon-o-academic-cap',
            'settings'         => 'heroicon-o-cog-6-tooth',
            'about'            => 'heroicon-o-information-circle',
        ];

        $items = [];
        $urut  = 1;

        foreach (MenuAccess::items() as $item) {
            if (($item['key'] ?? null) === 'dashboard') {
                continue;
            }

            if (! MenuAccess::can(auth()->user(), $item['key'])) {
                continue;
            }

            $route = $item['route'] ?? null;
            if (! $route || ! app('router')->has($route)) {
                continue;
            }

            $items[] = NavigationItem::make($item['label'])
                ->url(route($route))
                ->icon($ikon[$item['key']] ?? 'heroicon-o-square-2-stack')
                ->sort($urut++);
        }

        return $items;
    }
}
