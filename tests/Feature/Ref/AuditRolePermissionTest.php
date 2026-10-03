<?php

namespace Tests\Feature\Ref;

use App\Models\User;
use App\Support\MenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Audit menyeluruh: apakah tiap role benar-benar mendapat akses yang dijanjikan
 * config/menus.php — tidak kurang (fitur mati diam-diam) dan tidak lebih
 * (celah izin).
 *
 * Berbeda dari MenuPermissionTest yang menguji beberapa kasus pilihan, di sini
 * SELURUH kombinasi role x permission diperiksa sekaligus.
 */
class AuditRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MenuAccess::flush();
    }

    private function user(string $role): User
    {
        return User::create([
            'name'      => ucfirst($role),
            'username'  => $role . 'audit',
            'email'     => $role . '.audit@uji.test',
            'role'      => $role,
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    /** Menu yang muncul di halaman Hak Akses. */
    private function menuDikelola(): array
    {
        return array_values(array_filter(
            MenuAccess::items(),
            fn ($i) => $i['manageable'] ?? false
        ));
    }

    /* ─────────────────────────────────────────────────────────────
       1. Uji nyata lewat HTTP: tiap role membuka tiap halaman menu.
       ───────────────────────────────────────────────────────────── */

    public function test_setiap_role_hanya_bisa_membuka_menu_yang_diizinkan(): void
    {
        $roles    = array_merge(['developer'], config('menus.manageable_roles', []));
        $salah    = [];
        $laporan  = [];

        // Dibuat sekali di awal. Menghapus/mengganti pengguna di tengah membuat
        // AuthenticateSession menganggap sesinya dibajak lalu me-logout —
        // hasilnya 302 ke login, bukan 403, dan auditnya jadi menguji hal lain.
        $pengguna = [];
        foreach ($roles as $role) $pengguna[$role] = $this->user($role);

        foreach ($this->menuDikelola() as $item) {
            $baris = ['menu' => $item['label']];

            foreach ($roles as $role) {
                $boleh = MenuAccess::allowed($role, $item['key']);

                // Sesi dikosongkan supaya tiap permintaan berdiri sendiri.
                $this->flushSession();
                $res = $this->actingAs($pengguna[$role])->get(route($item['route']));

                // Yang diuji hanya "ditolak atau tidak", bukan isi halamannya.
                $ditolak = $res->status() === 403;

                if ($ditolak === $boleh) {
                    $salah[] = sprintf(
                        '%s (%s) untuk %s: seharusnya %s, nyatanya HTTP %d',
                        $item['label'], $item['key'], $role,
                        $boleh ? 'boleh' : 'ditolak', $res->status()
                    );
                }

                $baris[$role] = $ditolak ? '-' : 'v';
            }

            $laporan[] = $baris;
        }

        $this->cetak('AKSES MENU (v = bisa dibuka, - = 403)', $roles, $laporan);

        $this->assertSame([], $salah, "Akses menu tidak sesuai:\n" . implode("\n", $salah));
    }

    /* ─────────────────────────────────────────────────────────────
       2. Seluruh kombinasi role x aksi, lewat gate-nya langsung.
       ───────────────────────────────────────────────────────────── */

    public function test_gate_setiap_aksi_sesuai_bawaan_untuk_semua_role(): void
    {
        $roles   = config('menus.manageable_roles', []);
        $salah   = [];
        $laporan = [];
        $jumlah  = 0;

        $pengguna = [];
        foreach ($roles as $role) $pengguna[$role] = $this->user($role);

        foreach ($this->menuDikelola() as $item) {
            foreach ($item['actions'] ?? [] as $act) {
                $baris = ['menu' => $item['label'] . ' > ' . $act['label']];

                foreach ($roles as $role) {
                    // Harapan: aksi diizinkan HANYA bila role ada di default_roles
                    // aksi DAN boleh melihat menu induknya.
                    $harap = in_array($role, $act['default_roles'], true)
                          && in_array($role, $item['default_roles'], true);

                    $nyata = MenuAccess::can($pengguna[$role], $act['key']);

                    if ($harap !== $nyata) {
                        $salah[] = sprintf('%s untuk %s: harap %s, nyata %s',
                            $act['key'], $role,
                            $harap ? 'boleh' : 'tidak', $nyata ? 'boleh' : 'tidak');
                    }

                    $baris[$role] = $nyata ? 'v' : '-';
                    $jumlah++;
                }

                $laporan[] = $baris;
            }
        }

        $this->cetak('AKSI GRANULAR (v = boleh)', $roles, $laporan);
        fwrite(STDERR, "  {$jumlah} kombinasi role x aksi diperiksa\n");

        $this->assertSame([], $salah, "Aksi tidak sesuai bawaan:\n" . implode("\n", $salah));
    }

    /* ─────────────────────────────────────────────────────────────
       3. Aturan desain: default aksi harus subset menu induknya.
       ───────────────────────────────────────────────────────────── */

    /**
     * Aksi mewarisi izin menu induknya. Kalau default_roles sebuah aksi memuat
     * role yang tidak boleh melihat menunya, aksi itu mati sejak awal — saklarnya
     * menyala di layar tapi tidak pernah berlaku.
     */
    public function test_default_roles_aksi_selalu_subset_menu_induknya(): void
    {
        $salah = [];

        foreach (MenuAccess::items() as $item) {
            foreach ($item['actions'] ?? [] as $act) {
                $lebih = array_diff($act['default_roles'], $item['default_roles']);

                if ($lebih) {
                    $salah[] = sprintf(
                        '%s memberi %s, tapi role itu tidak boleh melihat menu %s',
                        $act['key'], implode('/', $lebih), $item['key']
                    );
                }
            }
        }

        $this->assertSame([], $salah, "Aksi yang mati sejak awal:\n" . implode("\n", $salah));
    }

    /* ─────────────────────────────────────────────────────────────
       4. Pemetaan route -> permission tidak saling menutupi.
       ───────────────────────────────────────────────────────────── */

    /**
     * permissionKeyForRoute memakai pencocokan pola dan mengambil yang pertama
     * cocok. Pola luas seperti 'management.*' bisa menelan route milik menu lain
     * tanpa ketahuan — route-nya lalu dijaga izin yang salah.
     */
    public function test_setiap_pola_route_terpetakan_ke_permission_miliknya_sendiri(): void
    {
        $salah = [];

        foreach (MenuAccess::items() as $item) {
            $semua = [['key' => $item['key'], 'match' => $item['match'] ?? []]];
            foreach ($item['actions'] ?? [] as $act) {
                $semua[] = ['key' => $act['key'], 'match' => $act['match'] ?? []];
            }

            foreach ($semua as $def) {
                foreach ($def['match'] as $pola) {
                    // Pola bisa mengandung '*', jadi diuji lewat nama route nyata.
                    foreach (Route::getRoutes() as $route) {
                        $nama = $route->getName();
                        if (!$nama || !Str::is($pola, $nama)) continue;

                        $hasil = MenuAccess::permissionKeyForRoute($nama);

                        if ($hasil !== $def['key']) {
                            $salah[] = sprintf(
                                "route '%s' (pola '%s' milik %s) malah dipetakan ke %s",
                                $nama, $pola, $def['key'], $hasil ?? 'NULL'
                            );
                        }
                    }
                }
            }
        }

        $this->assertSame([], $salah,
            "Pemetaan route tertutupi pola lain:\n" . implode("\n", array_unique($salah)));
    }

    /* ─────────────────────────────────────────────────────────────
       5. Menu yang tidak dikelola tetap terkunci.
       ───────────────────────────────────────────────────────────── */

    /**
     * Menu ber-manageable=false tidak punya saklar di layar, jadi satu-satunya
     * penjaganya adalah default_roles. Kalau ada yang bocor ke role biasa, tidak
     * akan ada yang menyadarinya dari halaman Hak Akses.
     */
    public function test_menu_khusus_developer_tidak_bocor_ke_role_lain(): void
    {
        $khusus = ['permissions', 'settings'];
        $salah  = [];

        foreach ($khusus as $key) {
            foreach (config('menus.manageable_roles', []) as $role) {
                if (MenuAccess::allowed($role, $key)) {
                    $salah[] = "{$key} terbuka untuk {$role}";
                }
            }
        }

        $this->assertSame([], $salah, implode("\n", $salah));
    }

    /* ───────────────────────── Pencetak matriks ───────────────────────── */

    private function cetak(string $judul, array $roles, array $baris): void
    {
        $lebar = 0;
        foreach ($baris as $b) $lebar = max($lebar, strlen($b['menu']));

        $garis = "\n  " . $judul . "\n  " . str_repeat('-', $lebar + 2 + count($roles) * 12) . "\n";
        $garis .= '  ' . str_pad('', $lebar + 2);
        foreach ($roles as $r) $garis .= str_pad(substr($r, 0, 10), 12);
        $garis .= "\n";

        foreach ($baris as $b) {
            $garis .= '  ' . str_pad($b['menu'], $lebar + 2);
            foreach ($roles as $r) $garis .= str_pad($b[$r] ?? '?', 12);
            $garis .= "\n";
        }

        fwrite(STDERR, $garis);
    }
}
