<?php

namespace Tests\Feature\Ref;

use App\Models\BotSetting;
use App\Models\User;
use App\Support\MenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Menu Tutorial: iframe menampilkan situs panduan bawaan (public/panduan),
 * video hanya tab tambahan yang muncul kalau URL-nya diisi.
 */
class HalamanTutorialTest extends TestCase
{
    use RefreshDatabase;

    private function jsx(): string
    {
        return file_get_contents(resource_path('js/Pages/Tutorial.jsx'));
    }

    private function props(User $user): array
    {
        return $this->actingAs($user)->get(route('tutorial'))->assertOk()->viewData('page')['props'];
    }

    public function test_iframe_menampilkan_panduan_bawaan(): void
    {
        $props = $this->props(User::factory()->create(['role' => 'operator']));

        $this->assertSame(asset('panduan/index.html'), $props['panduanUrl']);
        $this->assertStringContainsString('id="panduan-frame" src={panduanUrl}', $this->jsx());
        $this->assertStringContainsString('Buka di tab baru', $this->jsx());
    }

    public function test_tab_video_hanya_muncul_kalau_url_diisi(): void
    {
        $user = User::factory()->create(['role' => 'operator']);

        $this->assertNull($this->props($user)['iframeUrl']);

        BotSetting::instance()->update(['tutorial_iframe_url' => 'https://www.youtube.com/embed/abc123']);
        BotSetting::lupakan();

        $this->assertSame('https://www.youtube.com/embed/abc123', $this->props($user)['iframeUrl']);
        // Tombol tab Video hanya dirender bila URL ada; Panduan tetap tab bawaan.
        $this->assertStringContainsString("{iframeUrl ? (", $this->jsx());
        $this->assertStringContainsString("useState('panduan')", $this->jsx());
    }

    public function test_tombol_ubah_video_mengikuti_hak_akses(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $op  = User::factory()->create(['role' => 'operator']);

        $this->assertTrue($this->props($dev)['canEdit']);
        $this->flushSession();
        $this->assertFalse($this->props($op)['canEdit']);

        $this->actingAs($op)->post(route('tutorial.embed.update'), ['iframe_url' => 'https://x.test'])->assertForbidden();
    }

    /** Dulu route-nya dikunci isDeveloper(), jadi saklar di Hak Akses tidak ada artinya. */
    public function test_izin_ganti_video_bisa_diberikan_lewat_hak_akses(): void
    {
        DB::table('role_menu_permissions')->insert([
            'role' => 'admin', 'menu_key' => 'tutorial.edit', 'allowed' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        MenuAccess::flush();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertTrue($this->props($admin)['canEdit']);
        $this->actingAs($admin)
            ->post(route('tutorial.embed.update'), ['iframe_url' => 'https://www.youtube.com/embed/xyz'])
            ->assertRedirect();

        BotSetting::lupakan();
        $this->assertSame('https://www.youtube.com/embed/xyz', BotSetting::instance()->tutorial_iframe_url);
    }

    public function test_berkas_panduan_ada_dan_daftar_isinya_utuh(): void
    {
        $dir = public_path('panduan');
        foreach (['index.html', 'panduan.css', 'panduan.js'] as $f) {
            $this->assertFileExists($dir . '/' . $f);
        }

        $html = file_get_contents($dir . '/index.html');
        preg_match_all('/<section class="bab" id="([^"]+)"/', $html, $bab);
        preg_match_all('/<nav class="daftar".*?<\/nav>/s', $html, $nav);
        preg_match_all('/href="#([^"]+)"/', $nav[0][0] ?? '', $tautan);

        $this->assertGreaterThanOrEqual(15, count($bab[1]), 'Panduan harus mencakup semua menu utama.');
        $this->assertSame($bab[1], $tautan[1], 'Daftar isi harus menunjuk ke setiap bab, berurutan.');
    }

    public function test_setiap_menu_utama_dibahas_di_panduan(): void
    {
        $html = strtolower(strip_tags(file_get_contents(public_path('panduan/index.html'))));

        foreach (MenuAccess::items() as $menu) {
            // Menu khusus developer (Settings) tidak perlu diajarkan ke pengguna umum.
            if (in_array($menu['key'], ['settings', 'tutorial', 'about'], true)) {
                continue;
            }
            $this->assertStringContainsString(strtolower($menu['label']), $html,
                "Menu \"{$menu['label']}\" belum dibahas di panduan.");
        }
    }
}
