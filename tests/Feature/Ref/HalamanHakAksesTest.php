<?php

namespace Tests\Feature\Ref;

use App\Http\Controllers\ManagementController;
use App\Models\ActivityLog;
use App\Models\RoleMenuPermission;
use App\Models\User;
use App\Support\MenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Halaman "Hak Akses Menu & Aksi": tampilan matriksnya dan proses simpannya.
 *
 * Pelengkap MenuPermissionTest (yang menguji gate-nya). Di sini yang dijaga
 * adalah hal-hal yang sempat salah di halamannya sendiri.
 */
class HalamanHakAksesTest extends TestCase
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
            'name'      => ucfirst($role) . ' Uji',
            'username'  => $role . 'hakakses',
            'email'     => $role . '.hakakses@uji.test',
            'role'      => $role,
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    /* ───────────────────────── Simpan ───────────────────────── */

    /**
     * Versi lama memanggil updateOrCreate per sel: 5 role x 38 permission
     * menghasilkan 385 query untuk satu klik Simpan. Sekarang satu upsert.
     */
    public function test_simpan_tidak_lagi_menembak_ratusan_query(): void
    {
        $jumlah = 0;
        DB::listen(function () use (&$jumlah) { $jumlah++; });

        $this->actingAs($this->user('developer'))
            ->post(route('permissions.update'), ['allowed' => []])
            ->assertRedirect(route('permissions.index'));

        $this->assertLessThan(
            25, $jumlah,
            "Simpan sekali memakai {$jumlah} query — dulu 385, seharusnya sekali upsert."
        );
    }

    /** Hemat query tidak boleh mengorbankan kebenarannya. */
    public function test_simpan_menuliskan_nilai_yang_benar(): void
    {
        RoleMenuPermission::create(['role' => 'visitor', 'menu_key' => 'notes', 'allowed' => true]);
        MenuAccess::flush();

        $this->actingAs($this->user('developer'))->post(route('permissions.update'), [
            'allowed' => [
                'operator' => ['notes' => '1', 'notes.create' => '1'],
                // visitor sengaja tidak dikirim sama sekali -> harus jadi false
            ],
        ]);

        $this->assertTrue(
            RoleMenuPermission::where(['role' => 'operator', 'menu_key' => 'notes'])->value('allowed'),
            'yang dicentang harus tersimpan sebagai boleh'
        );
        $this->assertFalse(
            (bool) RoleMenuPermission::where(['role' => 'visitor', 'menu_key' => 'notes'])->value('allowed'),
            'baris lama yang tidak dicentang lagi harus dicabut, bukan dibiarkan'
        );
        $this->assertFalse(
            (bool) RoleMenuPermission::where(['role' => 'mandor', 'menu_key' => 'notes.delete'])->value('allowed'),
            'role yang tidak dikirim sama sekali tetap ditulis sebagai tidak boleh'
        );
    }

    /** Satu baris per sel, tidak berlipat ganda kalau disimpan berkali-kali. */
    public function test_simpan_berulang_tidak_menggandakan_baris(): void
    {
        $dev = $this->user('developer');

        $this->actingAs($dev)->post(route('permissions.update'), ['allowed' => []]);
        $pertama = RoleMenuPermission::count();

        $this->actingAs($dev)->post(route('permissions.update'), ['allowed' => []]);
        $this->assertSame($pertama, RoleMenuPermission::count(),
            'upsert harus menimpa baris yang sama, bukan menambah baris baru');
    }

    /* ───────────────────────── Tampilan ───────────────────────── */

    /** asata: halaman React — data matriks dibaca dari props Inertia. */
    private function props(): array
    {
        $dev = User::where('role', 'developer')->first() ?? $this->user('developer');

        return $this->actingAs($dev)->get(route('permissions.index'))->assertOk()
            ->viewData('page')['props'];
    }

    private function css(): string
    {
        return file_get_contents(resource_path('css/asata-ui.css'));
    }

    private function halaman(): string
    {
        return $this->actingAs($this->user('developer'))
            ->get(route('permissions.index'))
            ->assertOk()
            ->getContent();
    }

    /**
     * Label role dulu disalin ke dalam blade. Role yang ada di
     * manageable_roles tapi tidak ada di salinan itu akan tampil sebagai
     * ucfirst() tanpa keterangan — sekarang sumbernya satu.
     */
    public function test_label_role_diambil_dari_sumber_yang_sama_dengan_manajemen(): void
    {
        $html = $this->halaman();

        foreach (config('menus.manageable_roles') as $role) {
            $this->assertArrayHasKey($role, ManagementController::PERAN,
                "role {$role} tidak punya keterangan di ManagementController::PERAN");
            $this->assertStringContainsString(
                ManagementController::PERAN[$role]['label'], $html,
                "label role {$role} tidak muncul di halaman"
            );
        }
    }

    /** Jumlah pengguna tiap role ditampilkan supaya terlihat siapa yang terdampak. */
    public function test_menampilkan_jumlah_pengguna_tiap_role(): void
    {
        $this->user('operator');
        $this->user('developer');

        $this->assertSame(1, $this->props()['roleMeta']['operator']['user'],
            'kolom role harus menyebut berapa pengguna yang memakainya');
    }

    /**
     * Header aplikasi memakai z-10. Sel lengket tabel yang juga z-10 menimpa
     * judul halaman saat digulir — persis yang terlihat di layar Yoga.
     */
    public function test_sel_lengket_tabel_tidak_menimpa_header_aplikasi(): void
    {
        // asata: baris judul kolom lengket tepat di bawah header aplikasi (64px),
        // dengan z-index di bawah header (.au-topbar z-index 30).
        $this->assertMatchesRegularExpression(
            '/\.au-hak-tabel thead th \{[^}]*position: sticky;[^}]*top: 64px;[^}]*z-index: 2;/', $this->css(),
            'baris judul kolom harus ikut lengket di bawah header aplikasi');
    }

    /**
     * `sticky` mengacu ke scroll container TERDEKAT, bukan ke layar. Membungkus
     * tabel dengan overflow-x-auto (yang otomatis membuat overflow-y jadi auto)
     * atau overflow-hidden membuat baris judul kolom digeser 64px ke bawah di
     * dalam kartu: muncul pita kosong di atas dan baris pertama tertimpa.
     */
    public function test_tabel_tidak_dibungkus_scroll_container(): void
    {
        // asata: pembungkus tabel tidak boleh memakai overflow — itu mematikan sticky.
        preg_match('/\.au-hak-tabel \{([^}]*)\}/', $this->css(), $m);
        $this->assertNotEmpty($m, 'aturan .au-hak-tabel tidak ditemukan');
        $this->assertStringNotContainsString('overflow', $m[1],
            'pembungkus tabel memakai overflow — itu mematikan sticky pada baris judul kolom');
    }

    /**
     * Mobile-first: di ponsel hanya satu kolom role yang tampil. Sel role lain
     * tetap ada di DOM supaya nilainya tetap ikut terkirim saat Simpan.
     */
    public function test_di_ponsel_hanya_satu_kolom_role_yang_tampil(): void
    {
        // asata: kolom role lain disembunyikan lewat CSS di ponsel, tapi semua sel
        // tetap ada di state sehingga ikut terkirim saat Simpan.
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 767px\)\s*\{[^@]*\.au-hak-col:not\(\.is-hp\) \{ display: none; \}/s', $this->css(),
            'di ponsel hanya kolom role terpilih yang tampil');

        $state = $this->props()['state'];
        foreach (config('menus.manageable_roles') as $role) {
            $this->assertArrayHasKey($role . '|notes', $state, "sel role {$role} harus tetap ada di form");
        }
    }

    /** Menu tanpa ikon dulu menghasilkan <path d=""> kosong. */
    public function test_tidak_ada_path_svg_kosong(): void
    {
        $this->assertStringNotContainsString('d=""', $this->halaman());
    }

    /* ───────────────────── Menu terkunci ───────────────────── */

    /**
     * Menu ber-'locked' ditampilkan supaya daftar menunya lengkap, tapi tidak
     * boleh punya <input> — memberikannya ke role lain berarti role itu bisa
     * memberi dirinya sendiri semua izin.
     */
    public function test_menu_terkunci_tampil_tapi_tanpa_saklar(): void
    {
        $html = $this->halaman();

        $terkunci = array_values(array_filter(
            MenuAccess::items(),
            fn ($i) => ($i['manageable'] ?? false) && ($i['locked'] ?? false)
        ));

        $this->assertNotEmpty($terkunci, 'tidak ada menu terkunci untuk diuji');

        foreach ($terkunci as $item) {
            $this->assertStringContainsString($item['label'], $html,
                "menu terkunci {$item['label']} harus tetap tampil di daftar");

            foreach (config('menus.manageable_roles') as $role) {
                $this->assertStringNotContainsString(
                    'name="allowed[' . $role . '][' . $item['key'] . ']"', $html,
                    "menu terkunci {$item['key']} tidak boleh punya saklar untuk {$role}"
                );
            }
        }
    }

    /** Menu terkunci tidak pernah ditulis ke database, termasuk sebagai "tidak boleh". */
    public function test_menu_terkunci_tidak_ikut_tersimpan(): void
    {
        $this->actingAs($this->user('developer'))
            ->post(route('permissions.update'), ['allowed' => []]);

        foreach (MenuAccess::items() as $item) {
            if (!($item['locked'] ?? false)) continue;

            $this->assertSame(0,
                RoleMenuPermission::where('menu_key', $item['key'])->count(),
                "menu terkunci {$item['key']} tidak boleh punya baris di database"
            );
        }
    }

    /* ───────────────────── Jejak audit ───────────────────── */

    /**
     * Dulu catatannya selalu berbunyi sama ("Perbarui hak akses menu & aksi per
     * role"), jadi kalau ada izin berubah dan tidak ada yang mengaku, jejaknya
     * tidak bisa ditelusuri.
     */
    public function test_catatan_aktivitas_merinci_apa_yang_berubah(): void
    {
        $dev = $this->user('developer');

        // Kirim keadaan yang persis berlaku sekarang -> tidak ada yang berubah.
        // Tanpa ini, satu perubahan akan tenggelam di antara ratusan pencabutan.
        $this->actingAs($dev)->post(route('permissions.update'), ['allowed' => $this->payloadSaatIni()]);

        // Lalu ubah SATU hal saja: Visitor boleh menambah catatan.
        $payload = $this->payloadSaatIni();
        $payload['visitor']['notes']        = '1';
        $payload['visitor']['notes.create'] = '1';

        $this->actingAs($dev)->post(route('permissions.update'), ['allowed' => $payload]);

        $catatan = ActivityLog::latest('id')->value('description');

        $this->assertStringContainsString('Visitor', $catatan, $catatan);
        $this->assertStringContainsString('Catatan', $catatan, $catatan);
        $this->assertStringContainsString('DIBERI', $catatan, $catatan);
        $this->assertStringNotContainsString('DICABUT', $catatan,
            'hanya yang benar-benar berubah yang boleh tercatat');
        $this->assertStringNotContainsString('Perbarui hak akses menu & aksi per role', $catatan,
            'kalimat lama yang selalu sama tidak boleh dipakai lagi');
    }

    /**
     * Payload yang menggambarkan izin yang BERLAKU sekarang, seperti yang
     * dikirim peramban kalau tidak ada saklar yang disentuh.
     */
    private function payloadSaatIni(): array
    {
        $payload = [];

        foreach (MenuAccess::items() as $item) {
            if (!($item['manageable'] ?? false) || ($item['locked'] ?? false)) continue;

            $keys = array_merge([$item['key']], array_column($item['actions'] ?? [], 'key'));

            foreach (config('menus.manageable_roles') as $role) {
                foreach ($keys as $key) {
                    if (MenuAccess::allowed($role, $key)) $payload[$role][$key] = '1';
                }
            }
        }

        return $payload;
    }

    /** Pencabutan juga tercatat, bukan cuma pemberian. */
    public function test_catatan_aktivitas_mencatat_pencabutan(): void
    {
        $this->actingAs($this->user('developer'))
            ->post(route('permissions.update'), ['allowed' => []]);

        $catatan = ActivityLog::latest('id')->value('description');

        $this->assertStringContainsString('DICABUT', $catatan);
    }

    /** Menyimpan tanpa mengubah apa pun tidak boleh mengaku ada perubahan. */
    public function test_simpan_tanpa_perubahan_tercatat_apa_adanya(): void
    {
        $dev = $this->user('developer');

        // Simpan sekali supaya keadaan tersimpan = keadaan sekarang.
        $this->actingAs($dev)->post(route('permissions.update'), ['allowed' => []]);
        // Simpan lagi dengan isi yang sama persis.
        $this->actingAs($dev)->post(route('permissions.update'), ['allowed' => []]);

        $catatan = ActivityLog::latest('id')->value('description');

        $this->assertStringContainsString('tidak ada yang berubah', $catatan);
        $this->assertStringNotContainsString('DICABUT', $catatan);
    }

    /** Penanda "berbeda dari bawaan" butuh nilai bawaan ikut terkirim ke layar. */
    public function test_nilai_bawaan_ikut_dikirim_ke_layar(): void
    {
        $bawaan = $this->props()['bawaan'];

        // Kategori bawaannya developer saja -> admin bertanda bawaan false.
        $this->assertFalse($bawaan['admin|kategori'],
            'tiap saklar harus membawa nilai bawaannya supaya bisa ditandai & direset');
        // Catatan bawaannya semua role kecuali visitor -> admin true.
        $this->assertTrue($bawaan['admin|notes']);
    }

    /** Semua permission yang dikelola benar-benar muncul sebagai saklar. */
    public function test_semua_permission_yang_dikelola_punya_saklar(): void
    {
        $state  = $this->props()['state'];
        $roles  = config('menus.manageable_roles');
        $n      = 0;
        $hilang = [];

        foreach (MenuAccess::items() as $item) {
            $manageable = $item['manageable'] ?? false;
            if (!$manageable || ($item['locked'] ?? false)) continue;

            // asata: 'actions' = halaman selalu terlihat, hanya aksinya yang diatur.
            $keys = array_merge(
                $manageable === 'actions' ? [] : [$item['key']],
                array_column($item['actions'] ?? [], 'key')
            );

            foreach ($keys as $key) {
                foreach ($roles as $role) {
                    if (!array_key_exists($role . '|' . $key, $state)) {
                        $hilang[] = "{$role} x {$key}";
                    }
                    $n++;
                }
            }
        }

        $this->assertSame([], $hilang, 'Saklar tidak ada di halaman: ' . implode(', ', $hilang));
        $this->assertGreaterThan(100, $n, 'matriksnya jauh lebih kecil dari yang diharapkan');
    }
}
