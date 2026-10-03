<?php

namespace Tests\Feature\Ref;

use App\Models\ActivityLog;
use App\Models\BotSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Menu Settings (Control Panel).
 *
 * Yang paling dijaga di sini adalah bug lama yang merusak diam-diam: menyimpan
 * satu bagian saja ikut mematikan bagian lain, karena semua field ditulis ulang
 * tiap kali menyimpan dan field yang tidak terkirim dianggap "nonaktif".
 */
class HalamanSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function user(string $role = 'developer'): User
    {
        return User::create([
            'name'      => ucfirst($role),
            'username'  => $role . 'setting',
            'email'     => $role . '.setting@uji.test',
            'role'      => $role,
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    /** Keadaan awal dengan semua fitur menyala, untuk menguji apa yang terhapus. */
    private function semuaMenyala(): BotSetting
    {
        $setting = BotSetting::instance();
        $setting->update([
            'telegram_token'      => '1234567890:AAFabcdefghijklmnopqrstuvwxyz012345',
            'telegram_chat_id'    => '-1001234567890',
            'telegram_enabled'    => true,
            'reject_threshold'    => 12.5,
            'report_enabled'      => true,
            'disable_devtools'    => true,
            'maintenance_mode'    => true,
            'maintenance_message' => 'Sedang perbaikan',
        ]);

        return $setting;
    }

    /* ═════════════ Bug: simpan satu bagian menghapus bagian lain ═════════════ */

    /**
     * Ini bug yang benar-benar terjadi: halaman lama punya enam form terpisah,
     * dan form Telegram tidak menyalin field bagian lain sebagai input hidden.
     * Sekali menyimpan Telegram, Laporan Harian / Maintenance / DevTools mati
     * dan batas reject balik ke 5 — tanpa pemberitahuan apa pun.
     */
    public function test_menyimpan_telegram_tidak_mematikan_pengaturan_lain(): void
    {
        $this->semuaMenyala();

        $this->actingAs($this->user())->post(route('developer.bot-settings.update'), [
            'telegram_token'   => '1234567890:AAFabcdefghijklmnopqrstuvwxyz012345',
            'telegram_chat_id' => '-1001234567890',
            'telegram_enabled' => '1',
        ]);

        BotSetting::lupakan();
        $s = BotSetting::instance();

        $this->assertSame('12.50', (string) $s->reject_threshold, 'batas reject ikut tereset');
        $this->assertTrue($s->report_enabled,   'laporan harian ikut mati');
        $this->assertTrue($s->disable_devtools, 'proteksi devtools ikut mati');
        $this->assertTrue($s->maintenance_mode, 'maintenance ikut mati');
        $this->assertSame('Sedang perbaikan', $s->maintenance_message, 'pesan maintenance ikut terhapus');
    }

    /** Kontrol positif: yang memang dikirim tetap berubah. */
    public function test_field_yang_dikirim_benar_benar_tersimpan(): void
    {
        $this->semuaMenyala();

        $this->actingAs($this->user())->post(route('developer.bot-settings.update'), [
            'reject_threshold' => '7.5',
        ]);

        BotSetting::lupakan();
        $this->assertSame('7.50', (string) BotSetting::instance()->reject_threshold);
    }

    /**
     * Saklar yang dimatikan HARUS tetap tersimpan. Checkbox tak tercentang tidak
     * ikut terkirim, jadi halamannya memasang <input hidden value="0"> di depan
     * tiap checkbox. Tanpa itu, saklar tidak akan pernah bisa dimatikan.
     */
    public function test_saklar_bisa_dimatikan(): void
    {
        $this->semuaMenyala();

        $this->actingAs($this->user())->post(route('developer.bot-settings.update'), [
            'maintenance_mode' => '0',
        ]);

        BotSetting::lupakan();
        $this->assertFalse(BotSetting::instance()->maintenance_mode);
    }

    public function test_halaman_memasang_input_hidden_pendamping_tiap_saklar(): void
    {
        // asata: halaman Inertia — pasangan hidden "0" + checkbox ada di komponen Saklar,
        // dan setiap saklar memakainya.
        $this->actingAs($this->user())->get(route('developer.bot-settings'))->assertOk();
        $jsx = file_get_contents(resource_path('js/Pages/Settings/Bot.jsx'));

        $this->assertStringContainsString('<input type="hidden" name={nama} value="0" />', $jsx);
        $this->assertLessThan(
            strpos($jsx, '<input type="checkbox" name={nama} value="1"'),
            strpos($jsx, '<input type="hidden" name={nama} value="0" />'),
            'hidden harus sebelum checkbox (PHP memakai nilai terakhir)'
        );
        foreach (['telegram_enabled', 'discord_enabled', 'report_enabled', 'disable_devtools', 'maintenance_mode'] as $nama) {
            $this->assertStringContainsString('<Saklar nama="' . $nama . '"', $jsx,
                "saklar {$nama} tanpa input hidden pendamping — tidak akan bisa dimatikan");
        }
    }

    /* ═════════════ Satu form, bukan enam ═════════════ */

    /**
     * Enam form yang saling menyalin field itulah sumber bugnya. Kalau nanti
     * dipecah lagi, test ini yang memberi tahu.
     */
    public function test_pengaturan_memakai_satu_form_tanpa_salinan_tersembunyi(): void
    {
        $jsx = file_get_contents(resource_path('js/Pages/Settings/Bot.jsx'));

        $this->assertSame(1, substr_count($jsx, 'action={action}'),
            'hanya boleh ada SATU form yang menyimpan pengaturan');
        $this->assertStringNotContainsString('type="hidden" name="telegram_token"', $jsx,
            'Bot Token tidak boleh disalin sebagai input tersembunyi');

        // Token dikirim sekali lewat props, tidak berulang.
        $this->semuaMenyala();
        BotSetting::lupakan();
        $props = $this->actingAs($this->user())->get(route('developer.bot-settings'))->viewData('page')['props'];
        $this->assertSame('1234567890:AAFabcdefghijklmnopqrstuvwxyz012345', $props['setting']['telegram_token']);
    }

    /* ═════════════ Validasi ═════════════ */

    public function test_token_dengan_format_salah_ditolak(): void
    {
        $this->actingAs($this->user())
            ->post(route('developer.bot-settings.update'), ['telegram_token' => 'token-asal'])
            ->assertSessionHasErrors('telegram_token');
    }

    public function test_chat_id_bukan_angka_ditolak(): void
    {
        $this->actingAs($this->user())
            ->post(route('developer.bot-settings.update'), ['telegram_chat_id' => 'grup saya'])
            ->assertSessionHasErrors('telegram_chat_id');
    }

    public function test_chat_id_berupa_username_channel_diterima(): void
    {
        $this->actingAs($this->user())
            ->post(route('developer.bot-settings.update'), ['telegram_chat_id' => '@qcproduksi'])
            ->assertSessionHasNoErrors();
    }

    public function test_batas_reject_di_luar_rentang_ditolak(): void
    {
        $this->actingAs($this->user())
            ->post(route('developer.bot-settings.update'), ['reject_threshold' => '150'])
            ->assertSessionHasErrors('reject_threshold');
    }

    public function test_webhook_discord_bukan_url_ditolak(): void
    {
        $this->actingAs($this->user())
            ->post(route('developer.bot-settings.update'), ['discord_webhook' => 'bukan-url'])
            ->assertSessionHasErrors('discord_webhook');
    }

    /** Tipe uji bebas dulu diteruskan ke service; sekarang ditolak lebih awal. */
    public function test_tipe_uji_bot_divalidasi(): void
    {
        $this->actingAs($this->user())
            ->post(route('developer.bot-settings.test'), ['type' => 'whatsapp'])
            ->assertSessionHasErrors('type');
    }

    /* ═════════════ Jejak audit ═════════════ */

    /** Rahasia tidak boleh ikut tercatat — catatan aktivitas dikirim ke bot. */
    public function test_catatan_aktivitas_tidak_memuat_token_atau_webhook(): void
    {
        $token = '1234567890:AAFabcdefghijklmnopqrstuvwxyz012345';

        $this->actingAs($this->user())->post(route('developer.bot-settings.update'), [
            'telegram_token'  => $token,
            'discord_webhook' => 'https://discord.com/api/webhooks/123/rahasia-sekali',
        ]);

        $catatan = ActivityLog::latest('id')->value('description');

        $this->assertStringNotContainsString($token, $catatan);
        $this->assertStringNotContainsString('rahasia-sekali', $catatan);
        $this->assertStringContainsString('disembunyikan', $catatan);
    }

    public function test_catatan_aktivitas_menyebut_apa_yang_diubah(): void
    {
        $this->actingAs($this->user())->post(route('developer.bot-settings.update'), [
            'maintenance_mode' => '1',
        ]);

        $catatan = ActivityLog::latest('id')->value('description');

        $this->assertStringContainsString('maintenance_mode=aktif', $catatan);
    }

    /* ═════════════ Performa ═════════════ */

    /**
     * layouts/app.blade.php memanggil BotSetting::instance() dua kali (spanduk
     * maintenance & penjaga DevTools), jadi SETIAP halaman aplikasi membayar dua
     * query ke tabel satu baris ini. Sekarang diingat sepanjang request.
     */
    public function test_satu_query_bot_settings_per_halaman(): void
    {
        BotSetting::instance();          // pastikan barisnya sudah ada
        BotSetting::lupakan();

        $sql = [];
        DB::listen(function ($e) use (&$sql) { $sql[] = $e->sql; });

        $this->actingAs($this->user())->get(route('dashboard'))->assertOk();

        $jumlah = count(array_filter($sql, fn ($s) => str_contains($s, 'bot_settings')));

        $this->assertLessThanOrEqual(1, $jumlah,
            "Halaman biasa menembak {$jumlah} query ke bot_settings — dulu 2, seharusnya cukup 1.");
    }

    public function test_halaman_setting_juga_hanya_satu_query_bot_settings(): void
    {
        BotSetting::instance();
        BotSetting::lupakan();

        $sql = [];
        DB::listen(function ($e) use (&$sql) { $sql[] = $e->sql; });

        $this->actingAs($this->user())->get(route('developer.bot-settings'))->assertOk();

        $jumlah = count(array_filter($sql, fn ($s) => str_contains($s, 'bot_settings')));

        $this->assertLessThanOrEqual(1, $jumlah, "Halaman Settings menembak {$jumlah} query — dulu 4.");
    }

    /** Sesudah disimpan, yang diingat harus baris terbaru — bukan nilai basi. */
    public function test_ingatan_disegarkan_setelah_menyimpan(): void
    {
        $this->semuaMenyala();

        $this->actingAs($this->user())->post(route('developer.bot-settings.update'), [
            'maintenance_message' => 'Pesan baru',
        ]);

        // Tanpa lupakan(): meniru request yang sama membaca ulang setelah simpan.
        $this->assertSame('Pesan baru', BotSetting::instance()->maintenance_message);
    }

    /* ═════════════ Hak akses ═════════════ */

    public function test_hanya_developer_yang_bisa_membuka_settings(): void
    {
        $this->flushSession();
        $this->actingAs($this->user('admin'))->get(route('developer.bot-settings'))->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->user('developer'))->get(route('developer.bot-settings'))->assertOk();
    }

    public function test_role_lain_tidak_bisa_menyimpan_pengaturan(): void
    {
        $this->actingAs($this->user('operator'))
            ->post(route('developer.bot-settings.update'), ['maintenance_mode' => '1'])
            ->assertForbidden();

        BotSetting::lupakan();

        // Kolomnya boleh null (belum pernah diisi) — yang penting BUKAN aktif.
        $this->assertNotTrue(BotSetting::instance()->maintenance_mode);
    }

    /* ═════════════ Klien HTTP terpusat ═════════════ */

    /**
     * Verifikasi TLS dulu dimatikan di delapan tempat terpisah. Sekarang satu
     * pintu, bisa dinyalakan lewat .env tanpa menyentuh kode.
     */
    public function test_verifikasi_tls_bisa_dinyalakan_lewat_config(): void
    {
        $this->assertFalse(config('services.bot.verify_tls'),
            'bawaannya tetap seperti perilaku lama supaya notifikasi tidak mendadak mati');

        $this->assertSame(1, substr_count(
            file_get_contents(app_path('Services/BotNotificationService.php')),
            '->withoutVerifying()'
        ), 'withoutVerifying hanya boleh ada di satu tempat (helper klien)');
    }
}
