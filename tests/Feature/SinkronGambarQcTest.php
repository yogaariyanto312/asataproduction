<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SinkronGambarQc;
use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sinkron Gambar Kerja + foto jadwal dari qc (PRD 15): satu arah, hanya menambah, qc tidak pernah ditulis.
 * qc diganti database sqlite memori kedua; berkas qc di folder sementara.
 */
class SinkronGambarQcTest extends TestCase
{
    use RefreshDatabase;

    private Connection $qc;
    private string $akar;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');

        $this->akar = sys_get_temp_dir() . '/qc-uji-' . uniqid();
        mkdir($this->akar . '/gambar-kerja', 0777, true);
        mkdir($this->akar . '/schedule-photos/x', 0777, true);

        $this->qc = new SQLiteConnection(new \PDO('sqlite::memory:'));
        $this->qc->statement('create table gambar_kerja (id integer primary key, product_id integer null, judul text, seri text, kva text, urutan integer, file_path text, file_type text, thumbnail_path text null, uploaded_by integer null, created_at text null, updated_at text null, kolom_khusus_qc text null)');
        $this->qc->statement('create table schedule_photos (id integer primary key, target_date text, file_path text, uploaded_by integer null, created_at text null, updated_at text null)');

        User::unguarded(fn () => User::create(['name' => 'Dev', 'username' => 'dev', 'email' => 'd@uji.test', 'role' => 'developer', 'password' => 'x12345678', 'is_active' => true]));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->akar . '/*/*/*') ?: [] as $f) { @unlink($f); }
        foreach (glob($this->akar . '/*/*') ?: [] as $f) { is_file($f) ? @unlink($f) : @rmdir($f); }
        foreach (glob($this->akar . '/*') ?: [] as $f) { @rmdir($f); }
        @rmdir($this->akar);
        parent::tearDown();
    }

    private function gambar(string $seri, int $urutan, string $berkas = 'a.pdf', bool $adaBerkas = true): void
    {
        $rel = "gambar-kerja/$berkas";
        if ($adaBerkas) {
            file_put_contents($this->akar . '/' . $rel, 'ISI-' . $berkas);
        }
        $this->qc->table('gambar_kerja')->insert(['product_id' => 999, 'judul' => "Gbr $seri", 'seri' => $seri, 'kva' => '100', 'urutan' => $urutan, 'file_path' => $rel, 'file_type' => 'pdf', 'uploaded_by' => 888, 'kolom_khusus_qc' => 'x']);
    }

    private function jalan(bool $cek = false): array
    {
        return (new SinkronGambarQc())->jalankan($this->qc, $this->akar, $cek);
    }

    public function test_gambar_baru_ditambah_dan_berkas_disalin(): void
    {
        $this->gambar('S1', 1);

        $h = $this->jalan();

        $this->assertSame(1, $h['baru']);
        $baris = DB::table('gambar_kerja')->first();
        $this->assertSame('S1', $baris->seri);
        $this->assertNull($baris->product_id, 'produk qc yang tidak ada di asata dikosongkan');
        $this->assertSame((int) DB::table('users')->value('id'), (int) $baris->uploaded_by, 'pengunggah tak dikenal diganti developer');
        $this->assertSame('ISI-a.pdf', Storage::disk('public')->get('gambar-kerja/a.pdf'));
    }

    public function test_dijalankan_dua_kali_tidak_menggandakan(): void
    {
        $this->gambar('S1', 1);
        $this->jalan();

        $h = $this->jalan();

        $this->assertSame(0, $h['baru']);
        $this->assertSame(1, $h['sudah_ada']);
        $this->assertSame(1, DB::table('gambar_kerja')->count());
    }

    public function test_kunci_sama_berkas_beda_jadi_konflik_dan_tidak_ditimpa(): void
    {
        $this->gambar('S1', 1, 'a.pdf');
        $this->jalan();
        $this->qc->table('gambar_kerja')->update(['file_path' => 'gambar-kerja/b.pdf']);
        file_put_contents($this->akar . '/gambar-kerja/b.pdf', 'ISI-b');

        $h = $this->jalan();

        $this->assertCount(1, $h['konflik']);
        $this->assertSame(0, $h['baru']);
        $this->assertSame('gambar-kerja/a.pdf', DB::table('gambar_kerja')->value('file_path'), 'asata tidak diubah');
    }

    public function test_berkas_yang_sudah_ada_tidak_ditimpa(): void
    {
        Storage::disk('public')->put('gambar-kerja/a.pdf', 'MILIK-ASATA');
        $this->gambar('S1', 1);

        $this->jalan();

        $this->assertSame('MILIK-ASATA', Storage::disk('public')->get('gambar-kerja/a.pdf'));
    }

    public function test_berkas_hilang_di_qc_dicatat(): void
    {
        $this->gambar('S1', 1, 'hilang.pdf', false);

        $h = $this->jalan();

        $this->assertSame(['gambar-kerja/hilang.pdf'], $h['berkas_hilang']);
    }

    public function test_berkas_hilang_dicoba_lagi_tanpa_membuat_baris_rusak(): void
    {
        $this->gambar('PULIH', 1, 'pulih.pdf', false);
        $this->jalan();
        $this->assertSame(0, DB::table('gambar_kerja')->count());
        file_put_contents($this->akar . '/gambar-kerja/pulih.pdf', 'PULIH');
        $this->assertSame(1, $this->jalan()['baru']);
        $this->assertSame('PULIH', Storage::disk('public')->get('gambar-kerja/pulih.pdf'));
    }

    public function test_baris_lama_tanpa_berkas_dipulihkan_tanpa_duplikat(): void
    {
        $this->gambar('LAMA', 1);
        $this->jalan();
        Storage::disk('public')->delete('gambar-kerja/a.pdf');
        $this->assertSame(1, $this->jalan()['berkas_disalin']);
        $this->assertSame(1, DB::table('gambar_kerja')->count());
        $this->assertTrue(Storage::disk('public')->exists('gambar-kerja/a.pdf'));
    }

    public function test_salin_gagal_tidak_menyisipkan_baris(): void
    {
        $this->gambar('GAGAL', 1);
        $layanan = new class extends SinkronGambarQc {
            protected function salinBerkas(string $akar, string $rel, bool $cek): bool
            {
                throw new \RuntimeException('Gagal salin untuk uji');
            }
        };
        try {
            $layanan->jalankan($this->qc, $this->akar);
            $this->fail('Galat salin harus diteruskan');
        } catch (\RuntimeException $e) {
            $this->assertSame('Gagal salin untuk uji', $e->getMessage());
        }
        $this->assertSame(0, DB::table('gambar_kerja')->count());
    }

    public function test_putaran_berikutnya_mendeteksi_upload_baru(): void
    {
        $this->assertSame(0, $this->jalan()['baru']);
        $this->gambar('BARU', 1);
        $this->assertSame(1, $this->jalan()['baru']);
        $this->assertSame(0, $this->jalan()['baru']);
    }

    public function test_berkas_kosong_ditunda_dan_lokasi_di_luar_sumber_ditolak(): void
    {
        $this->gambar('KOSONG', 1);
        file_put_contents($this->akar . '/gambar-kerja/a.pdf', '');
        $this->assertSame(0, $this->jalan()['baru']);
        $this->assertSame(0, DB::table('gambar_kerja')->count());
        $this->qc->table('gambar_kerja')->update(['file_path' => '../di-luar.pdf']);
        $this->expectException(\Throwable::class);
        $this->jalan();
    }

    public function test_jumlah_kueri_asata_tidak_bertambah_per_gambar_yang_sudah_ada(): void
    {
        for ($i = 1; $i <= 20; $i++) { $this->gambar('S' . $i, $i); }
        $this->jalan();
        DB::enableQueryLog();
        $h = $this->jalan();
        $kueri = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame(20, $h['sudah_ada']);
        $this->assertLessThan(12, count($kueri), 'pencocokan tidak boleh satu SELECT per gambar');
    }

    public function test_otomatis_nonaktif_dilewati_dan_gagal_tidak_mematikan_otomatis(): void
    {
        SinkronGambarQc::aturOtomatis(false);
        $this->artisan('gambar:sinkron-qc --otomatis')->assertExitCode(0);
        $this->assertNull(SinkronGambarQc::hasilTerakhir());
        SinkronGambarQc::aturOtomatis(true);
        $this->artisan('gambar:sinkron-qc --otomatis')->assertExitCode(1);
        $this->assertNotEmpty(SinkronGambarQc::hasilTerakhir()['galat']);
        $this->assertTrue(SinkronGambarQc::otomatis());
    }

    public function test_pengunci_bersama_menolak_manual_dan_otomatis(): void
    {
        $kunci = fopen(Storage::disk('local')->path('sinkron-qc.lock'), 'c');
        flock($kunci, LOCK_EX);
        try {
            $this->artisan('gambar:sinkron-qc')->expectsOutput('Sinkron sedang berjalan. Tunggu hingga selesai.')->assertExitCode(1);
            $this->artisan('gambar:sinkron-qc --otomatis')->assertExitCode(1);
            $this->assertNull(SinkronGambarQc::hasilTerakhir());
        } finally {
            flock($kunci, LOCK_UN);
            fclose($kunci);
        }
    }

    public function test_scheduler_otomatis_tiap_menit(): void
    {
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $event = collect($events)->first(fn ($e) => str_contains($e->command ?? '', 'gambar:sinkron-qc --otomatis'));
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_api_status_dan_pengaturan_hanya_developer(): void
    {
        $dev = User::first();
        $this->actingAs($dev, 'sanctum')->getJson('/api/v1/settings/sinkron-qc')
            ->assertOk()->assertJsonPath('otomatis', true)->assertJsonPath('interval_menit', 1);
        $this->postJson('/api/v1/settings/sinkron-qc/aturan', ['otomatis' => false])->assertOk();
        $this->assertFalse(SinkronGambarQc::otomatis());
        $this->postJson('/api/v1/settings/sinkron-qc/aturan', ['otomatis' => 'salah'])->assertStatus(422);
        $dev->update(['role' => 'operator']);
        $this->getJson('/api/v1/settings/sinkron-qc')->assertForbidden();
        $this->postJson('/api/v1/settings/sinkron-qc/aturan', ['otomatis' => true])->assertForbidden();
        $this->postJson('/api/v1/settings/sinkron-qc')->assertForbidden();
    }

    public function test_mode_cek_tidak_menulis_apa_pun(): void
    {
        $this->gambar('S1', 1);
        $this->qc->table('schedule_photos')->insert(['target_date' => now()->startOfWeek()->toDateString(), 'file_path' => 'schedule-photos/x/j.jpg']);
        file_put_contents($this->akar . '/schedule-photos/x/j.jpg', 'JPG');

        $h = $this->jalan(true);

        $this->assertSame(1, $h['baru']);
        $this->assertSame(1, $h['jadwal_baru']);
        $this->assertSame(0, DB::table('gambar_kerja')->count());
        $this->assertSame(0, DB::table('schedule_photos')->count());
        $this->assertFalse(Storage::disk('public')->exists('gambar-kerja/a.pdf'));
    }

    public function test_foto_jadwal_minggu_ini_ditambah_minggu_lama_dilewati_dan_tidak_ditimpa(): void
    {
        $senin = now()->startOfWeek()->toDateString();
        file_put_contents($this->akar . '/schedule-photos/x/j.jpg', 'JPG');
        $this->qc->table('schedule_photos')->insert(['target_date' => $senin, 'file_path' => 'schedule-photos/x/j.jpg', 'uploaded_by' => 888]);
        $this->qc->table('schedule_photos')->insert(['target_date' => now()->subWeeks(3)->startOfWeek()->toDateString(), 'file_path' => 'schedule-photos/x/lama.jpg']);

        $h = $this->jalan();

        $this->assertSame(1, $h['jadwal_baru'], 'minggu lama tidak ikut');
        $this->assertSame('JPG', Storage::disk('public')->get('schedule-photos/x/j.jpg'));

        // Sudah ada: ulang = tidak menggandakan; qc ganti berkas = konflik, asata tetap.
        $this->assertSame(0, $this->jalan()['jadwal_baru']);
        $this->qc->table('schedule_photos')->update(['file_path' => 'schedule-photos/x/baru.jpg']);
        $h = $this->jalan();
        $this->assertCount(1, $h['konflik']);
        $this->assertSame('schedule-photos/x/j.jpg', DB::table('schedule_photos')->value('file_path'));
    }

    public function test_qc_tidak_pernah_ditulis(): void
    {
        $this->gambar('S1', 1);
        $sebelum = json_encode($this->qc->table('gambar_kerja')->get());

        $this->jalan();
        $this->jalan(true);

        $this->assertSame($sebelum, json_encode($this->qc->table('gambar_kerja')->get()));
    }

    public function test_hasil_cek_disimpan_terpisah_dari_hasil_sinkron(): void
    {
        $this->gambar('S1', 1);

        SinkronGambarQc::simpanHasil($this->jalan(true));
        $this->assertNotNull(SinkronGambarQc::hasilCek());
        $this->assertNull(SinkronGambarQc::hasilTerakhir(), 'cek tidak boleh dianggap sinkron sungguhan');

        SinkronGambarQc::simpanHasil($this->jalan());
        $this->assertSame(1, SinkronGambarQc::hasilTerakhir()['baru']);
        $this->assertTrue(SinkronGambarQc::hasilCek()['cek']);
    }

    public function test_akar_qc_null_bila_tidak_diatur(): void
    {
        $this->assertNull(SinkronGambarQc::akarQc());
    }
}
