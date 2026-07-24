<?php

namespace App\Support;

use App\Models\DepartmentMenuPermission;
use App\Models\RoleMenuPermission;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Otoritas tunggal untuk hak akses menu (view) & aksi granular.
 *
 * Permission key:
 *  - view menu : key menu, mis. "gambar-kerja".
 *  - aksi      : "menu.aksi", mis. "gambar-kerja.upload".
 *
 * allowed(role, key):
 *  - developer selalu true (bypass).
 *  - bila ada baris tersimpan (role,key) → pakai nilainya (bisa memberi/mencabut).
 *  - bila tidak → default = role ada di default_roles permission tsb.
 * Tabel role_menu_permissions adalah penentu akhir (bisa grant di luar bawaan).
 */
class MenuAccess
{
    /** @var array<string,bool>|null cache "role|key" => allowed */
    protected static ?array $stored = null;

    /** @var array<string,bool>|null cache "department|key" => allowed */
    protected static ?array $storedDept = null;

    /** @var array<string,bool>|null set key permission (view+aksi) yang dept_shared */
    protected static ?array $sharedKeys = null;

    /** @var array<string,array>|null index key => definisi permission (view/aksi) */
    protected static ?array $index = null;

    protected static function stored(): array
    {
        if (static::$stored === null) {
            static::$stored = [];
            try {
                foreach (RoleMenuPermission::all(['role', 'menu_key', 'allowed']) as $row) {
                    static::$stored["{$row->role}|{$row->menu_key}"] = (bool) $row->allowed;
                }
            } catch (\Throwable $e) {
                static::$stored = []; // tabel belum ada (sebelum migrate)
            }
        }
        return static::$stored;
    }

    protected static function storedDept(): array
    {
        if (static::$storedDept === null) {
            static::$storedDept = [];
            try {
                foreach (DepartmentMenuPermission::all(['department', 'menu_key', 'allowed']) as $row) {
                    static::$storedDept["{$row->department}|{$row->menu_key}"] = (bool) $row->allowed;
                }
            } catch (\Throwable $e) {
                static::$storedDept = []; // tabel belum ada (sebelum migrate)
            }
        }
        return static::$storedDept;
    }

    /** Set key permission (view + aksi) untuk menu ber-flag dept_shared (tak di-gate departemen). */
    protected static function sharedKeys(): array
    {
        if (static::$sharedKeys === null) {
            static::$sharedKeys = [];
            foreach (static::items() as $item) {
                if (!($item['dept_shared'] ?? false)) continue;
                static::$sharedKeys[$item['key']] = true;
                foreach ($item['actions'] ?? [] as $act) {
                    static::$sharedKeys[$act['key']] = true;
                }
            }
        }
        return static::$sharedKeys;
    }

    public static function isDeptShared(string $key): bool
    {
        return isset(static::sharedKeys()[$key]);
    }

    public static function flush(): void
    {
        static::$stored     = null;
        static::$storedDept = null;
        static::$sharedKeys = null;
        static::$index      = null;
    }

    public static function items(): array
    {
        return config('menus.items', []);
    }

    /**
     * Index semua permission (view + aksi): key => ['default_roles'=>..., 'match'=>[...], 'is_action'=>bool].
     * Aksi didaftarkan lebih dulu agar lebih spesifik saat resolusi route.
     */
    protected static function index(): array
    {
        if (static::$index === null) {
            $actions = [];
            $views   = [];
            foreach (static::items() as $item) {
                foreach ($item['actions'] ?? [] as $act) {
                    $actions[$act['key']] = [
                        'default_roles' => $act['default_roles'],
                        'match'         => $act['match'] ?? [],
                    ];
                }
                $views[$item['key']] = [
                    'default_roles' => $item['default_roles'],
                    'match'         => $item['match'] ?? [],
                ];
            }
            static::$index = $actions + $views; // aksi dulu, baru view
        }
        return static::$index;
    }

    public static function defaultRoles(string $key): array
    {
        return static::index()[$key]['default_roles'] ?? [];
    }

    public static function allowed(?string $role, string $key): bool
    {
        if (!$role) return false;
        if ($role === 'developer') return true;

        $stored = static::stored();
        if (array_key_exists("{$role}|{$key}", $stored)) {
            return $stored["{$role}|{$key}"];
        }
        return in_array($role, static::defaultRoles($key), true);
    }

    /**
     * Gate departemen untuk sebuah permission key.
     *  - departemen kosong (null) → tak dibatasi (true).
     *  - ada baris tersimpan (department,key) → pakai nilainya (termasuk menu
     *    dept_shared, yang kini bisa diatur manual oleh developer).
     *  - default → true (departemen mengizinkan sampai dicabut developer).
     */
    public static function departmentAllowed(?string $department, string $key): bool
    {
        if (!$department) return true;

        $stored = static::storedDept();
        if (array_key_exists("{$department}|{$key}", $stored)) {
            return $stored["{$department}|{$key}"];
        }
        return true;
    }

    /**
     * Izin efektif untuk seorang user: developer selalu boleh; selain itu ROLE
     * dan DEPARTEMEN keduanya harus mengizinkan. Ini gate sebenarnya yang dipakai
     * sidebar, middleware, dan directive @allowedTo.
     */
    public static function can(?User $user, string $key): bool
    {
        if (!$user) return false;
        if ($user->role === 'developer') return true;

        return static::allowed($user->role, $key)
            && static::departmentAllowed($user->department, $key);
    }

    /** Resolusi nama route → permission key (aksi diutamakan, lalu view). Null bila tak dikelola. */
    public static function permissionKeyForRoute(?string $routeName): ?string
    {
        if (!$routeName) return null;
        foreach (static::index() as $key => $def) {
            foreach ($def['match'] as $pattern) {
                if (Str::is($pattern, $routeName)) return $key;
            }
        }
        return null;
    }
}
