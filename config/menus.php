<?php

/**
 * Sumber tunggal definisi menu sidebar & hak akses (view + aksi granular).
 *
 * Tiap entri menu:
 *  - key           : identitas unik (dipakai sebagai permission key "view").
 *  - label         : teks di sidebar.
 *  - route         : nama route landing (untuk href).
 *  - icon / paths  : path SVG.
 *  - default_roles : role yang secara bawaan boleh MELIHAT menu.
 *  - match         : pola nama route (Str::is) route "view" (read-only) menu ini.
 *  - manageable    : true bila muncul di UI "Hak Akses Menu".
 *  - group         : opsional penanda grup dropdown sidebar.
 *  - actions       : opsional daftar aksi granular. Tiap aksi:
 *        key, label, default_roles, match (pola route aksi tsb).
 *
 * PENTING: default_roles tiap view/aksi disamakan dengan proteksi role: yang
 * berlaku sekarang, sehingga saat middleware role: dilepas (agar bisa di-grant),
 * perilaku default tetap identik.
 */

$all         = ['developer', 'admin', 'supervisor', 'mandor', 'operator'];
$withVisitor = ['developer', 'admin', 'supervisor', 'mandor', 'operator', 'visitor'];

return [
    'items' => [
        [
            'key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard',
            'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
            'default_roles' => $withVisitor, 'match' => ['dashboard'], 'manageable' => false,
        ],
        [
            'key' => 'notes', 'label' => 'Catatan', 'route' => 'notes.index',
            'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
            'default_roles' => $all, 'match' => ['notes.index'], 'manageable' => true,
        ],
        [
            'key' => 'targets', 'label' => 'Target Produksi', 'route' => 'production.targets.index',
            'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
            'default_roles' => $all, 'match' => ['production.targets.index'], 'manageable' => true,
            'actions' => [
                ['key' => 'targets.edit',   'label' => 'Set / Edit', 'default_roles' => ['developer', 'admin', 'supervisor', 'mandor'],
                 'match' => ['production.targets.store', 'production.targets.schedule-photo.store']],
                ['key' => 'targets.delete', 'label' => 'Hapus', 'default_roles' => ['developer', 'admin', 'supervisor', 'mandor'],
                 'match' => ['production.targets.destroy', 'production.targets.schedule-photo.destroy']],
            ],
        ],
        [
            'key' => 'chatting', 'label' => 'Chatting', 'route' => 'chatting',
            'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3v-3z',
            'default_roles' => $withVisitor, 'match' => ['chatting'], 'manageable' => true,
        ],
        [
            'key' => 'gambar-kerja', 'label' => 'Gambar Kerja', 'route' => 'gambar-kerja.index',
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'default_roles' => $withVisitor, 'match' => ['gambar-kerja.index', 'gambar-kerja.by-group'], 'manageable' => true,
            'actions' => [
                ['key' => 'gambar-kerja.upload', 'label' => 'Upload', 'default_roles' => ['developer', 'admin'],
                 'match' => ['gambar-kerja.create', 'gambar-kerja.store']],
                ['key' => 'gambar-kerja.edit', 'label' => 'Edit', 'default_roles' => ['developer', 'admin'],
                 'match' => ['gambar-kerja.update-kategori', 'gambar-kerja.update-info', 'gambar-kerja.upload-thumbnail', 'gambar-kerja.delete-thumbnail']],
                ['key' => 'gambar-kerja.delete', 'label' => 'Hapus', 'default_roles' => ['developer', 'admin'],
                 'match' => ['gambar-kerja.destroy', 'gambar-kerja.destroy-by-group']],
            ],
        ],
        [
            'key' => 'input-produksi', 'label' => 'Input Produksi', 'route' => 'production.create',
            'icon' => 'M12 4v16m8-8H4',
            'default_roles' => ['developer', 'admin', 'operator'],
            'match' => ['production.create', 'production.store'], 'manageable' => true, 'group' => 'produksi',
        ],
        [
            'key' => 'riwayat-produksi', 'label' => 'Riwayat Produksi', 'route' => 'production.index',
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
            'default_roles' => $withVisitor, 'match' => ['production.index', 'production.show'], 'manageable' => true, 'group' => 'produksi',
            'actions' => [
                ['key' => 'riwayat-produksi.edit',   'label' => 'Edit', 'default_roles' => ['developer', 'admin', 'operator'],
                 'match' => ['production.edit', 'production.update']],
                ['key' => 'riwayat-produksi.delete', 'label' => 'Hapus', 'default_roles' => ['developer', 'admin', 'operator'],
                 'match' => ['production.destroy']],
            ],
        ],
        [
            'key' => 'barang-pengganti', 'label' => 'Barang Pengganti', 'route' => 'replacements.index',
            'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
            'default_roles' => $all, 'match' => ['replacements.index'], 'manageable' => true, 'group' => 'produksi',
            'actions' => [
                ['key' => 'barang-pengganti.create', 'label' => 'Tambah', 'default_roles' => ['developer', 'admin', 'operator'],
                 'match' => ['replacements.store']],
                ['key' => 'barang-pengganti.delete', 'label' => 'Hapus', 'default_roles' => ['developer', 'admin', 'operator'],
                 'match' => ['replacements.destroy']],
            ],
        ],
        [
            'key' => 'aksesoris', 'label' => 'Aksesoris Keluar', 'route' => 'accessories.index',
            'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
            'default_roles' => $all, 'match' => ['accessories.index'], 'manageable' => true, 'group' => 'produksi',
            'actions' => [
                ['key' => 'aksesoris.create', 'label' => 'Tambah', 'default_roles' => ['developer', 'admin', 'operator'],
                 'match' => ['accessories.store']],
                ['key' => 'aksesoris.delete', 'label' => 'Hapus', 'default_roles' => ['developer', 'admin', 'operator'],
                 'match' => ['accessories.destroy']],
            ],
        ],
        [
            'key' => 'master-produk', 'label' => 'Master Produk', 'route' => 'products.index',
            'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
            'default_roles' => ['developer', 'admin'], 'match' => ['products.index', 'products.show'], 'manageable' => true, 'group' => 'produksi', 'dept_shared' => true,
            'actions' => [
                ['key' => 'master-produk.create', 'label' => 'Tambah', 'default_roles' => ['developer', 'admin'],
                 'match' => ['products.create', 'products.store']],
                ['key' => 'master-produk.edit', 'label' => 'Edit', 'default_roles' => ['developer', 'admin'],
                 'match' => ['products.edit', 'products.update', 'products.toggle-active']],
                ['key' => 'master-produk.delete', 'label' => 'Hapus', 'default_roles' => ['developer', 'admin'],
                 'match' => ['products.destroy']],
            ],
        ],
        [
            'key' => 'laporan', 'label' => 'Laporan', 'route' => 'reports.index',
            'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'default_roles' => ['developer', 'admin', 'supervisor', 'mandor'],
            'match' => ['reports.index', 'reports.daily'], 'manageable' => true,
            'actions' => [
                ['key' => 'laporan.export', 'label' => 'Export', 'default_roles' => ['developer', 'admin', 'supervisor', 'mandor'],
                 'match' => ['reports.export-pdf', 'reports.export-excel', 'reports.daily-pdf']],
            ],
        ],
        [
            'key' => 'kategori', 'label' => 'Kategori', 'route' => 'categories.index',
            'icon' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
            'default_roles' => ['developer'], 'match' => ['categories.index'], 'manageable' => true, 'group' => 'produksi', 'dept_shared' => true,
        ],
        [
            'key' => 'manajemen', 'label' => 'Manajemen', 'route' => 'management.index',
            'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
            'default_roles' => ['developer', 'admin', 'operator'], 'match' => ['management.*'], 'manageable' => true,
        ],
        [
            'key' => 'permissions', 'label' => 'Hak Akses Menu', 'route' => 'permissions.index',
            'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
            'default_roles' => ['developer'], 'match' => ['permissions.*'], 'manageable' => false,
        ],
        [
            'key' => 'tutorial', 'label' => 'Tutorial', 'route' => 'tutorial',
            'paths' => [
                'M11.5 6H4a1 1 0 00-1 1v11a1 1 0 001 1h7.5V6z',
                'M12.5 6H20a1 1 0 011 1v11a1 1 0 01-1 1h-7.5V6z',
                'M11.5 18.5c.3.4.8.5 1.3.5s1-.3 1.3-.5',
                'M12 1a3 3 0 100 6 3 3 0 000-6z',
                'M11 3l3 1-3 1V3z',
                'M4.5 10h5.5M4.5 12.5h3.5M4.5 15h5.5',
                'M13.5 10h5.5M13.5 12.5h3.5M13.5 15h5.5',
            ],
            'default_roles' => $withVisitor, 'match' => ['tutorial'], 'manageable' => true,
        ],
        [
            'key' => 'settings', 'label' => 'Settings', 'route' => 'developer.bot-settings',
            'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
            'default_roles' => ['developer'], 'match' => ['developer.bot-settings*', 'developer.*'], 'manageable' => false,
        ],
        [
            'key' => 'about', 'label' => 'Tentang Aplikasi', 'route' => 'about',
            'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'default_roles' => $withVisitor, 'match' => ['about'], 'manageable' => false,
        ],
    ],

    // Role yang bisa diatur di matriks (developer sengaja dikecualikan: selalu bypass/penuh).
    'manageable_roles' => ['admin', 'supervisor', 'mandor', 'operator', 'visitor'],
];
