<?php

namespace Tests\Feature\Ref;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sidebar bisa dikecilkan jadi ikon saja (PC) — port dari referensi.
 * asata merender sidebar di React (resources/js/Layouts/AppLayout.jsx), jadi
 * penanda tombol & label diperiksa dari sumber komponennya, sedangkan skrip
 * <head> dan aturan CSS diperiksa dari halaman/CSS sungguhan. Perilaku klik &
 * tooltip diuji di browser.
 */
class SidebarKecilTest extends TestCase
{
    use RefreshDatabase;

    private function html(string $peran = 'developer'): string
    {
        return $this->actingAs(User::factory()->create(['role' => $peran]))
            ->get(route('dashboard'))->assertOk()->getContent();
    }

    private function layout(): string
    {
        return file_get_contents(resource_path('js/Layouts/AppLayout.jsx'));
    }

    public function test_tombol_kecilkan_dan_tooltip_ada(): void
    {
        $src = $this->layout();

        // Tombolnya = ikon logo (papan klip) di kepala sidebar, satu-satunya.
        $this->assertSame(1, substr_count($src, 'id="sb-mini-toggle"'), 'hanya satu tombol pengecil');
        $logo = substr($src, strpos($src, 'id="sb-mini-toggle"'), 800);
        $this->assertStringContainsString('ICON.clipboard', $logo, 'ikonnya tetap papan klip');
        $this->assertStringContainsString('au-sb-tip', $src, 'tooltip nama menu ada');
        $this->assertStringContainsString('id="app-main"', $src, 'jarak konten ikut beranimasi lewat #app-main');

        $header = substr($src, strpos($src, 'id="app-header"'), 1200);
        $this->assertStringNotContainsString('sb-mini-toggle', $header, 'tidak ada tombol pengecil di header');
    }

    public function test_setiap_menu_punya_tooltip_dan_label(): void
    {
        $src = $this->layout();

        // Setiap tautan menu (termasuk grup QC-Welding) membawa data-sb-tip dan
        // labelnya dibungkus sb-label supaya bisa disembunyikan saat kecil.
        $this->assertGreaterThanOrEqual(4, substr_count($src, 'data-sb-tip='));
        $this->assertStringContainsString('<span className="sb-label">{item.label}</span>', $src);
        $this->assertStringContainsString('<span className="sb-label">QC-Welding</span>', $src);
    }

    /** Pilihan tersimpan harus berlaku sebelum CSS dimuat — kalau tidak, sidebar berkedip. */
    public function test_pilihan_dipasang_di_head_sebelum_halaman_tergambar(): void
    {
        $html   = $this->html();
        $kepala = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringContainsString("localStorage.getItem('qc:sidebar-mini')", $kepala);
        $this->assertStringContainsString("classList.add('sb-mini')", $kepala);
        $this->assertLessThan(strpos($kepala, '<link'), strpos($kepala, 'qc:sidebar-mini'),
            'skrip harus jalan sebelum stylesheet');
    }

    public function test_aturan_kecil_hanya_berlaku_di_layar_pc(): void
    {
        $css = file_get_contents(resource_path('css/asata-ui.css'));

        $this->assertStringContainsString('.au-sb-tip { display: none; }', $css, 'tooltip mati di HP');
        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 1024px\)\s*\{[^@]*\.sb-mini \.au-sidebar \{ width: 68px; \}/s', $css,
            'lebar kecil hanya di dalam media query PC; di HP sidebar tetap laci penuh');
    }
}
