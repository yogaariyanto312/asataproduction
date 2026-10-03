<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\DepartmentMenuPermission;
use App\Models\RoleMenuPermission;
use App\Models\User;
use App\Support\MenuAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PermissionController extends Controller
{
    /** Banyaknya perubahan yang dirinci di catatan aktivitas sebelum diringkas. */
    private const RINCIAN_MAKS = 12;

    /**
     * Daftar menu yang bisa diatur beserta baris permission-nya (view + aksi).
     *
     *  - 'locked'            : ikut ditampilkan supaya daftarnya lengkap, tapi
     *                          saklarnya dikunci (Hak Akses & Settings — memberi
     *                          kendali atas sistem izin itu sendiri).
     *  - 'manageable'=>'actions' : hanya aksinya yang diatur (mis. Dashboard,
     *                          halaman awal yang tidak boleh bisa dimatikan).
     */
    protected function matrix(): array
    {
        $rows = [];
        foreach (MenuAccess::items() as $item) {
            $manageable = $item['manageable'] ?? false;
            if (!$manageable) continue;

            $perms = $manageable === 'actions' ? [] : [[
                'key'     => $item['key'],
                'label'   => 'Lihat',
                'is_view' => true,
            ]];
            foreach ($item['actions'] ?? [] as $act) {
                $perms[] = ['key' => $act['key'], 'label' => $act['label'], 'is_view' => false];
            }

            // 'icon' satu path, 'paths' beberapa — disatukan supaya tampilan tak perlu menebak.
            $paths = $item['paths'] ?? array_filter([$item['icon'] ?? null]);

            $rows[] = [
                'key'    => $item['key'],
                'label'  => $item['label'],
                'paths'  => array_values($paths),
                'locked' => (bool) ($item['locked'] ?? false),
                'shared' => (bool) ($item['dept_shared'] ?? false),
                'perms'  => $perms,
            ];
        }
        return $rows;
    }

    /**
     * Permission key yang benar-benar bisa disimpan. Menu terkunci dikecualikan:
     * saklarnya tidak dikirim, jadi kalau ikut, semuanya tertulis "tidak boleh"
     * tiap kali Simpan ditekan.
     */
    protected function allKeys(): array
    {
        $keys = [];
        foreach ($this->matrix() as $row) {
            if ($row['locked']) continue;
            foreach ($row['perms'] as $p) $keys[] = $p['key'];
        }
        return $keys;
    }

    /** Label permission untuk catatan aktivitas: key => "Menu > Aksi". */
    protected function labelKey(): array
    {
        $label = [];
        foreach ($this->matrix() as $row) {
            foreach ($row['perms'] as $p) {
                $label[$p['key']] = $row['label'] . ' > ' . $p['label'];
            }
        }
        return $label;
    }

    /** Label, warna, keterangan, dan jumlah pengguna tiap role. */
    protected function roleMeta(array $roles): array
    {
        $jumlah = User::query()
            ->whereIn('role', $roles)
            ->groupBy('role')
            ->pluck(DB::raw('count(*)'), 'role');

        $meta = [];
        foreach ($roles as $role) {
            $info = ManagementController::PERAN[$role] ?? [];
            $meta[$role] = [
                'label' => $info['label'] ?? ucfirst($role),
                'warna' => $info['warna'] ?? 'slate',
                'ket'   => $info['ket']   ?? '',
                'user'  => (int) ($jumlah[$role] ?? 0),
            ];
        }
        return $meta;
    }

    protected function departemenAktif()
    {
        return Department::where('is_active', true)->orderBy('name')->pluck('name');
    }

    public function index(Request $request)
    {
        $rows        = $this->matrix();
        $roles       = config('menus.manageable_roles', []);
        $departments = $this->departemenAktif();

        // Mode departemen bila ?department= valid; selain itu mode role (bawaan).
        $selectedDept = $request->input('department');
        if ($selectedDept && !$departments->contains($selectedDept)) {
            $selectedDept = null;
        }

        // Dua peta: state = yang berlaku sekarang (posisi saklar), bawaan = nilai
        // dari config (penanda "diubah" & tombol "Kembalikan ke bawaan").
        $state  = [];
        $bawaan = [];

        foreach ($rows as $row) {
            foreach ($row['perms'] as $perm) {
                if ($selectedDept) {
                    $state[$perm['key']]  = MenuAccess::departmentAllowed($selectedDept, $perm['key']);
                    $bawaan[$perm['key']] = true; // departemen mengizinkan sampai dicabut
                    continue;
                }
                foreach ($roles as $role) {
                    $sel = "{$role}|{$perm['key']}";
                    $state[$sel]  = MenuAccess::allowed($role, $perm['key']);
                    $bawaan[$sel] = in_array($role, MenuAccess::defaultRoles($perm['key']), true);
                }
            }
        }

        return Inertia::render('Permissions/Index', [
            'mode'         => $selectedDept ? 'department' : 'role',
            'indexUrl'     => route('permissions.index'),
            'updateUrl'    => route('permissions.update'),
            'rows'         => $rows,
            'roles'        => $roles,
            'roleMeta'     => $this->roleMeta($roles),
            'departments'  => $departments->map(fn ($d) => ['value' => $d, 'label' => $d])->values(),
            'selectedDept' => $selectedDept,
            'state'        => $state,
            'bawaan'       => $bawaan,
        ]);
    }

    public function update(Request $request)
    {
        $selectedDept = $request->input('department');
        $checked      = (array) $request->input('allowed', []);
        $allKeys      = $this->allKeys();
        $now          = now();

        // ── Mode departemen ────────────────────────────────────────────────
        if ($selectedDept && $this->departemenAktif()->contains($selectedDept)) {
            $sebelum = DepartmentMenuPermission::where('department', $selectedDept)
                ->pluck('allowed', 'menu_key')->map(fn ($v) => (bool) $v)->all();

            $baris = [];
            $berubah = [];
            foreach ($allKeys as $key) {
                $nilai = isset($checked[$key]);
                if (($sebelum[$key] ?? true) !== $nilai) {
                    $berubah[] = ['siapa' => $selectedDept, 'key' => $key, 'jadi' => $nilai];
                }
                $baris[] = ['department' => $selectedDept, 'menu_key' => $key, 'allowed' => $nilai,
                            'created_at' => $now, 'updated_at' => $now];
            }

            DB::transaction(function () use ($baris) {
                foreach (array_chunk($baris, 200) as $bagian) {
                    DepartmentMenuPermission::upsert($bagian, ['department', 'menu_key'], ['allowed', 'updated_at']);
                }
            });

            MenuAccess::flush();
            ActivityLog::record('update', $this->ringkasan($berubah, "departemen {$selectedDept}"));

            return redirect()->route('permissions.index', ['department' => $selectedDept])
                ->with('success', $berubah === []
                    ? 'Tidak ada perubahan hak akses.'
                    : "Hak akses departemen {$selectedDept} diperbarui (" . count($berubah) . ' perubahan).');
        }

        // ── Mode role ──────────────────────────────────────────────────────
        $roles = config('menus.manageable_roles', []);

        $sebelum = RoleMenuPermission::query()
            ->get(['role', 'menu_key', 'allowed'])
            ->mapWithKeys(fn ($r) => ["{$r->role}|{$r->menu_key}" => (bool) $r->allowed])
            ->all();

        // Satu upsert untuk semua sel, bukan updateOrCreate per sel (dulu ratusan query).
        $baris   = [];
        $berubah = [];

        foreach ($roles as $role) {
            foreach ($allKeys as $key) {
                $nilai = isset($checked[$role][$key]);
                $sel   = "{$role}|{$key}";

                // Baris yang belum pernah disimpan dibandingkan dengan bawaannya,
                // supaya "perubahan" tidak salah dihitung saat pertama kali Simpan.
                $lama = $sebelum[$sel] ?? in_array($role, MenuAccess::defaultRoles($key), true);

                if ($lama !== $nilai) {
                    $berubah[] = ['siapa' => $role, 'key' => $key, 'jadi' => $nilai];
                }

                $baris[] = ['role' => $role, 'menu_key' => $key, 'allowed' => $nilai,
                            'created_at' => $now, 'updated_at' => $now];
            }
        }

        DB::transaction(function () use ($baris) {
            foreach (array_chunk($baris, 200) as $bagian) {
                RoleMenuPermission::upsert($bagian, ['role', 'menu_key'], ['allowed', 'updated_at']);
            }
        });

        MenuAccess::flush();
        ActivityLog::record('update', $this->ringkasan($berubah));

        return redirect()->route('permissions.index')
            ->with('success', $berubah === []
                ? 'Tidak ada perubahan hak akses.'
                : 'Hak akses berhasil diperbarui (' . count($berubah) . ' perubahan).');
    }

    /**
     * Kalimat untuk catatan aktivitas — merinci izin apa yang DIBERI/DICABUT
     * untuk siapa, supaya perubahan izin bisa ditelusuri.
     */
    protected function ringkasan(array $berubah, ?string $lingkup = null): string
    {
        $judul = $lingkup ? "hak akses {$lingkup}" : 'hak akses';

        if ($berubah === []) {
            return "Simpan {$judul} — tidak ada yang berubah";
        }

        $label = $this->labelKey();
        $peran = ManagementController::PERAN;

        $rinci = [];
        foreach (array_slice($berubah, 0, self::RINCIAN_MAKS) as $b) {
            $rinci[] = sprintf('%s: %s %s',
                $peran[$b['siapa']]['label'] ?? ucfirst($b['siapa']),
                $label[$b['key']] ?? $b['key'],
                $b['jadi'] ? 'DIBERI' : 'DICABUT',
            );
        }

        $sisa = count($berubah) - count($rinci);
        if ($sisa > 0) $rinci[] = "dan {$sisa} perubahan lain";

        return "Ubah {$judul} (" . count($berubah) . '): ' . implode('; ', $rinci);
    }
}
