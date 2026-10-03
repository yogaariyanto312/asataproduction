<?php

namespace App\Http\Middleware;

use App\Support\MenuAccess;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Root template halaman Inertia.
     *
     * Sengaja BUKAN "app": resources/views/layouts/app.blade.php sudah dipakai
     * halaman Blade lama, dan selama migrasi bertahap keduanya hidup
     * berdampingan. Root Inertia berdiri sendiri di resources/views/inertia.blade.php.
     *
     * @var string
     */
    protected $rootView = 'inertia';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props yang dibagikan ke setiap halaman React.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'username'   => $user->username,
                    'email'      => $user->email,
                    'role'       => $user->role,
                    'department' => $user->department,
                    'avatar_url' => $user->avatarUrl(),
                ] : null,
            ],

            // URL aksi global. Sengaja datang dari server: aplikasi ini dilayani
            // dari subpath (/asata-production/public), sehingga URL literal
            // seperti '/logout' di sisi React akan menembak root domain dan 404.
            'logoutUrl' => fn () => route('logout'),
            'profileUrl' => fn () => route('profile.edit'),
            'appName'    => fn () => ucwords((string) config('app.name', 'Asata Production')),

            // Notifikasi berkala (pesan chat baru, catatan jatuh tempo, badge belum
            // dibaca) — hanya bila menu Chatting boleh dibuka.
            'notifUrl' => fn () => $user && \App\Support\MenuAccess::can($user, 'chatting') ? route('api.notifications') : null,
            'chatUrl'  => fn () => $user && \App\Support\MenuAccess::can($user, 'chatting') ? route('chatting') : null,
            'notesUrl' => fn () => $user && \App\Support\MenuAccess::can($user, 'notes') ? route('notes.index') : null,

            // Settings → blokir klik kanan & DevTools untuk selain developer.
            'disableDevtools' => fn () => $user && $user->role !== 'developer'
                && (bool) \App\Models\BotSetting::instance()->disable_devtools,

            // Developer tetap bisa memakai aplikasi selama maintenance — beri
            // penanda supaya tidak lupa mematikannya.
            'maintenanceAktif' => fn () => $user && $user->role === 'developer'
                && \App\Http\Middleware\MaintenanceMode::aktif(\App\Models\BotSetting::instance())
                    ? ['settingsUrl' => route('developer.bot-settings')]
                    : null,

            // Nama route aktif — dipakai sidebar React untuk menandai menu yang
            // sedang dibuka, mencocokkannya dengan pola "match" tiap menu.
            'routeName' => fn () => optional($request->route())->getName(),

            // Menu sidebar hasil filter hak akses — cerminan logika di
            // layouts/app.blade.php, supaya sidebar React memakai sumber
            // kebenaran yang sama (config/menus.php + MenuAccess).
            'menu' => fn () => $user ? $this->menuFor($request) : [],

            // Flash message: dipakai ulang oleh toast React.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'webhook_info' => fn () => $request->session()->get('webhook_info'),
            ],
        ];
    }

    /**
     * Route yang halamannya SUDAH dirender React (Inertia).
     *
     * Selama migrasi bertahap, sebagian besar menu masih Blade. Sidebar React
     * harus memakai <a> biasa untuk halaman Blade — memakai <Link> Inertia ke
     * respons non-Inertia membuat Inertia menampilkan modal error di iframe
     * ber-origin null, navigasi gagal, dan modal itu menutupi halaman.
     *
     * Tambahkan nama route ke sini setiap kali satu halaman selesai dimigrasi.
     *
     * @var list<string>
     */
    protected array $inertiaRoutes = [
        'dashboard',
        'management.index',
        'categories.index',
        'products.index',
        'notes.index',
        'replacements.index',
        'accessories.index',
        'production.index',
        'production.create',
        'reports.index',
        'permissions.index',
        'tutorial',
        'about',
        'production.targets.index',
        'gambar-kerja.index',
        'chatting',
        'developer.bot-settings',
        'profile.edit',
    ];

    /**
     * Daftar menu yang boleh dilihat user.
     *
     * Struktur sengaja dibiarkan DATAR, persis seperti config/menus.php: menu
     * tidak punya "children", pengelompokan dilakukan lewat atribut "group"
     * (mis. grup "produksi" yang dirender sebagai dropdown QC-Welding untuk
     * developer). Pola "match" ikut dikirim supaya sidebar React bisa
     * menentukan menu aktif tanpa menduplikasi aturan di sisi klien.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function menuFor(Request $request): array
    {
        $user = $request->user();
        $out  = [];

        foreach (MenuAccess::items() as $item) {
            if (! MenuAccess::can($user, $item['key'])) {
                continue;
            }

            $out[] = [
                'key'   => $item['key'],
                'label' => $item['label'],
                // Sebagian menu (mis. Tutorial) memakai 'paths' berisi banyak
                // path SVG, bukan 'icon' tunggal — sama seperti sidebar Blade
                // yang menulis @foreach($item['paths'] ?? [$item['icon']]).
                'icon'  => $item['icon'] ?? null,
                'paths' => $item['paths'] ?? null,
                'group' => $item['group'] ?? null,
                'match' => $item['match'] ?? [],
                'url'   => $this->urlForRoute($item['route'] ?? null),
                'spa'   => in_array($item['route'] ?? null, $this->inertiaRoutes, true),
            ];
        }

        return $out;
    }

    /** Nama route -> URL absolut; null bila route tidak terdaftar. */
    protected function urlForRoute(?string $name): ?string
    {
        if (! $name || ! app('router')->has($name)) {
            return null;
        }

        return route($name);
    }
}
