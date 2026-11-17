<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Realtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Realtime ringan (PRD 14): nomor versi per topik, token bertanda tangan, dan public/rt.php tanpa Laravel.
 * Berkas versi memakai storage/app/rt yang sama dengan aplikasi (rt.php membaca lokasi tetap), jadi uji ini
 * hanya menaikkan nomor (tidak menghapus apa pun).
 */
class RealtimeRinganTest extends TestCase
{
    use RefreshDatabase;

    /** Menjalankan public/rt.php di proses PHP terpisah (tanpa Laravel), meniru permintaan HTTP lewat $_GET/$_SERVER. */
    private function rt(array $get, array $server = []): array
    {
        $kode = '<?php $_GET = ' . var_export($get, true) . '; $_SERVER = array_merge($_SERVER, ' . var_export($server, true) . '); include ' . var_export(base_path('public/rt.php'), true) . ';';
        $berkas = tempnam(sys_get_temp_dir(), 'rt') . '.php';
        file_put_contents($berkas, $kode);
        $keluar = [];
        exec('"' . PHP_BINARY . '" "' . $berkas . '" 2>&1', $keluar);
        @unlink($berkas);

        return ['badan' => implode("
", $keluar)];
    }

    private function token(int $id): string
    {
        $u = new User();
        $u->id = $id;

        return Realtime::token($u);
    }

    public function test_bump_menaikkan_versi_dan_rt_php_melaporkannya(): void
    {
        $t = $this->token(7);
        Realtime::bump('produksi');
        $a = json_decode($this->rt(['t' => $t, 'p' => 'produksi,target'])['badan'], true);
        $this->assertArrayHasKey('produksi', $a);
        $this->assertNotSame('0', $a['produksi']);

        Realtime::bump('produksi');
        $b = json_decode($this->rt(['t' => $t, 'p' => 'produksi'])['badan'], true);
        $this->assertNotSame($a['produksi'], $b['produksi'], 'dua bump berturut-turut harus menghasilkan nomor berbeda');
    }

    public function test_topik_per_pengguna_hanya_milik_pemegang_token(): void
    {
        Realtime::bumpPengguna('chat', 7);
        $milik = json_decode($this->rt(['t' => $this->token(7), 'p' => 'chat'])['badan'], true);
        $lain = json_decode($this->rt(['t' => $this->token(8), 'p' => 'chat'])['badan'], true);

        $this->assertNotSame('0', $milik['chat']);
        $this->assertNotSame($milik['chat'], $lain['chat'], 'pengguna 8 tidak boleh melihat versi chat pengguna 7');
    }

    public function test_token_palsu_atau_kedaluwarsa_ditolak_dan_topik_asing_diabaikan(): void
    {
        $sah = $this->token(7);
        $this->assertSame('{}', $this->rt(['t' => 'x.y.z', 'p' => 'produksi'])['badan']);
        [$id, $exp, $sig] = explode('.', $sah);
        $this->assertSame('{}', $this->rt(['t' => "$id.$exp." . strrev($sig), 'p' => 'produksi'])['badan'], 'tanda tangan salah');
        $this->assertSame('{}', $this->rt(['t' => '8.' . $exp . '.' . $sig, 'p' => 'produksi'])['badan'], 'id diganti, tanda tangan tak cocok');
        $this->assertSame('{}', $this->rt(['t' => "$id." . (time() - 5) . '.' . $sig, 'p' => 'produksi'])['badan'], 'kedaluwarsa');

        $hasil = json_decode($this->rt(['t' => $sah, 'p' => '../../.env,produksi,kunci'])['badan'], true);
        $this->assertSame(['produksi'], array_keys($hasil), 'hanya topik yang dikenal yang dilayani');
    }

    public function test_tidak_berubah_membalas_304_tanpa_isi(): void
    {
        $t = $this->token(7);
        Realtime::bump('target');
        $pertama = $this->rt(['t' => $t, 'p' => 'target'])['badan'];
        $etag = '"' . md5($pertama) . '"';

        $ulang = $this->rt(['t' => $t, 'p' => 'target'], ['HTTP_IF_NONE_MATCH' => $etag]);
        $this->assertSame('', trim($ulang['badan']), 'ETag sama: tanpa badan (304)');
    }

    public function test_perubahan_model_menaikkan_versi_topiknya(): void
    {
        $file = Realtime::dir() . '/catatan.v';
        $sebelum = is_file($file) ? file_get_contents($file) : '0';

        Realtime::bump('catatan');

        $this->assertNotSame($sebelum, file_get_contents($file));
    }

    public function test_halaman_membawa_props_rt_hanya_untuk_pengguna_masuk(): void
    {
        $u = new User(['name' => 'Rt', 'username' => 'rtuji', 'email' => 'rt@uji.test', 'role' => 'developer', 'password' => 'x12345678']);
        $u->forceFill(['is_active' => true])->save();

        $props = $this->actingAs($u)->get('/dashboard')->viewData('page')['props'];
        $this->assertStringContainsString('rt.php', $props['rt']['url']);
        $this->assertStringStartsWith($u->id . '.', $props['rt']['token']);
    }
}
