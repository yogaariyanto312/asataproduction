<?php

namespace Tests\Feature;

use App\Support\AplikasiAndroid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Distribusi APK: endpoint versi (auto update), link unduh (tujuan QR), dan QR di halaman Tentang. */
class AplikasiAndroidTest extends TestCase
{
    use RefreshDatabase;

    private string $simpan;

    protected function setUp(): void
    {
        parent::setUp();
        // storage sementara: jangan sentuh APK sungguhan di storage/app/android.
        $this->simpan = sys_get_temp_dir().'/asata-uji-'.uniqid();
        File::makeDirectory($this->simpan.'/app/android', 0777, true);
        $this->app->useStoragePath($this->simpan);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->simpan);
        parent::tearDown();
    }

    private function rilis(array $isi = []): void
    {
        file_put_contents(AplikasiAndroid::folder().'/AsataProduction-1.2.0-3.apk', 'APK-PALSU');
        file_put_contents(AplikasiAndroid::folder().'/versi.json', json_encode($isi + [
            'versionCode' => 3, 'versionName' => '1.2.0', 'berkas' => 'AsataProduction-1.2.0-3.apk',
            'ukuran' => 9, 'sha256' => hash('sha256', 'APK-PALSU'), 'catatan' => 'Perbaikan', 'wajib' => false, 'tanggal' => '05-10-2026 10:00',
        ]));
    }

    public function test_belum_ada_rilis(): void
    {
        $this->getJson('/api/v1/aplikasi/versi')->assertOk()->assertJsonPath('data', null);
        $this->get('/unduh/aplikasi')->assertNotFound();
    }

    public function test_versi_tanpa_login_dan_tidak_dicache(): void
    {
        $this->rilis();
        $r = $this->getJson('/api/v1/aplikasi/versi')->assertOk()
            ->assertJsonPath('data.versionCode', 3)
            ->assertJsonPath('data.versionName', '1.2.0')
            ->assertJsonPath('data.url', route('unduh.aplikasi'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
    }

    public function test_unduh_tanpa_login(): void
    {
        $this->rilis();
        $r = $this->get('/unduh/aplikasi')->assertOk();
        $this->assertSame('application/vnd.android.package-archive', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('AsataProduction-1.2.0.apk', $r->headers->get('Content-Disposition'));
    }

    public function test_berkas_di_luar_folder_ditolak(): void
    {
        file_put_contents($this->simpan.'/app/rahasia.apk', 'x');
        $this->rilis(['berkas' => '../rahasia.apk']);
        // basename() → mencari app/android/rahasia.apk yang tidak ada → dianggap belum ada rilis.
        $this->get('/unduh/aplikasi')->assertNotFound();
        $this->getJson('/api/v1/aplikasi/versi')->assertJsonPath('data', null);
    }

    /** Aplikasi Windows: rilis MSI terpisah di storage/app/windows, dipilih lewat ?platform=windows. */
    private function rilisWindows(): void
    {
        File::ensureDirectoryExists(AplikasiAndroid::folder('windows'));
        file_put_contents(AplikasiAndroid::folder('windows').'/AsataProduction-1.0.1-2.msi', 'MSI-PALSU');
        file_put_contents(AplikasiAndroid::folder('windows').'/versi.json', json_encode([
            'versionCode' => 2, 'versionName' => '1.0.1', 'berkas' => 'AsataProduction-1.0.1-2.msi',
            'ukuran' => 9, 'sha256' => hash('sha256', 'MSI-PALSU'), 'catatan' => '', 'wajib' => false, 'tanggal' => '12-10-2026 10:00',
        ]));
    }

    public function test_windows_tidak_menerima_apk_android(): void
    {
        // Hanya APK yang ada: aplikasi Windows tidak boleh ditawari APK.
        $this->rilis();
        $this->getJson('/api/v1/aplikasi/versi?platform=windows')->assertOk()->assertJsonPath('data', null);
        $this->get('/unduh/aplikasi-windows')->assertNotFound();
        // Tanpa parameter tetap Android (aplikasi Android lama tidak berubah).
        $this->getJson('/api/v1/aplikasi/versi')->assertJsonPath('data.platform', 'android')->assertJsonPath('data.versionCode', 3);
    }

    public function test_versi_dan_unduh_windows(): void
    {
        $this->rilis();
        $this->rilisWindows();
        $this->getJson('/api/v1/aplikasi/versi?platform=windows')->assertOk()
            ->assertJsonPath('data.platform', 'windows')
            ->assertJsonPath('data.versionCode', 2)
            ->assertJsonPath('data.url', route('unduh.aplikasi.windows'));
        $r = $this->get('/unduh/aplikasi-windows')->assertOk();
        $this->assertSame('application/x-msi', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('AsataProduction-1.0.1.msi', $r->headers->get('Content-Disposition'));
        // Platform asing diperlakukan sebagai Android.
        $this->getJson('/api/v1/aplikasi/versi?platform=../windows')->assertJsonPath('data.platform', 'android');
    }

    public function test_qr_berisi_matriks_persegi(): void
    {
        $qr = AplikasiAndroid::qr('https://asataproduction.yogaariyanto.com/unduh/aplikasi');
        $this->assertNotEmpty($qr);
        foreach ($qr as $baris) {
            $this->assertSame(count($qr), strlen($baris));
            $this->assertMatchesRegularExpression('/^[01]+$/', $baris);
        }
    }
}
