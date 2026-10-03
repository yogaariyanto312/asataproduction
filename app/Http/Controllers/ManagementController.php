<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ManagementController extends Controller
{
    /**
     * Keterangan tiap peran (label, warna, penjelasan). Satu-satunya daftar —
     * dipakai juga oleh halaman Hak Akses supaya tidak ada dua salinan yang
     * bisa berbeda diam-diam.
     */
    public const PERAN = [
        'developer'  => ['label' => 'Developer',  'warna' => 'blue',    'ket' => 'Akses penuh ke seluruh sistem'],
        'admin'      => ['label' => 'Admin',      'warna' => 'purple',  'ket' => 'Mengelola operator, visitor, dan data produksi'],
        'supervisor' => ['label' => 'Supervisor', 'warna' => 'teal',    'ket' => 'Memantau laporan dan data produksi'],
        'mandor'     => ['label' => 'Mandor',     'warna' => 'violet',  'ket' => 'Mengawasi pekerjaan di lapangan'],
        'operator'   => ['label' => 'Operator',   'warna' => 'sky',     'ket' => 'Mencatat hasil produksi harian'],
        'visitor'    => ['label' => 'Visitor',    'warna' => 'emerald', 'ket' => 'Hanya bisa melihat, tanpa mengubah data'],
    ];

    /** Peran → nama resource route-nya (create/edit/destroy). */
    private const SUMBER = [
        'developer'  => 'developers',
        'admin'      => 'admins',
        'supervisor' => 'supervisors',
        'mandor'     => 'mandors',
        'operator'   => 'operators',
        'visitor'    => 'visitors',
    ];

    public function index(Request $request)
    {
        $tab    = $this->tab($request);
        $search = trim((string) $request->input('search'));
        $kelola = $this->bisaKelola();

        $props = [
            'tab'        => $tab,
            'search'     => $search,
            'indexUrl'   => route('management.index'),
            'peran'      => self::PERAN,
            'jumlah'     => $this->jumlahPerPeran($search),
            'bisaLihat'  => $this->bisaLihat(),
            'bisaKelola' => $kelola,
            'createUrl'  => null,
            'pengguna'   => [],
            'departemen' => null,
        ];

        // Tab Departemen (khas asata) — hanya developer yang mengelola departemen.
        if ($tab === 'department') {
            $props['departemen'] = $this->departemen();

            return Inertia::render('Management/Index', $props);
        }

        // Daftar hanya peran yang sedang dibuka — dulu keenam peran diambil
        // sekaligus dan seluruh data pengguna terkirim untuk satu tab yang dilihat.
        $pengguna = $this->saring(User::where('role', $tab), $search)->orderBy('name')->get();
        $boleh    = $kelola[$tab] ?? false;
        $sumber   = self::SUMBER[$tab];
        $user     = auth()->user();
        // Tombol hanya muncul bila peran boleh dikelola DAN saklar aksinya di
        // Hak Akses menyala — tombol yang ujungnya 403 tidak perlu ditampilkan.
        $bisa = fn (string $aksi) => $boleh && \App\Support\MenuAccess::can($user, 'manajemen.' . $aksi);

        $props['createUrl'] = $bisa('create') ? route($sumber . '.create') : null;
        $props['pengguna']  = $pengguna->map(fn ($u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'username'   => $u->username,
            'email'      => $u->email,
            'department' => $u->department,
            'avatar_url' => $u->avatar ? $u->avatarUrl() : null,
            'is_active'  => (bool) $u->is_active,
            'is_me'      => $u->id === auth()->id(),
            'joined'     => $u->created_at?->locale('id')->isoFormat('D MMM YYYY'),
            'editUrl'    => $bisa('edit') ? route($sumber . '.edit', $u->id) : null,
            // Tidak ada tombol hapus untuk akun sendiri.
            'deleteUrl'  => $bisa('delete') && $u->id !== auth()->id() ? route($sumber . '.destroy', $u->id) : null,
            'toggleUrl'  => $bisa('edit') && $tab === 'operator' ? route('operators.toggle-active', $u->id) : null,
        ])->values();

        return Inertia::render('Management/Index', $props);
    }

    private function tab(Request $request): string
    {
        $tab   = (string) $request->input('tab', 'admin');
        $lihat = $this->bisaLihat();

        if (! ($lihat[$tab] ?? false)) {
            // Tab yang tidak boleh dilihat tidak dialihkan diam-diam ke data
            // lain; dikembalikan ke tab pertama yang memang boleh.
            return array_key_first(array_filter($lihat)) ?: 'admin';
        }

        return $tab;
    }

    private function saring($query, string $search)
    {
        return $query->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('username', 'like', "%{$search}%");
        }));
    }

    /**
     * Jumlah pengguna per peran, ikut menghitung kata pencarian — angka di tab
     * langsung memberitahu peran mana yang punya hasil.
     */
    private function jumlahPerPeran(string $search): array
    {
        $jumlah = $this->saring(User::query(), $search)
            ->selectRaw('role, COUNT(*) as jml')
            ->groupBy('role')
            ->pluck('jml', 'role');

        $hasil = collect(self::PERAN)->map(fn ($x, $peran) => (int) ($jumlah[$peran] ?? 0))->all();

        if ($this->bisaLihat()['department']) {
            $hasil['department'] = Department::count();
        }

        return $hasil;
    }

    /**
     * Peran mana yang boleh ditambah/diubah/dihapus oleh yang sedang login —
     * mengikuti middleware di routes: developer semuanya, admin semua peran di
     * bawahnya (operator, supervisor, mandor, visitor), tapi tidak admin &
     * developer. Saklar Hak Akses "Manajemen > Tambah/Edit/Hapus" tetap berlaku.
     */
    private function bisaKelola(): array
    {
        $user = auth()->user();
        $semuaFalse = array_fill_keys(array_keys(self::PERAN), false);

        if ($user->isDeveloper()) {
            return array_fill_keys(array_keys(self::PERAN), true);
        }

        if ($user->role === 'admin') {
            return array_merge($semuaFalse,
                ['operator' => true, 'supervisor' => true, 'mandor' => true, 'visitor' => true]);
        }

        return $semuaFalse;
    }

    /** Daftar developer & departemen hanya untuk developer; sisanya boleh dilihat. */
    private function bisaLihat(): array
    {
        $dev   = auth()->user()->isDeveloper();
        $lihat = array_fill_keys(array_keys(self::PERAN), true);
        $lihat['developer']  = $dev;
        $lihat['department'] = $dev;

        return $lihat;
    }

    private function departemen(): array
    {
        $rows = Department::withCount(['operators' => fn ($q) => $q->where('role', 'operator')])
            ->orderBy('name')
            ->get();

        return [
            'storeUrl' => route('departments.store'),
            'rows'     => $rows->map(fn ($d) => [
                'id'        => $d->id,
                'name'      => $d->name,
                'is_active' => (bool) $d->is_active,
                'operators' => (int) $d->operators_count,
                'updateUrl' => route('departments.update', $d->id),
                'deleteUrl' => route('departments.destroy', $d->id),
            ])->values(),
        ];
    }
}
