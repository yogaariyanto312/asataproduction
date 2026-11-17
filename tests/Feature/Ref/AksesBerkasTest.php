<?php

namespace Tests\Feature\Ref;

use App\Models\Note;
use App\Models\RoleMenuPermission;
use App\Models\User;
use App\Support\AksesBerkas;
use App\Support\MenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Celah lama: /file/{path} dan /thumb/{path} hanya butuh login, jadi siapa pun
 * yang sudah masuk bisa mengambil berkas apa pun di disk `public` asal tahu
 * path-nya — termasuk PDF gambar kerja yang tombol unduhnya disembunyikan
 * untuk role tertentu.
 */
class AksesBerkasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MenuAccess::flush();
        Storage::fake('public');
    }

    private function user(string $role): User
    {
        return User::create([
            'name'      => ucfirst($role),
            'username'  => $role . 'berkas',
            'email'     => $role . '.berkas@uji.test',
            'role'      => $role,
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    private function taruh(string $path): string
    {
        Storage::disk('public')->put($path, 'isi berkas uji');
        return $path;
    }

    private function ambil(User $user, string $path)
    {
        $this->flushSession();
        return $this->actingAs($user)->get('/file/' . $path);
    }

    /* ───────────── Inti celahnya ───────────── */

    /**
     * Visitor tidak punya izin apa pun atas Gambar Kerja selain melihat
     * daftarnya. Sebelum perbaikan, berkasnya tetap bisa diambil langsung.
     */
    public function test_role_tanpa_izin_gambar_kerja_tidak_bisa_mengambil_berkasnya(): void
    {
        $path = $this->taruh('gambar-kerja/rahasia-trafo.pdf');

        // Cabut izin melihat Gambar Kerja untuk visitor.
        RoleMenuPermission::create(['role' => 'visitor', 'menu_key' => 'gambar-kerja', 'allowed' => false]);
        MenuAccess::flush();

        $this->ambil($this->user('visitor'), $path)->assertForbidden();
    }

    /** Kontrol positif: yang memang berhak tetap bisa membukanya. */
    public function test_role_yang_berizin_tetap_bisa_mengambil_gambar_kerja(): void
    {
        $path = $this->taruh('gambar-kerja/boleh.pdf');

        // Bawaan: visitor boleh MELIHAT gambar kerja.
        $this->ambil($this->user('visitor'), $path)->assertOk();
        $this->ambil($this->user('admin'), $path)->assertOk();
    }

    public function test_foto_jadwal_mengikuti_izin_target_produksi(): void
    {
        $path = $this->taruh('schedule-photos/2026-09-20/jadwal.jpg');

        // Bawaan: visitor TIDAK boleh melihat Target Produksi.
        $this->ambil($this->user('visitor'), $path)->assertForbidden();
        $this->ambil($this->user('operator'), $path)->assertOk();
    }

    /** Folder yang tidak terdaftar ditolak, bukan dibiarkan lewat. */
    public function test_folder_tak_dikenal_ditolak(): void
    {
        $path = $this->taruh('entah-apa/rahasia.txt');

        $this->ambil($this->user('admin'), $path)->assertForbidden();
    }

    /** Avatar tetap terbuka untuk semua yang login — muncul di sidebar & chat. */
    public function test_avatar_tetap_bisa_dilihat_semua_yang_login(): void
    {
        $path = $this->taruh('avatars/about/about_1.webp');

        $this->ambil($this->user('visitor'), $path)->assertOk();
    }

    /** Tanpa login, keduanya tetap dilempar ke halaman masuk. */
    public function test_tanpa_login_tetap_diarahkan_ke_login(): void
    {
        $path = $this->taruh('gambar-kerja/apa-saja.pdf');

        $this->get('/file/' . $path)->assertRedirect(route('login'));
    }

    /* ───────────── Foto catatan: izin menu ATAU terlibat ───────────── */

    /**
     * Kartu Catatan di Dashboard bersifat self-scoped dan tidak dijaga izin menu
     * Catatan. Visitor bisa dikirimi catatan berfoto, jadi fotonya harus tetap
     * terbuka untuknya — kalau tidak, gambarnya jadi 403 di dashboard sendiri.
     */
    public function test_penerima_catatan_bisa_melihat_fotonya_meski_menu_catatan_tertutup(): void
    {
        $path    = $this->taruh('notes/photos/foto.jpg');
        $visitor = $this->user('visitor');
        $admin   = $this->user('admin');

        $this->assertFalse(MenuAccess::can($visitor, 'notes'),
            'prasyarat: visitor memang tidak boleh membuka menu Catatan');

        Note::create([
            'user_id'        => $admin->id,
            'target_user_id' => $visitor->id,
            'title'          => 'Untuk visitor',
            'photo_path'     => $path,
        ]);

        $this->ambil($visitor, $path)->assertOk();
    }

    /** Tapi orang lain yang tidak terlibat tetap ditolak. */
    public function test_orang_yang_tidak_terlibat_tidak_bisa_melihat_foto_catatan(): void
    {
        $path   = $this->taruh('notes/photos/foto-lain.jpg');
        $admin  = $this->user('admin');
        $lain   = $this->user('visitor');

        Note::create([
            'user_id'        => $admin->id,
            'target_user_id' => $admin->id,
            'title'          => 'Catatan admin',
            'photo_path'     => $path,
        ]);

        $this->ambil($lain, $path)->assertForbidden();
    }

    /* ───────────── Thumbnail lewat jalur yang sama ───────────── */

    public function test_thumbnail_ikut_dijaga(): void
    {
        Storage::disk('public')->put('gambar-kerja/gambar.jpg',
            UploadedFile::fake()->image('gambar.jpg', 40, 40)->getContent());

        RoleMenuPermission::create(['role' => 'visitor', 'menu_key' => 'gambar-kerja', 'allowed' => false]);
        MenuAccess::flush();

        $this->flushSession();
        $this->actingAs($this->user('visitor'))
            ->get('/thumb/gambar-kerja/gambar.jpg')
            ->assertForbidden();
    }

    /**
     * Ditolak SEBELUM keberadaan berkasnya diperiksa. Kalau 404 dijawab lebih
     * dulu, selisih jawaban 403/404 membocorkan path mana yang ada.
     */
    public function test_ditolak_sebelum_membocorkan_berkas_itu_ada_atau_tidak(): void
    {
        RoleMenuPermission::create(['role' => 'visitor', 'menu_key' => 'gambar-kerja', 'allowed' => false]);
        MenuAccess::flush();

        $visitor = $this->user('visitor');
        $ada     = $this->taruh('gambar-kerja/ada.pdf');

        $jawabanAda    = $this->ambil($visitor, $ada)->status();
        $jawabanTiada  = $this->ambil($visitor, 'gambar-kerja/tidak-ada.pdf')->status();

        $this->assertSame(403, $jawabanAda);
        $this->assertSame($jawabanAda, $jawabanTiada,
            'berkas yang ada dan yang tidak ada harus dijawab sama, supaya tidak bisa ditebak');
    }

    /* ───────────── Penjagaan peta folder ───────────── */

    /**
     * Folder unggahan baru harus didaftarkan di AksesBerkas, kalau tidak
     * berkasnya akan ditolak diam-diam untuk semua orang kecuali developer.
     * Test ini membaca folder yang benar-benar ada di disk `public`.
     */
    public function test_semua_folder_di_disk_public_sudah_dipetakan(): void
    {
        // Sengaja memakai disk asli, bukan Storage::fake, karena yang diperiksa
        // adalah folder yang betul-betul dipakai aplikasi.
        $nyata = \Illuminate\Support\Facades\Storage::build([
            'driver' => 'local',
            'root'   => storage_path('app/public'),
        ]);

        $folder = array_map(
            fn ($d) => basename($d),
            $nyata->directories()
        );

        if ($folder === []) {
            $this->markTestSkipped('disk public kosong di lingkungan ini');
        }

        $belum = array_diff($folder, AksesBerkas::folderDikenal());

        $this->assertSame([], array_values($belum),
            'Folder ini belum dipetakan di AksesBerkas::PETA: ' . implode(', ', $belum));
    }

    /**
     * Laravel mendaftarkan GET & PUT /storage/{path} tanpa middleware untuk tiap
     * disk lokal ber-'serve' => true. Pagarnya cuma URL bertanda tangan, jadi
     * satu-satunya rahasia adalah APP_KEY — dan PUT-nya menulis berkas.
     * Disk `local` tidak dipakai kode mana pun, jadi route-nya ditiadakan.
     */
    public function test_route_unggah_bawaan_laravel_tidak_terdaftar(): void
    {
        $nama = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->filter(fn ($n) => str_starts_with($n, 'storage.local'))
            ->values()
            ->all();

        $this->assertSame([], $nama,
            'route serve/upload bawaan masih terdaftar: ' . implode(', ', $nama));

        $this->assertFalse(config('filesystems.disks.local.serve'),
            "disk 'local' tidak dipakai aplikasi — 'serve' harus tetap false");
    }

    /** Developer tetap bisa mengambil apa pun, sama seperti bypass di MenuAccess. */
    public function test_developer_tetap_bisa_mengambil_apa_saja(): void
    {
        $path = $this->taruh('entah-apa/berkas.txt');

        $this->ambil($this->user('developer'), $path)->assertOk();
    }
}
