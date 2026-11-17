<?php

namespace Tests\Feature\Ref;

use App\Models\BotSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\RoleMenuPermission;
use App\Models\SchedulePhoto;
use App\Models\User;
use App\Services\Telegram\BotPerintah;
use App\Services\Telegram\PenautanAkun;
use App\Support\MenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Perintah bot Telegram.
 *
 * Celah yang ditutup di sini nyata: sebelumnya webhook TIDAK memeriksa siapa
 * pengirimnya sama sekali, jadi siapa pun yang menemukan username bot bisa
 * mengirim /jadwal beserta foto dan foto itu tersimpan sebagai jadwal produksi
 * resmi.
 *
 * BotPerintah sengaja tidak menyentuh jaringan, jadi seluruh aturannya diuji
 * langsung di sini tanpa HTTP tiruan.
 */
class BotTelegramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function setting(array $isi = []): BotSetting
    {
        $setting = BotSetting::instance();
        $setting->update(array_merge([
            'telegram_token'   => '1234567890:AAFabcdefghijklmnopqrstuvwxyz012345',
            'telegram_chat_id' => '-100111',
        ], $isi));

        return $setting->fresh();
    }

    private function user(string $role, ?string $telegramId = null): User
    {
        $user = User::create([
            'name'      => ucfirst($role) . ' Bot',
            'username'  => $role . 'bot',
            'email'     => $role . '.bot@uji.test',
            'role'      => $role,
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);

        if ($telegramId) {
            $user->forceFill(['telegram_user_id' => $telegramId, 'telegram_linked_at' => now()])->save();
        }

        return $user;
    }

    /** Bangun satu pesan Telegram. */
    private function pesan(string $teks, string $fromId = '555', string $chatId = '555'): array
    {
        return [
            'text' => $teks,
            'from' => ['id' => $fromId],
            'chat' => ['id' => $chatId],
        ];
    }

    private function jalankan(array $pesan, ?BotSetting $setting = null): array
    {
        MenuAccess::flush();

        return (new BotPerintah($setting ?? $this->setting()))->tangani($pesan);
    }

    private function teksPertama(array $balasan): string
    {
        return $balasan[0]['teks'] ?? '';
    }

    /* ═══════════════ Otorisasi ═══════════════ */

    /** Inti celahnya: orang asing tidak boleh bisa apa-apa. */
    public function test_orang_tak_dikenal_tidak_bisa_memakai_perintah(): void
    {
        $balasan = $this->jalankan($this->pesan('/produksi', '999', '999'));

        $this->assertStringContainsString('belum tertaut', $this->teksPertama($balasan));
    }

    public function test_orang_tak_dikenal_tidak_bisa_memulai_unggah_jadwal(): void
    {
        $balasan = $this->jalankan($this->pesan('/jadwal upload', '999', '999'));

        $this->assertStringContainsString('belum tertaut', $this->teksPertama($balasan));
        $this->assertNull(cache('tg_sesi_999'), 'sesi unggah tidak boleh terbuka untuk orang asing');
    }

    /** Foto dari orang asing didiamkan — tidak ada sesi, tidak ada yang tersimpan. */
    public function test_foto_tanpa_sesi_diabaikan(): void
    {
        $balasan = $this->jalankan([
            'photo' => [['file_id' => 'abc']],
            'from'  => ['id' => '999'],
            'chat'  => ['id' => '999'],
        ]);

        $this->assertSame([], $balasan);
    }

    /** Grup yang terdaftar di Settings boleh bertanya, tapi tidak boleh menulis. */
    public function test_grup_terdaftar_boleh_membaca(): void
    {
        $balasan = $this->jalankan($this->pesan('/produksi', '777', '-100111'));

        $this->assertStringContainsString('Produksi', $this->teksPertama($balasan));
    }

    public function test_grup_terdaftar_tetap_tidak_boleh_menulis(): void
    {
        $balasan = $this->jalankan($this->pesan('/jadwal upload', '777', '-100111'));

        $this->assertStringContainsString('bot harus tahu Anda siapa', $this->teksPertama($balasan));
        $this->assertNull(cache('tg_sesi_777'));
    }

    /* ═══════════════ Izin mengikuti aplikasi ═══════════════ */

    /** Izin bot memakai MenuAccess, bukan daftar terpisah yang mudah basi. */
    public function test_perintah_tulis_menuntut_izin_yang_sama_dengan_aplikasi(): void
    {
        $this->user('supervisor', '555');   // supervisor tidak boleh mengunggah gambar kerja

        $balasan = $this->jalankan($this->pesan('/gambar Trafo PLN'));

        $this->assertStringContainsString('tidak punya izin', $this->teksPertama($balasan));
        $this->assertNull(cache('tg_sesi_555'));
    }

    public function test_yang_berizin_bisa_memulai_unggah(): void
    {
        $this->user('admin', '555');     // admin boleh gambar-kerja.upload

        $balasan = $this->jalankan($this->pesan('/gambar Trafo PLN'));

        $this->assertStringContainsString('Unggah Gambar Kerja', $this->teksPertama($balasan));
        $this->assertSame('gambar', cache('tg_sesi_555')['jenis']);
    }

    /** Mencabut izin di halaman Hak Akses langsung berlaku juga untuk bot. */
    public function test_mencabut_izin_di_aplikasi_langsung_memblokir_bot(): void
    {
        $this->user('admin', '555');

        RoleMenuPermission::create(['role' => 'admin', 'menu_key' => 'gambar-kerja', 'allowed' => false]);
        MenuAccess::flush();

        $balasan = $this->jalankan($this->pesan('/gambar Trafo PLN'));

        $this->assertStringContainsString('tidak punya izin', $this->teksPertama($balasan));
    }

    public function test_pengguna_nonaktif_dianggap_tidak_tertaut(): void
    {
        $user = $this->user('admin', '555');
        $user->update(['is_active' => false]);

        $balasan = $this->jalankan($this->pesan('/produksi'));

        $this->assertStringContainsString('belum tertaut', $this->teksPertama($balasan));
    }

    /* ═══════════════ Penautan akun ═══════════════ */

    public function test_kode_penautan_menautkan_akun(): void
    {
        $user = $this->user('supervisor');
        $kode = PenautanAkun::buatKode($user);

        $balasan = $this->jalankan($this->pesan("/tautkan {$kode}"));

        $this->assertStringContainsString('Akun tertaut', $this->teksPertama($balasan));
        $this->assertSame('555', $user->fresh()->telegram_user_id);
    }

    public function test_kode_salah_ditolak(): void
    {
        $balasan = $this->jalankan($this->pesan('/tautkan SALAH1'));

        $this->assertStringContainsString('kedaluwarsa', $this->teksPertama($balasan));
    }

    /** Kode sekali pakai — kalau bocor, tidak bisa dipakai ulang. */
    public function test_kode_hanya_bisa_dipakai_sekali(): void
    {
        $user = $this->user('supervisor');
        $kode = PenautanAkun::buatKode($user);

        $this->jalankan($this->pesan("/tautkan {$kode}", '555'));
        $balasan = $this->jalankan($this->pesan("/tautkan {$kode}", '666'));

        $this->assertStringContainsString('kedaluwarsa', $this->teksPertama($balasan));
        $this->assertSame('555', $user->fresh()->telegram_user_id, 'penaut kedua tidak boleh merebut akun');
    }

    /** Satu akun Telegram tidak boleh menempel pada dua pengguna sekaligus. */
    public function test_menautkan_ulang_melepas_tautan_lama(): void
    {
        $lama = $this->user('operator', '555');
        $baru = $this->user('admin');

        $this->jalankan($this->pesan('/tautkan ' . PenautanAkun::buatKode($baru), '555'));

        $this->assertNull($lama->fresh()->telegram_user_id);
        $this->assertSame('555', $baru->fresh()->telegram_user_id);
    }

    public function test_pengguna_nonaktif_tidak_bisa_menautkan(): void
    {
        $user = $this->user('supervisor');
        $kode = PenautanAkun::buatKode($user);
        $user->update(['is_active' => false]);

        $balasan = $this->jalankan($this->pesan("/tautkan {$kode}"));

        $this->assertStringContainsString('kedaluwarsa', $this->teksPertama($balasan));
        $this->assertNull($user->fresh()->telegram_user_id);
    }

    /* ═══════════════ /help ═══════════════ */

    /** Dulu perintah tak dikenal didiamkan, sehingga bot terlihat rusak. */
    public function test_perintah_tak_dikenal_dijawab_bukan_didiamkan(): void
    {
        $this->user('admin', '555');

        $balasan = $this->jalankan($this->pesan('/entahapa'));

        $this->assertStringContainsString('tidak dikenal', $this->teksPertama($balasan));
        $this->assertStringContainsString('/help', $this->teksPertama($balasan));
    }

    public function test_help_hanya_menampilkan_yang_boleh_dipakai(): void
    {
        $this->user('supervisor', '555');

        $teks = $this->teksPertama($this->jalankan($this->pesan('/help')));

        $this->assertStringContainsString('/help', $teks);
        $this->assertStringNotContainsString('/lapor', $teks, 'supervisor tidak boleh mencatat produksi');
        $this->assertStringNotContainsString('/gambar', $teks);
    }

    public function test_obrolan_biasa_di_grup_didiamkan(): void
    {
        $this->assertSame([], $this->jalankan($this->pesan('halo semua', '777', '-100111')));
    }

    /** Di grup Telegram menambahkan nama bot: "/help@qcbot". */
    public function test_perintah_bernama_bot_tetap_dikenali(): void
    {
        $this->user('admin', '555');

        $teks = $this->teksPertama($this->jalankan($this->pesan('/help@qcbot', '555', '-100111')));

        $this->assertStringContainsString('Perintah yang tersedia', $teks);
    }

    /* ═══════════════ Penautan lewat halaman Profil ═══════════════ */

    public function test_halaman_profil_bisa_membuat_kode(): void
    {
        $user = $this->user('supervisor');

        $res = $this->actingAs($user)->post(route('profile.telegram.kode'));

        $res->assertRedirect();
        $kode = session('telegram_kode');

        $this->assertNotEmpty($kode);
        $this->assertSame($user->id, PenautanAkun::tukarkan($kode, '555')?->id);
    }

    public function test_halaman_profil_bisa_memutus_tautan(): void
    {
        $user = $this->user('operator', '555');

        $this->actingAs($user)->delete(route('profile.telegram.putus'))->assertRedirect();

        $this->assertNull($user->fresh()->telegram_user_id);
    }

    /** Tiap orang hanya boleh menautkan akunnya sendiri. */
    public function test_kode_selalu_untuk_pengguna_yang_sedang_login(): void
    {
        $a = $this->user('supervisor');
        $b = $this->user('admin');

        $this->actingAs($a)->post(route('profile.telegram.kode'));
        $kode = session('telegram_kode');

        $this->assertSame($a->id, PenautanAkun::tukarkan($kode, '555')?->id);
        $this->assertNull($b->fresh()->telegram_user_id);
    }

    /* ─────────────── Hanya developer, admin, supervisor ─────────────── */

    public static function peranTanpaTelegram(): array
    {
        return ['operator' => ['operator'], 'mandor' => ['mandor'], 'visitor' => ['visitor']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('peranTanpaTelegram')]
    public function test_peran_lain_tidak_bisa_membuat_kode(string $peran): void
    {
        $user = $this->user($peran);

        $this->actingAs($user)->post(route('profile.telegram.kode'))->assertForbidden();
        $this->assertNull(session('telegram_kode'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('peranTanpaTelegram')]
    public function test_kartu_telegram_tidak_tampil_untuk_peran_lain(string $peran): void
    {
        // asata: kartu dirender dari prop `telegram` (null = tidak tampil).
        $props = $this->actingAs($this->user($peran))->get(route('profile.edit'))->assertOk()->viewData('page')['props'];

        $this->assertNull($props['telegram']);
    }

    public function test_kartu_telegram_tampil_untuk_developer_admin_supervisor(): void
    {
        foreach (['developer', 'admin', 'supervisor'] as $peran) {
            // Sesi dikosongkan tiap ganti orang: AuthenticateSession menolak
            // (302) sesi yang hash password-nya milik pengguna sebelumnya.
            $this->flushSession();
            $props = $this->actingAs($this->user($peran))->get(route('profile.edit'))->assertOk()->viewData('page')['props'];
            $this->assertSame(route('profile.telegram.kode'), $props['telegram']['kodeUrl'] ?? null, "{$peran} harus bisa menautkan.");
        }
    }

    /** Yang telanjur tertaut sebelum aturan ini tetap bisa memutus tautannya. */
    public function test_tautan_lama_peran_lain_masih_bisa_diputus(): void
    {
        $user = $this->user('operator', '555');

        $props = $this->actingAs($user)->get(route('profile.edit'))->viewData('page')['props'];
        $this->assertSame(route('profile.telegram.putus'), $props['telegram']['putusUrl']);
        $this->assertNull($props['telegram']['kodeUrl'], 'Peran lain tidak boleh membuat kode baru.');

        $this->actingAs($user)->delete(route('profile.telegram.putus'))->assertRedirect();
        $this->assertNull($user->fresh()->telegram_user_id);
    }

    /** Tautan lama operator tidak lagi dikenali bot, jadi tidak bisa mengubah data. */
    public function test_tautan_lama_peran_lain_tidak_dikenali_bot(): void
    {
        $this->user('operator', '555');

        $teks = $this->teksPertama($this->jalankan($this->pesan('/lapor 2601 12')));

        $this->assertStringContainsString('belum tertaut', $teks);
        $this->assertStringContainsString('khusus developer, admin, dan supervisor', $teks);
    }

    /** Perannya diturunkan dalam 10 menit antara kode dibuat dan dikirim. */
    public function test_kode_ditolak_kalau_perannya_berubah_sebelum_ditukar(): void
    {
        $user = $this->user('supervisor');
        $kode = PenautanAkun::buatKode($user);
        $user->update(['role' => 'operator']);

        $teks = $this->teksPertama($this->jalankan($this->pesan("/tautkan {$kode}")));

        $this->assertStringContainsString('kedaluwarsa', $teks);
        $this->assertNull($user->fresh()->telegram_user_id);
    }

    public function test_pengingat_tidak_dikirim_ke_tautan_lama_peran_lain(): void
    {
        $op = $this->user('operator', '555');
        $this->catatan(['title' => 'Untuk operator', 'user_id' => $op->id, 'target_user_id' => $op->id]);

        $this->assertSame([], \App\Services\Telegram\Pemberitahuan::pengingatCatatan());
    }

    public function test_tutorial_tautkan_menyebut_peran_yang_boleh(): void
    {
        $this->user('admin', '555');

        $teks = $this->teksPertama($this->jalankan($this->pesan('/tutorial tautkan')));

        $this->assertStringContainsString('Khusus developer, admin, dan supervisor', $teks);
    }

    /** telegram_user_id tidak boleh bisa diisi lewat pengisian massal. */
    public function test_id_telegram_tidak_bisa_diisi_massal(): void
    {
        $user = $this->user('operator');

        $user->fill(['telegram_user_id' => '999'])->save();

        $this->assertNull($user->fresh()->telegram_user_id);
    }

    /* ═══════════════ Perintah baca ═══════════════ */

    private function produk(string $seri, string $kategori = 'Tangki'): Product
    {
        $cat = Category::firstOrCreate(['name' => $kategori], ['has_manual_serial' => false]);

        return Product::create([
            'category_id' => $cat->id,
            'name'        => "Trafo {$seri}",
            'series'      => $seri,
            'is_active'   => true,
        ]);
    }

    public function test_produksi_menampilkan_angka_hari_ini(): void
    {
        $admin  = $this->user('admin', '555');
        $produk = $this->produk('2601');

        ProductionLog::create([
            'product_id' => $produk->id, 'user_id' => $admin->id,
            'production_date' => now()->toDateString(),
            'up_qty' => 0, 'bt_qty' => 0, 'total_qty' => 12, 'reject_qty' => 2,
        ]);

        $teks = $this->teksPertama($this->jalankan($this->pesan('/produksi')));

        $this->assertStringContainsString('12 unit', $teks);
        $this->assertStringContainsString('Tangki', $teks);
    }

    public function test_produksi_tanpa_data_menjawab_dengan_jelas(): void
    {
        $this->user('admin', '555');

        $this->assertStringContainsString(
            'Belum ada data produksi',
            $this->teksPertama($this->jalankan($this->pesan('/produksi')))
        );
    }

    public function test_role_tanpa_izin_laporan_tidak_bisa_melihat_reject(): void
    {
        // Izin Laporan supervisor dicabut di Hak Akses, jadi /reject ikut tertutup.
        $this->user('supervisor', '555');
        RoleMenuPermission::create(['role' => 'supervisor', 'menu_key' => 'laporan', 'allowed' => false]);
        MenuAccess::flush();

        $this->assertStringContainsString(
            'tidak punya izin',
            $this->teksPertama($this->jalankan($this->pesan('/reject')))
        );
    }

    public function test_jadwal_mengirim_berkasnya(): void
    {
        Storage::fake('public');
        $this->user('admin', '555');

        // Kuncinya tanggal SENIN minggu berjalan, bukan hari ini.
        Storage::disk('public')->put('schedule-photos/x/jadwal.jpg', 'isi');
        SchedulePhoto::create([
            'target_date' => \App\Services\Telegram\Tanggal::awalMinggu(),
            'file_path'   => 'schedule-photos/x/jadwal.jpg',
        ]);

        $balasan = $this->jalankan($this->pesan('/jadwal'));

        $this->assertSame('foto', $balasan[0]['jenis']);
        $this->assertSame('isi', $balasan[0]['isi']);
    }

    public function test_jadwal_kosong_menjelaskan_cara_mengunggah(): void
    {
        $this->user('admin', '555');

        $teks = $this->teksPertama($this->jalankan($this->pesan('/jadwal')));

        $this->assertStringContainsString('Belum ada jadwal', $teks);
        $this->assertStringContainsString('/jadwal upload', $teks);
    }

    /* ═══════════════ /info dan /tutorial ═══════════════ */

    public function test_info_menyebut_versi_dan_identitas_penanya(): void
    {
        \App\Models\Changelog::create(['type' => 'feature', 'version' => 'v9.9', 'title' => 'Uji']);
        $this->user('admin', '555');

        $teks = $this->teksPertama($this->jalankan($this->pesan('/info')));

        $this->assertStringContainsString('v9.9', $teks);
        $this->assertStringContainsString('Admin Bot', $teks, 'menyebut siapa yang bertanya');
        $this->assertStringContainsString('admin', $teks);
    }

    /** Daftar fitur disaring izin — menampilkan yang pasti ditolak cuma bikin kecewa. */
    public function test_info_hanya_menyebut_fitur_yang_boleh_dipakai(): void
    {
        $this->user('supervisor', '555');
        RoleMenuPermission::create(['role' => 'supervisor', 'menu_key' => 'targets', 'allowed' => false]);
        MenuAccess::flush();

        $teks = $this->teksPertama($this->jalankan($this->pesan('/info')));

        $this->assertStringNotContainsString('/lapor', $teks);
        $this->assertStringNotContainsString('/gambar', $teks);
        // Menu Target Produksi dicabut, jadi /jadwal & /target tidak boleh muncul.
        $this->assertStringNotContainsString('/jadwal', $teks);
        $this->assertStringContainsString('/produksi', $teks);
    }

    public function test_info_menyebut_belum_tertaut_untuk_orang_grup(): void
    {
        $teks = $this->teksPertama($this->jalankan($this->pesan('/info', '777', '-100111')));

        $this->assertStringContainsString('belum tertaut', $teks);
    }

    /** Kabar otomatis hanya dijanjikan kalau Telegram memang menyala. */
    public function test_info_tidak_menjanjikan_kabar_saat_telegram_mati(): void
    {
        $this->user('admin', '555');
        $setting = $this->setting(['telegram_enabled' => false]);

        $teks = $this->teksPertama($this->jalankan($this->pesan('/info'), $setting));

        $this->assertStringContainsString('dimatikan', $teks);
        $this->assertStringNotContainsString('07:00', $teks);
    }

    public function test_info_menyebut_batas_reject_yang_berlaku(): void
    {
        $this->user('admin', '555');
        // Bagian "kabar otomatis" hanya ditampilkan saat Telegram menyala.
        $setting = $this->setting(['reject_threshold' => 7.5, 'telegram_enabled' => true]);

        $teks = $this->teksPertama($this->jalankan($this->pesan('/info'), $setting));

        $this->assertStringContainsString('7.50%', $teks);
    }

    public function test_tutorial_menampilkan_tombol_topik(): void
    {
        $this->user('admin', '555');

        $balasan = $this->jalankan($this->pesan('/tutorial'));

        $this->assertArrayHasKey('tombol', $balasan[0]);

        $semua = array_merge(...$balasan[0]['tombol']);
        $data  = array_column($semua, 'callback_data');

        $this->assertContains('tut:jadwal', $data);
        $this->assertContains('tut:tautkan', $data);
    }

    /** Topik yang tidak boleh dipakai tidak perlu muncul sebagai pilihan. */
    public function test_tutorial_menyaring_topik_sesuai_izin(): void
    {
        $this->user('supervisor', '555');

        $balasan = $this->jalankan($this->pesan('/tutorial'));
        $data    = array_column(array_merge(...$balasan[0]['tombol']), 'callback_data');

        $this->assertNotContains('tut:lapor', $data);
        $this->assertNotContains('tut:gambar', $data);
        $this->assertContains('tut:tautkan', $data);
    }

    public function test_tutorial_topik_langsung_lewat_argumen(): void
    {
        $this->user('admin', '555');

        $teks = $this->teksPertama($this->jalankan($this->pesan('/tutorial jadwal')));

        $this->assertStringContainsString('satu minggu penuh', $teks);
        $this->assertStringContainsString('/jadwal upload', $teks);
    }

    public function test_tutorial_topik_tak_dikenal_menyebut_yang_ada(): void
    {
        $this->user('admin', '555');

        $teks = $this->teksPertama($this->jalankan($this->pesan('/tutorial entahapa')));

        $this->assertStringContainsString('tidak dikenal', $teks);
        $this->assertStringContainsString('jadwal', $teks);
    }

    public function test_tombol_tutorial_membuka_topiknya(): void
    {
        $this->user('admin', '555');

        $balasan = (new BotPerintah($this->setting()))->tanganiTombol([
            'data' => 'tut:gambar',
            'from' => ['id' => '555'],
        ]);

        $this->assertStringContainsString('kategori', $this->teksPertama($balasan));
        $this->assertStringContainsString('26T0282061', $this->teksPertama($balasan));
    }

    /** Tombol tutorial tidak boleh mengganggu sesi unggah yang sedang berjalan. */
    public function test_tombol_tutorial_tidak_merusak_sesi_gambar(): void
    {
        $this->user('admin', '555');
        $this->jalankan($this->pesan('/gambar Trafo PLN'));

        (new BotPerintah($this->setting()))->tanganiTombol([
            'data' => 'tut:tautkan',
            'from' => ['id' => '555'],
        ]);

        $this->assertSame('kategori', cache('tg_sesi_555')['langkah'],
            'sesi gambar harus tetap di langkahnya');
    }

    public function test_topik_tanpa_izin_dijelaskan_bukan_ditampilkan(): void
    {
        $this->user('supervisor', '555');

        $balasan = (new BotPerintah($this->setting()))->tanganiTombol([
            'data' => 'tut:lapor',
            'from' => ['id' => '555'],
        ]);

        $this->assertStringContainsString('tidak punya izin', $this->teksPertama($balasan));
    }

    /** Semua topik harus punya isinya — kalau tidak, tombolnya menuju kekosongan. */
    public function test_semua_topik_tutorial_ada_isinya(): void
    {
        $this->user('developer', '555');

        foreach (array_keys(\App\Services\Telegram\Panduan::topik()) as $kunci) {
            $isi = \App\Services\Telegram\Panduan::isiTopik($kunci, null);

            $this->assertNotNull($isi, "topik {$kunci} tidak punya isi");
            $this->assertGreaterThan(80, strlen($isi), "isi topik {$kunci} terlalu pendek");
        }
    }

    public function test_help_ikut_menyebut_info_dan_tutorial(): void
    {
        $this->user('supervisor', '555');

        $teks = $this->teksPertama($this->jalankan($this->pesan('/help')));

        $this->assertStringContainsString('/info', $teks);
        $this->assertStringContainsString('/tutorial', $teks);
    }

    /* ═══════════════ Jadwal: mingguan, bukan harian ═══════════════ */

    /**
     * Bug yang dialami Yoga: foto jadwal "hilang besoknya".
     *
     * Halaman Target Produksi mencari foto dengan kunci tanggal SENIN
     * (now()->startOfWeek(MONDAY)), sedangkan bot lama menyimpannya dengan
     * tanggal hari unggah. Kalau tidak kebetulan hari Senin, fotonya tersimpan
     * di tanggal yang tidak pernah dicari siapa pun.
     */
    public function test_unggah_jadwal_memakai_tanggal_senin_bukan_hari_ini(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00')); // Kamis

        $this->user('admin', '555');
        $this->jalankan($this->pesan('/jadwal upload'));

        $this->assertSame('2026-09-21', cache('tg_sesi_555')['tanggal'],
            'harus Senin minggu itu, bukan Kamis tanggal unggahnya');
    }

    public function test_pesan_unggah_menyebut_rentang_mingguan(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
        $this->user('admin', '555');

        $teks = $this->teksPertama($this->jalankan($this->pesan('/jadwal upload')));

        $this->assertStringContainsString('21 Sep', $teks);
        $this->assertStringContainsString('27 Sep', $teks);
        $this->assertStringContainsString('satu minggu penuh', $teks);
    }

    /** Foto yang diunggah hari apa pun harus tetap ketemu sepanjang minggu itu. */
    public function test_jadwal_ketemu_sepanjang_minggu(): void
    {
        Storage::fake('public');
        $this->user('admin', '555');

        Storage::disk('public')->put('schedule-photos/2026-09-21/jadwal.jpg', 'isi');
        SchedulePhoto::create([
            'target_date' => '2026-09-21',
            'file_path'   => 'schedule-photos/2026-09-21/jadwal.jpg',
        ]);

        foreach (['2026-09-21', '2026-09-24', '2026-09-27'] as $hari) {
            $this->travelTo(\Carbon\Carbon::parse($hari . ' 09:00:00'));

            $balasan = $this->jalankan($this->pesan('/jadwal'));

            $this->assertSame('foto', $balasan[0]['jenis'], "jadwal harus tetap ketemu pada {$hari}");
        }
    }

    public function test_jadwal_minggu_depan_bisa_dipilih(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
        $this->user('admin', '555');

        $this->jalankan($this->pesan('/jadwal upload depan'));

        $this->assertSame('2026-09-28', cache('tg_sesi_555')['tanggal']);
    }

    /* ═══════════════ Gambar kerja: dituntun bertahap ═══════════════ */

    public function test_gambar_menanyakan_kategori_dengan_tombol(): void
    {
        $this->user('admin', '555');

        $balasan = $this->jalankan($this->pesan('/gambar Trafo PLN'));

        $this->assertArrayHasKey('tombol', $balasan[0]);
        $this->assertSame(['PLN', 'Swasta', 'Type Test'], array_column($balasan[0]['tombol'][0], 'text'));
        $this->assertSame('kategori', cache('tg_sesi_555')['langkah']);
    }

    public function test_gambar_tanpa_judul_menanyakan_judulnya_dulu(): void
    {
        $this->user('admin', '555');

        $teks = $this->teksPertama($this->jalankan($this->pesan('/gambar')));

        $this->assertStringContainsString('judul', $teks);
        $this->assertSame('judul', cache('tg_sesi_555')['langkah'],
            'sesi harus tetap dibuka supaya jawabannya bisa langsung diketik');
    }

    /** Alur penuh: judul -> kategori -> seri/kva -> berkas. */
    public function test_alur_penuh_gambar_kerja(): void
    {
        $this->user('admin', '555');

        $this->jalankan($this->pesan('/gambar'));

        // Jawaban judul diketik biasa, bukan perintah.
        $this->jalankan($this->pesan('Trafo Distribusi'));
        $this->assertSame('kategori', cache('tg_sesi_555')['langkah']);

        // Tombol kategori ditekan.
        $balasan = (new BotPerintah($this->setting()))->tanganiTombol([
            'data' => 'kat:swasta',
            'from' => ['id' => '555'],
        ]);
        $this->assertStringContainsString('seri', $this->teksPertama($balasan));
        $this->assertSame('swasta', cache('tg_sesi_555')['kategori']);

        // Seri + KVA.
        $balasan = $this->jalankan($this->pesan('26T0282061 4000KVA'));

        $sesi = cache('tg_sesi_555');
        $this->assertSame('26T0282061', $sesi['seri']);
        $this->assertSame('4000', $sesi['kva']);
        $this->assertSame(2026, $sesi['tahun'], 'tahun diambil dari dua angka pertama seri');
        $this->assertSame('berkas', $sesi['langkah']);
        $this->assertStringContainsString('4000 KVA', $this->teksPertama($balasan));
    }

    public function test_seri_boleh_dilewati(): void
    {
        $this->user('admin', '555');
        $this->jalankan($this->pesan('/gambar Trafo PLN'));

        (new BotPerintah($this->setting()))->tanganiTombol(['data' => 'kat:pln', 'from' => ['id' => '555']]);
        $this->jalankan($this->pesan('-'));

        $sesi = cache('tg_sesi_555');
        $this->assertNull($sesi['seri']);
        $this->assertNull($sesi['kva']);
        $this->assertSame('berkas', $sesi['langkah']);
    }

    /** Sesi gambar tetap terbuka supaya beberapa lembar bisa dikirim berturut-turut. */
    public function test_sesi_gambar_tidak_ditutup_setelah_satu_berkas(): void
    {
        $this->user('admin', '555');
        $this->jalankan($this->pesan('/gambar Trafo PLN'));
        (new BotPerintah($this->setting()))->tanganiTombol(['data' => 'kat:pln', 'from' => ['id' => '555']]);
        $this->jalankan($this->pesan('-'));

        $balasan = $this->jalankan([
            'photo' => [['file_id' => 'besar']],
            'from'  => ['id' => '555'],
            'chat'  => ['id' => '555'],
        ]);

        $this->assertSame('unduh', $balasan[0]['jenis']);
        $this->assertFalse($balasan[0]['tutup'], 'gambar kerja boleh berlanjut ke berkas berikutnya');
    }

    /** Jadwal sebaliknya: satu berkas per minggu, sesinya ditutup. */
    public function test_sesi_jadwal_ditutup_setelah_satu_berkas(): void
    {
        $this->user('admin', '555');
        $this->jalankan($this->pesan('/jadwal upload'));

        $balasan = $this->jalankan([
            'photo' => [['file_id' => 'besar']],
            'from'  => ['id' => '555'],
            'chat'  => ['id' => '555'],
        ]);

        $this->assertTrue($balasan[0]['tutup']);
    }

    public function test_tombol_kategori_setelah_sesi_habis_dijelaskan(): void
    {
        $this->user('admin', '555');

        $balasan = (new BotPerintah($this->setting()))->tanganiTombol([
            'data' => 'kat:pln',
            'from' => ['id' => '555'],
        ]);

        $this->assertStringContainsString('sudah berakhir', $this->teksPertama($balasan));
    }

    /** Gambar kerja tersimpan lengkap dengan kategori, seri, kva, tahun. */
    public function test_gambar_tersimpan_dengan_kategori_dan_seri(): void
    {
        Storage::fake('public');
        $admin = $this->user('admin', '555');

        $pesan = \App\Services\Telegram\Penyimpanan::gambarKerja('isi-berkas', 'jpg', [
            'judul'    => 'Trafo Distribusi',
            'kategori' => 'swasta',
            'seri'     => '26T0282061',
            'kva'      => '4000',
            'tahun'    => 2026,
        ], $admin);

        $baris = \App\Models\GambarKerja::first();

        $this->assertSame('swasta', $baris->kategori_seri);
        $this->assertSame('26T0282061', $baris->seri);
        $this->assertSame('4000', $baris->kva);
        $this->assertSame(2026, (int) $baris->tahun);
        $this->assertSame(1, $baris->urutan);
        $this->assertStringContainsString('Swasta', $pesan);

        // Berkas kedua pada kelompok yang sama meneruskan urutannya.
        \App\Services\Telegram\Penyimpanan::gambarKerja('isi-2', 'jpg', [
            'judul' => 'Trafo Distribusi', 'kategori' => 'swasta',
            'seri'  => '26T0282061', 'kva' => '4000', 'tahun' => 2026,
        ], $admin);

        $this->assertSame(2, \App\Models\GambarKerja::orderByDesc('id')->first()->urutan);
    }

    /* ═══════════════ Pemberitahuan proaktif ═══════════════ */

    private function catatan(array $isi): \App\Models\Note
    {
        return \App\Models\Note::create(array_merge([
            'title'    => 'Catatan uji',
            'is_done'  => false,
            'due_date' => now()->toDateString(),
        ], $isi));
    }

    private function target(Product $produk, int $qty): \App\Models\ProductionTarget
    {
        // created_by wajib diisi di skema — targetnya selalu punya pembuat.
        $pembuat = User::first() ?? $this->user('developer');

        return \App\Models\ProductionTarget::create([
            'product_id'   => $produk->id,
            'target_date'  => now()->toDateString(),
            'target_qty'   => $qty,
            'baseline_qty' => 0,
            'created_by'   => $pembuat->id,
        ]);
    }

    /** Tidak ada yang perlu dikabarkan -> null, supaya tidak mengirim pesan kosong. */
    public function test_ringkasan_pagi_diam_kalau_tidak_ada_apa_apa(): void
    {
        $this->assertNull(\App\Services\Telegram\Pemberitahuan::ringkasanPagi());
    }

    public function test_ringkasan_pagi_menyebut_target_dan_catatan(): void
    {
        $user   = $this->user('operator');
        $produk = $this->produk('2601');
        $this->target($produk, 50);
        $this->catatan(['title' => 'Cek trafo', 'user_id' => $user->id, 'target_user_id' => $user->id]);

        $teks = \App\Services\Telegram\Pemberitahuan::ringkasanPagi();

        $this->assertStringContainsString('Ringkasan Pagi', $teks);
        $this->assertStringContainsString('2601', $teks);
        $this->assertStringContainsString('Cek trafo', $teks);
    }

    public function test_target_meleset_hanya_menyebut_yang_jauh_tertinggal(): void
    {
        $admin  = $this->user('admin');
        $jauh   = $this->produk('AAA');
        $hampir = $this->produk('BBB');

        $this->target($jauh, 100);      // 0% — jauh tertinggal
        $this->target($hampir, 10);

        ProductionLog::create([
            'product_id' => $hampir->id, 'user_id' => $admin->id,
            'production_date' => now()->toDateString(),
            'up_qty' => 0, 'bt_qty' => 0, 'total_qty' => 9, 'reject_qty' => 0,
        ]);                              // 90% — masih wajar

        $teks = \App\Services\Telegram\Pemberitahuan::targetMeleset();

        $this->assertStringContainsString('AAA', $teks);
        $this->assertStringNotContainsString('BBB', $teks, 'yang sudah 90% tidak perlu diperingatkan');
    }

    public function test_target_meleset_diam_kalau_semua_sehat(): void
    {
        $this->assertNull(\App\Services\Telegram\Pemberitahuan::targetMeleset());
    }

    /** Pengingat dikirim per orang, dan hanya ke yang sudah menautkan Telegram. */
    public function test_pengingat_catatan_hanya_untuk_yang_tertaut(): void
    {
        $tertaut = $this->user('supervisor', '555');
        $tanpa   = $this->user('admin');

        $this->catatan(['title' => 'Untuk tertaut', 'user_id' => $tanpa->id, 'target_user_id' => $tertaut->id]);
        $this->catatan(['title' => 'Untuk tanpa',   'user_id' => $tertaut->id, 'target_user_id' => $tanpa->id]);

        $pesan = \App\Services\Telegram\Pemberitahuan::pengingatCatatan();

        $this->assertCount(1, $pesan);
        $this->assertSame('555', $pesan[0]['chat_id']);
        $this->assertStringContainsString('Untuk tertaut', $pesan[0]['teks']);
        $this->assertStringNotContainsString('Untuk tanpa', $pesan[0]['teks']);
    }

    public function test_catatan_selesai_tidak_diingatkan(): void
    {
        // Supervisor, bukan operator: operator memang tidak pernah diingatkan,
        // sehingga test ini akan lulus tanpa menguji apa-apa.
        $user = $this->user('supervisor', '555');
        $this->catatan(['title' => 'Sudah kelar', 'user_id' => $user->id, 'target_user_id' => $user->id, 'is_done' => true]);

        $this->assertSame([], \App\Services\Telegram\Pemberitahuan::pengingatCatatan());
    }

    public function test_laporan_mingguan_membandingkan_dengan_minggu_lalu(): void
    {
        $admin  = $this->user('admin');
        $produk = $this->produk('2601');

        ProductionLog::create([
            'product_id' => $produk->id, 'user_id' => $admin->id,
            'production_date' => now()->subDays(2)->toDateString(),
            'up_qty' => 0, 'bt_qty' => 0, 'total_qty' => 100, 'reject_qty' => 5,
        ]);
        ProductionLog::create([
            'product_id' => $produk->id, 'user_id' => $admin->id,
            'production_date' => now()->subDays(10)->toDateString(),
            'up_qty' => 0, 'bt_qty' => 0, 'total_qty' => 50, 'reject_qty' => 0,
        ]);

        $teks = \App\Services\Telegram\Pemberitahuan::laporanMingguan();

        $this->assertStringContainsString('Laporan Mingguan', $teks);
        $this->assertStringContainsString('100 unit', $teks);
        $this->assertStringContainsString('naik 100%', $teks);
        $this->assertStringContainsString('2601', $teks, 'produk paling bermasalah disebut');
    }

    public function test_laporan_mingguan_diam_kalau_belum_ada_data(): void
    {
        $this->assertNull(\App\Services\Telegram\Pemberitahuan::laporanMingguan());
    }

    /* ═══════════════ Tombol "Buka di aplikasi" ═══════════════ */

    public function test_perintah_baca_membawa_tombol_ke_halamannya(): void
    {
        config(['app.url' => 'https://qc.contoh.com']);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://qc.contoh.com');

        $this->user('admin', '555');

        $balasan = $this->jalankan($this->pesan('/produksi'));

        $this->assertArrayHasKey('tombol', $balasan[0]);
        $this->assertSame('Buka di aplikasi', $balasan[0]['tombol'][0][0]['text']);
        // Skemanya mengikuti lingkungan; yang penting alamatnya memang menunjuk
        // ke aplikasi dan ke halaman yang benar.
        $this->assertStringContainsString('qc.contoh.com', $balasan[0]['tombol'][0][0]['url']);
        $this->assertStringEndsWith('/production', $balasan[0]['tombol'][0][0]['url']);
    }

    /**
     * Telegram menolak SELURUH pesan kalau ada tombol dengan URL tak sah.
     * Saat APP_URL masih localhost, pesannya harus tetap terkirim — tanpa tombol.
     */
    public function test_tombol_dilewati_kalau_alamatnya_belum_bisa_dibuka(): void
    {
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');

        $this->user('admin', '555');

        $balasan = $this->jalankan($this->pesan('/produksi'));

        $this->assertArrayNotHasKey('tombol', $balasan[0]);
        $this->assertNotEmpty($balasan[0]['teks'], 'pesannya tetap harus terkirim');
    }

    /* ═══════════════ Sesi unggah ═══════════════ */

    public function test_batal_menghapus_sesi(): void
    {
        $this->user('admin', '555');

        $this->jalankan($this->pesan('/gambar Trafo PLN'));
        $this->assertNotNull(cache('tg_sesi_555'));

        $this->jalankan($this->pesan('/batal'));
        $this->assertNull(cache('tg_sesi_555'));
    }

    public function test_berkas_tak_didukung_ditolak_saat_sesi_berjalan(): void
    {
        $this->user('admin', '555');
        $this->jalankan($this->pesan('/gambar Trafo PLN'));

        $balasan = $this->jalankan([
            'document' => ['file_id' => 'x', 'mime_type' => 'application/zip', 'file_size' => 100],
            'from'     => ['id' => '555'],
            'chat'     => ['id' => '555'],
        ]);

        $this->assertStringContainsString('foto', $this->teksPertama($balasan));
        $this->assertNotNull(cache('tg_sesi_555'), 'sesi tidak boleh hangus karena salah kirim berkas');
    }

    public function test_foto_saat_sesi_berjalan_menghasilkan_perintah_unduh(): void
    {
        $this->user('admin', '555');
        $this->jalankan($this->pesan('/gambar Trafo PLN'));

        $balasan = $this->jalankan([
            'photo' => [['file_id' => 'kecil'], ['file_id' => 'besar']],
            'from'  => ['id' => '555'],
            'chat'  => ['id' => '555'],
        ]);

        $this->assertSame('unduh', $balasan[0]['jenis']);
        $this->assertSame('besar', $balasan[0]['berkas']['file_id'], 'harus memilih ukuran terbesar');
        $this->assertSame('gambar', $balasan[0]['sesi']['jenis']);
    }
}
