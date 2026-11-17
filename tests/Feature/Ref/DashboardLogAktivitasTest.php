<?php

namespace Tests\Feature\Ref;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kartu "Log Aktivitas" di dashboard: isi yang sama dengan notifikasi bot
 * Telegram/Discord. Memuat nama orang dan semua perubahan data, jadi dijaga
 * izin dashboard.aktivitas (bawaan: developer & admin).
 *
 * asata: data dikirim lewat props (`activities`, null bila tak berizin) dan
 * JSON api.dashboard.aktivitas; label & pemisah tanggal dirender Dashboard.jsx.
 */
class DashboardLogAktivitasTest extends TestCase
{
    use RefreshDatabase;

    private function log(User $oleh, string $aksi, string $isi, ?string $waktu = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $oleh->id, 'action' => $aksi, 'description' => $isi,
            'ip_address' => '10.0.0.1', 'created_at' => $waktu ?? now(),
        ]);
    }

    private function props(User $user): array
    {
        return $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('page')['props'];
    }

    public function test_developer_dan_admin_melihat_kartu_beserta_isinya(): void
    {
        $op = User::factory()->create(['role' => 'operator', 'name' => 'Budi Operator']);
        $this->log($op, 'create', 'Menambah produk: 26T001-100KVA');

        foreach (['developer', 'admin'] as $peran) {
            $this->flushSession();
            $log = $this->props(User::factory()->create(['role' => $peran]))['activities'];

            $this->assertNotNull($log, "{$peran} harus melihat kartu.");
            $baris = collect($log['items'])->firstWhere('desc', 'Menambah produk: 26T001-100KVA');
            $this->assertNotNull($baris);
            $this->assertSame('Budi Operator', $baris['user']);
            $this->assertSame('create', $baris['action']);
        }

        $jsx = file_get_contents(resource_path('js/Pages/Dashboard.jsx'));
        $this->assertStringContainsString('id="log-aktivitas"', $jsx);
        $this->assertStringContainsString("create: ['TAMBAH', 'is-tambah']", $jsx);
    }

    public function test_peran_lain_tidak_melihat_kartu_maupun_isinya(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $this->log($dev, 'create', 'Tambah operator: Rahasia Sekali');

        foreach (['operator', 'visitor', 'supervisor', 'mandor'] as $peran) {
            $this->flushSession();
            $orang = User::factory()->create(['role' => $peran]);

            $html = $this->actingAs($orang)->get(route('dashboard'))->assertOk()->getContent();
            $this->assertNull($this->props($orang)['activities'], "{$peran} tidak boleh melihat kartu.");
            $this->assertStringNotContainsString('Rahasia Sekali', $html);
            $this->actingAs($orang)->getJson(route('api.dashboard.aktivitas'))->assertForbidden();
        }
    }

    public function test_isi_log_di_escape(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $this->log($dev, 'update', 'Edit produk: <script>alert(1)</script>');

        // JSON tidak pernah membawa tag mentah; React merender teksnya sebagai teks.
        $mentah = $this->actingAs($dev)->getJson(route('api.dashboard.aktivitas'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $mentah);

        $html = $this->actingAs($dev)->get(route('dashboard'))->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);

        $jsx = file_get_contents(resource_path('js/Pages/Dashboard.jsx'));
        $this->assertStringContainsString('<span className="au-db-log-isi">{l.desc}</span>', $jsx,
            'Deskripsi log harus dirender sebagai teks, bukan HTML.');
    }

    public function test_urutan_terbaru_dulu_dan_dibatasi_100(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $sama = now()->format('Y-m-d H:i:s');

        for ($i = 1; $i <= 105; $i++) {
            // Semua di detik yang sama: urutan harus tetap urutan kejadian.
            $this->log($dev, 'create', "Aksi nomor {$i}.", $sama);
        }

        $data = $this->actingAs($dev)->getJson(route('api.dashboard.aktivitas'))->assertOk()->json();
        $isi  = array_column($data['items'], 'desc');

        $this->assertCount(100, $isi, 'Yang dikirim harus 100 baris.');
        $this->assertSame('Aksi nomor 105.', $isi[0], 'Terbaru harus di atas.');
        $this->assertSame('Aksi nomor 104.', $isi[1]);
        $this->assertNotContains('Aksi nomor 5.', $isi, 'Entri paling lama di luar batas tidak ikut.');
        $this->assertSame(ActivityLog::max('id'), $data['terbaru']);
    }

    public function test_pemisah_tanggal_hari_ini_dan_kemarin(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        $this->log($dev, 'login', 'User login ke sistem', now()->subDay());
        $this->log($dev, 'logout', 'User logout dari sistem');

        $items = $this->actingAs($dev)->getJson(route('api.dashboard.aktivitas'))->assertOk()->json('items');

        $this->assertSame(['Hari ini', 'Kemarin'], array_column($items, 'day'));
        $this->assertSame('logout', $items[0]['action']);

        $jsx = file_get_contents(resource_path('js/Pages/Dashboard.jsx'));
        $this->assertStringContainsString("logout: ['KELUAR', 'is-keluar']", $jsx);

        // Pemisah yang sticky saling menumpuk & menimpa baris log saat digulir.
        $css = file_get_contents(resource_path('css/asata-ui.css'));
        $this->assertDoesNotMatchRegularExpression('/\.au-db-log-hari \{[^}]*sticky/', $css, 'Pemisah tanggal tidak boleh sticky.');
    }

    public function test_log_tanpa_pengguna_ditulis_sistem(): void
    {
        $dev = User::factory()->create(['role' => 'developer']);
        ActivityLog::create(['user_id' => null, 'action' => 'create', 'description' => 'Tugas terjadwal', 'created_at' => now()]);

        $this->actingAs($dev)->getJson(route('api.dashboard.aktivitas'))->assertOk()
            ->assertJsonPath('items.0.user', 'Sistem');
    }

    public function test_izin_bisa_diberikan_ke_peran_lain_lewat_hak_akses(): void
    {
        \Illuminate\Support\Facades\DB::table('role_menu_permissions')->insert([
            'role' => 'supervisor', 'menu_key' => 'dashboard.aktivitas', 'allowed' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        \App\Support\MenuAccess::flush();

        $spv = User::factory()->create(['role' => 'supervisor']);
        $this->actingAs($spv)->getJson(route('api.dashboard.aktivitas'))->assertOk();
    }
}
