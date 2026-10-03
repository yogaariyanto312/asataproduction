<?php

namespace Tests\Feature\Ref;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Menu Manajemen Pengguna.
 *
 * Keluhan yang ditangani: setelah menambah/mengubah/menghapus pengguna, layar
 * terlempar ke halaman lama yang berdiri sendiri ("Manajemen Admin") dan tidak
 * punya jalan kembali. Berkas uji ini menjaga supaya semua jalur kelola
 * pengguna selalu berakhir kembali di menu Manajemen, pada tab yang benar.
 */
class ManajemenPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private function buat(string $peran, ?string $nama = null): User
    {
        $nama ??= ucfirst($peran) . ' Uji';
        $slug = strtolower(str_replace(' ', '', $nama)) . $peran;

        return User::create([
            'name'      => $nama,
            'username'  => $slug,
            'email'     => $slug . '@uji.test',
            'role'      => $peran,
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    /** asata: halaman React — data dibaca dari props Inertia, bukan HTML. */
    private function props(User $siapa, array $query = []): array
    {
        $this->flushSession();

        return $this->actingAs($siapa)->get(route('management.index', $query))->assertOk()
            ->viewData('page')['props'];
    }

    private function isiValid(array $ganti = []): array
    {
        return array_merge([
            'name'                  => 'Orang Baru',
            'email'                 => 'orangbaru@uji.test',
            'username'              => 'orangbaru',
            'password'              => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $ganti);
    }

    /* ── Halaman & tab ──────────────────────────────────────────────────── */

    public function test_halaman_manajemen_terbuka(): void
    {
        $this->actingAs($this->buat('developer'))
            ->get(route('management.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Management/Index'));
    }

    public function test_tab_menentukan_daftar_yang_tampil(): void
    {
        $dev = $this->buat('developer');
        $this->buat('admin', 'Admin Satu');
        $this->buat('operator', 'Operator Satu');

        $html = $this->actingAs($dev)->get(route('management.index', ['tab' => 'admin']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Admin Satu', $html);
        $this->assertStringNotContainsString('Operator Satu', $html,
            'Tab admin tidak boleh ikut mengirim data peran lain ke layar.');
    }

    public function test_tab_yang_tidak_dikenal_jatuh_ke_tab_aman(): void
    {
        $this->actingAs($this->buat('developer'))
            ->get(route('management.index', ['tab' => 'tukang-sihir']))
            ->assertOk();
    }

    public function test_jumlah_di_tab_mengikuti_pencarian(): void
    {
        $dev = $this->buat('developer');
        $this->buat('admin', 'Budi Admin');
        $this->buat('admin', 'Cici Admin');
        $this->buat('operator', 'Budi Operator');

        $html = $this->actingAs($dev)
            ->get(route('management.index', ['tab' => 'admin', 'search' => 'Budi']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Budi Admin', $html);
        $this->assertStringNotContainsString('Cici Admin', $html, 'Pencarian tidak menyaring.');
    }

    public function test_pencarian_tidak_melempar_keluar_dari_tab(): void
    {
        $props = $this->props($this->buat('developer'), ['tab' => 'supervisor', 'search' => 'apa saja']);

        // Kolom pencarian membawa tab yang sedang dibuka (dikirim balik apa adanya).
        $this->assertSame('supervisor', $props['tab']);
        $this->assertSame('apa saja', $props['search']);
    }

    public function test_pencarian_juga_lewat_username(): void
    {
        $dev = $this->buat('developer');
        $this->buat('admin', 'Nama Beda');

        $html = $this->actingAs($dev)
            ->get(route('management.index', ['tab' => 'admin', 'search' => 'namabedaadmin']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Nama Beda', $html);
    }

    /* ── Tidak ada lagi halaman yang berdiri sendiri ────────────────────── */

    public function test_halaman_daftar_lama_sudah_tidak_ada(): void
    {
        $dev = $this->buat('developer');

        foreach (['/admins', '/supervisors', '/mandors', '/operators', '/developers'] as $alamat) {
            $status = $this->actingAs($dev)->get($alamat)->status();

            // 405 karena alamat yang sama masih dipakai untuk menyimpan data
            // baru (POST); yang penting halamannya sudah tidak bisa dibuka.
            $this->assertContains($status, [404, 405],
                "Halaman lama {$alamat} masih bisa dibuka (status {$status}).");
        }
    }

    /* ── Setelah simpan/hapus selalu kembali ke Manajemen ───────────────── */

    public static function peranYangDikelolaDeveloper(): array
    {
        return [
            'admin'      => ['admins', 'admin'],
            'supervisor' => ['supervisors', 'supervisor'],
            'mandor'     => ['mandors', 'mandor'],
            'operator'   => ['operators', 'operator'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('peranYangDikelolaDeveloper')]
    public function test_tambah_pengguna_kembali_ke_tab_yang_benar(string $sumber, string $peran): void
    {
        $dev = $this->buat('developer');

        $this->actingAs($dev)
            ->post(route($sumber . '.store'), $this->isiValid())
            ->assertRedirect(route('management.index', ['tab' => $peran]));

        $this->assertDatabaseHas('users', ['email' => 'orangbaru@uji.test', 'role' => $peran]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('peranYangDikelolaDeveloper')]
    public function test_ubah_pengguna_kembali_ke_tab_yang_benar(string $sumber, string $peran): void
    {
        $dev  = $this->buat('developer');
        $user = $this->buat($peran);

        $this->actingAs($dev)
            ->put(route($sumber . '.update', $user), [
                'name'     => 'Nama Diubah',
                'email'    => $user->email,
                'username' => $user->username,
            ])
            ->assertRedirect(route('management.index', ['tab' => $peran]));

        $this->assertSame('Nama Diubah', $user->fresh()->name);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('peranYangDikelolaDeveloper')]
    public function test_hapus_pengguna_kembali_ke_tab_yang_benar(string $sumber, string $peran): void
    {
        $dev  = $this->buat('developer');
        $user = $this->buat($peran);

        $this->actingAs($dev)
            ->delete(route($sumber . '.destroy', $user))
            ->assertRedirect(route('management.index', ['tab' => $peran]));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_kelola_visitor_juga_kembali_ke_tabnya(): void
    {
        $dev = $this->buat('developer');

        $this->actingAs($dev)
            ->post(route('visitors.store'), $this->isiValid())
            ->assertRedirect(route('management.index', ['tab' => 'visitor']));
    }

    public function test_tombol_batal_mengarah_kembali_ke_manajemen(): void
    {
        $dev = $this->buat('developer');
        $props = $this->actingAs($dev)->get(route('admins.create'))->assertOk()->viewData('page')['props'];

        $this->assertSame(route('management.index', ['tab' => 'admin']), $props['resource']['indexUrl'],
            'Tombol Batal masih menunjuk halaman lama.');
    }

    /* ── Hak akses ──────────────────────────────────────────────────────── */

    /**
     * Admin mengelola semua peran di bawahnya. Supervisor & mandor dulu
     * terkunci untuk developer saja — keduanya ditambahkan belakangan dan
     * aturannya tidak ikut diperbarui, jadi admin tidak bisa menambah mandor.
     */
    public static function peranBawahanAdmin(): array
    {
        return [
            'operator'   => ['operator', 'operators'],
            'supervisor' => ['supervisor', 'supervisors'],
            'mandor'     => ['mandor', 'mandors'],
            'visitor'    => ['visitor', 'visitors'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('peranBawahanAdmin')]
    public function test_admin_bisa_menambah_mengubah_dan_menghapus_peran_bawahannya(string $peran, string $sumber): void
    {
        $admin = $this->buat('admin');

        $props = $this->props($admin, ['tab' => $peran]);
        $this->assertSame(route($sumber . '.create'), $props['createUrl'],
            "Admin seharusnya melihat tombol tambah {$peran}.");

        $this->actingAs($admin)->get(route($sumber . '.create'))->assertOk();

        $isi = $this->isiValid(['username' => 'baru' . $peran, 'email' => "baru{$peran}@uji.test"]);
        $this->actingAs($admin)->post(route($sumber . '.store'), $isi)->assertRedirect();

        $orang = User::where('username', 'baru' . $peran)->first();
        $this->assertNotNull($orang, "Admin gagal menambah {$peran}.");
        $this->assertSame($peran, $orang->role, 'Peran akun baru harus dikunci oleh alamatnya.');

        $this->actingAs($admin)
            ->put(route($sumber . '.update', $orang), array_merge($isi, ['name' => 'Nama Diubah', 'password' => '', 'password_confirmation' => '']))
            ->assertRedirect();
        $this->assertSame('Nama Diubah', $orang->fresh()->name);

        $this->actingAs($admin)->delete(route($sumber . '.destroy', $orang))->assertRedirect();
        $this->assertNull(User::find($orang->id), "Admin gagal menghapus {$peran}.");
    }

    public function test_admin_tetap_tidak_bisa_menambah_admin_atau_developer(): void
    {
        $admin = $this->buat('admin');

        $this->assertNull($this->props($admin, ['tab' => 'admin'])['createUrl'],
            'Tombol yang ujungnya ditolak server tidak perlu ditampilkan.');

        $this->actingAs($admin)->post(route('admins.store'), $this->isiValid())->assertForbidden();
        $this->actingAs($admin)->post(route('developers.store'), $this->isiValid())->assertForbidden();
        $this->assertNull(User::where('username', 'orangbaru')->first());
    }

    public function test_admin_tidak_bisa_mengubah_developer_lewat_alamat_mandor(): void
    {
        $admin = $this->buat('admin');
        $dev   = $this->buat('developer');

        // Alamat mandor kini terbuka untuk admin — pastikan tidak jadi pintu
        // untuk mengubah akun berperan lebih tinggi.
        $this->actingAs($admin)
            ->put(route('mandors.update', $dev), $this->isiValid(['name' => 'Dibajak']))
            ->assertNotFound();
        $this->actingAs($admin)->delete(route('supervisors.destroy', $dev))->assertNotFound();

        $this->assertSame('Developer Uji', $dev->fresh()->name);
        $this->assertSame('developer', $dev->fresh()->role);
    }

    public function test_operator_tidak_bisa_menambah_mandor(): void
    {
        $this->actingAs($this->buat('operator'))
            ->post(route('mandors.store'), $this->isiValid())
            ->assertForbidden();
    }

    public function test_tab_developer_tersembunyi_untuk_bukan_developer(): void
    {
        $admin = $this->buat('admin');
        $this->buat('developer', 'Developer Rahasia');

        $html = $this->actingAs($admin)->get(route('management.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Developer Rahasia', $html);
        $this->assertFalse($this->props($admin)['bisaLihat']['developer'], 'tab developer tidak boleh tampil');
    }

    public function test_bukan_developer_tidak_bisa_memaksa_membuka_tab_developer(): void
    {
        $admin = $this->buat('admin');
        $this->buat('developer', 'Developer Rahasia');

        $html = $this->actingAs($admin)
            ->get(route('management.index', ['tab' => 'developer']))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('Developer Rahasia', $html,
            'Tab developer tetap bisa dibuka lewat URL.');
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('management.index'))->assertRedirect(route('login'));
    }

    /* ── Perilaku lain ──────────────────────────────────────────────────── */

    public function test_tidak_bisa_menghapus_akun_sendiri(): void
    {
        $dev = $this->buat('developer');

        $this->actingAs($dev)->delete(route('developers.destroy', $dev));

        $this->assertDatabaseHas('users', ['id' => $dev->id]);
    }

    public function test_tombol_hapus_tidak_muncul_untuk_diri_sendiri(): void
    {
        $dev = $this->buat('developer');
        $baris = collect($this->props($dev, ['tab' => 'developer'])['pengguna'])->firstWhere('id', $dev->id);

        $this->assertTrue($baris['is_me'], 'penanda "Anda" harus ada');
        $this->assertNull($baris['deleteUrl'], 'Tombol hapus untuk akun sendiri seharusnya tidak dirender.');
    }

    public function test_status_operator_bisa_diubah_dari_daftar(): void
    {
        $dev = $this->buat('developer');
        $op  = $this->buat('operator');

        $this->actingAs($dev)->patch(route('operators.toggle-active', $op));

        $this->assertFalse((bool) $op->fresh()->is_active);
    }

    public function test_pengguna_peran_lain_tidak_bisa_diubah_lewat_alamat_peran_lain(): void
    {
        $dev = $this->buat('developer');
        $op  = $this->buat('operator');

        // Operator diubah lewat alamat admin — harus ditolak, bukan diam-diam
        // mengubah perannya.
        $this->actingAs($dev)
            ->put(route('admins.update', $op), [
                'name'  => 'Naik Pangkat',
                'email' => $op->email,
            ])
            ->assertNotFound();

        $this->assertSame('operator', $op->fresh()->role);
    }
}
