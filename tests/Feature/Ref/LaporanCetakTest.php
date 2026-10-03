<?php

namespace Tests\Feature\Ref;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cetak Laporan Harian — padanan asata untuk LaporanCetakTest referensi.
 * Halamannya React (Reports/Daily.jsx), jadi penandanya diperiksa dari sumber
 * komponen & CSS hasil build, datanya dari props Inertia.
 */
class LaporanCetakTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = new User(['name' => 'Admin Cetak', 'username' => 'admincetak', 'email' => 'admincetak@uji.test', 'role' => 'admin', 'password' => Hash::make('rahasia123')]);
        $u->forceFill(['is_active' => true])->save();

        return $u;
    }

    private function sumber(): string
    {
        return file_get_contents(resource_path('js/Pages/Reports/Daily.jsx'));
    }

    /** CSS hasil build untuk halaman React (entri inertia.jsx). */
    private function cssTerbangun(): string
    {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $css = '';
        foreach ($manifest as $entri) {
            foreach ($entri['css'] ?? [] as $berkas) {
                $css .= file_get_contents(public_path('build/' . $berkas));
            }
        }
        $this->assertNotSame('', $css, 'CSS tidak ada di manifest build.');

        return $css;
    }

    private function blokCetak(string $css): string
    {
        $isi = '';
        $dari = 0;
        while (($posisi = strpos($css, '@media print', $dari)) !== false) {
            $buka = strpos($css, '{', $posisi);
            if ($buka === false) break;
            $dalam = 0;
            for ($i = $buka; $i < strlen($css); $i++) {
                if ($css[$i] === '{') {
                    $dalam++;
                } elseif ($css[$i] === '}' && --$dalam === 0) {
                    $isi .= substr($css, $buka + 1, $i - $buka - 1) . ' ';
                    $dari = $i;
                    break;
                }
            }
            if ($dalam !== 0) break;
        }

        return $isi;
    }

    public function test_bilah_filter_dan_tombol_tidak_ikut_tercetak(): void
    {
        $this->assertMatchesRegularExpression('~className="au-panel tanpa-cetak"~', $this->sumber());
    }

    public function test_ada_kepala_kertas_khusus_cetak(): void
    {
        $src = $this->sumber();
        $this->assertStringContainsString('hanya-cetak', $src, 'Kepala kertas tidak ada.');
        $this->assertStringContainsString('Laporan Produksi Harian', $src);
        $this->assertStringContainsString('Dicetak', $src);

        $props = $this->actingAs($this->admin())->get(route('reports.daily'))->assertOk()->viewData('page')['props'];
        $this->assertSame('Admin Cetak', $props['printedBy']);
        $this->assertStringContainsString((string) now()->year, $props['printedAt']);
    }

    public function test_kepala_kertas_tidak_kelihatan_di_layar(): void
    {
        $this->assertMatchesRegularExpression('~\.hanya-cetak\s*\{[^}]*display:\s*none~', $this->cssTerbangun(),
            'Kepala kertas seharusnya tersembunyi selama di layar.');
    }

    public function test_aturan_cetak_ikut_ter_build(): void
    {
        $blok = $this->blokCetak($this->cssTerbangun());
        $this->assertNotSame('', $blok, 'Tidak ada aturan @media print di CSS hasil build.');

        foreach (['#sidebar', '#app-header', '.tanpa-cetak'] as $penanda) {
            $this->assertStringContainsString($penanda, $blok, "Aturan cetak untuk {$penanda} belum ikut ter-build.");
        }
        $this->assertStringContainsString('table-header-group', $blok, 'Kepala tabel harus diulang tiap halaman kertas.');
        $this->assertStringContainsString('landscape', $this->cssTerbangun(), 'Tabel harian 9 kolom — kertasnya mendatar.');
    }

    public function test_bilah_atas_punya_penanda_yang_dipakai_aturan_cetak(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.jsx'));
        $this->assertStringContainsString('id="app-header"', $layout);
        $this->assertStringContainsString('id="sidebar"', $layout);
    }

    public function test_tabel_harian_masih_tampil_normal_di_layar(): void
    {
        $admin = $this->admin();
        $cat = Category::create(['name' => 'Cover PLN', 'is_active' => true, 'has_manual_serial' => false]);
        $p = Product::create(['category_id' => $cat->id, 'type' => 'regular', 'name' => 'COVER CETAK', 'series' => 'C-CETAK', 'kva' => '50', 'is_active' => true]);
        ProductionLog::create(['product_id' => $p->id, 'user_id' => $admin->id, 'production_date' => today()->toDateString(),
            'up_qty' => 0, 'bt_qty' => 0, 'total_qty' => 3, 'reject_qty' => 0]);

        $props = $this->actingAs($admin)->get(route('reports.daily'))->assertOk()->viewData('page')['props'];
        $this->assertStringContainsString('C-CETAK', $props['logs'][0]['seriesKva']);
        $this->assertEquals(3, $props['totals']['total']);

        $src = $this->sumber();
        $this->assertStringContainsString('TOTAL', $src);
        $this->assertStringContainsString('window.print()', $src, 'Tombol Print hilang.');
    }
}
