<?php

namespace Tests\Feature\Ref;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kartu kalender di dashboard: pindah bulan lewat tombol di header kartunya.
 *
 * Diport dari referensi. asata memakai Inertia: kalender bulan berjalan ikut
 * di props halaman, bulan lain diambil sebagai JSON dari api.dashboard.calendar
 * (referensi mengirim potongan HTML). Yang diuji tetap sama: isi bulannya,
 * tombol navigasi, sorot hari ini, agenda, validasi, dan ukuran kiriman.
 */
class DashboardKalenderTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'developer']);
    }

    private function agenda(User $user, string $tanggal, string $judul): CalendarEvent
    {
        return CalendarEvent::create([
            'user_id'         => $user->id,
            'event_date'      => $tanggal,
            'title'           => $judul,
            'created_by_name' => $user->name,
        ]);
    }

    private function kalenderDashboard(User $user): array
    {
        return $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('page')['props']['calendar'];
    }

    private function bulan(User $user, string $bulan): array
    {
        return $this->actingAs($user)->getJson(route('api.dashboard.calendar', ['month' => $bulan]))->assertOk()->json();
    }

    /** Semua judul agenda di sebuah bulan. */
    private function judulAgenda(array $kal): array
    {
        return collect($kal['days'])->flatMap(fn ($d) => collect($d['events'])->pluck('title'))->all();
    }

    public function test_dashboard_menampilkan_tombol_pindah_bulan(): void
    {
        $kal = $this->kalenderDashboard($this->user());

        $this->assertSame(now()->copy()->subMonth()->format('Y-m'), $kal['prev'], 'Tombol bulan sebelumnya tidak ada.');
        $this->assertSame(now()->copy()->addMonth()->format('Y-m'), $kal['next'], 'Tombol bulan berikutnya tidak ada.');

        $jsx = file_get_contents(resource_path('js/Components/Calendar.jsx'));
        $this->assertStringContainsString('data-cal-month={cal.prev}', $jsx);
        $this->assertStringContainsString('data-cal-month={cal.next}', $jsx);
        $this->assertStringContainsString('id="dashboard-calendar"', file_get_contents(resource_path('js/Pages/Dashboard.jsx')));
    }

    public function test_bulan_ini_tidak_perlu_tombol_hari_ini(): void
    {
        $kal = $this->kalenderDashboard($this->user());

        // Tombol "Hari ini" (title "Kembali ke bulan ini") hanya dirender bila !isCurrent.
        $this->assertTrue($kal['isCurrent'], 'Tombol "Hari ini" hanya perlu muncul saat sedang melihat bulan lain.');
        $this->assertStringContainsString("{!cal.isCurrent ? (", file_get_contents(resource_path('js/Components/Calendar.jsx')));
    }

    public function test_memuat_bulan_lain(): void
    {
        $user  = $this->user();
        $bulan = now()->copy()->subMonth();

        $kal = $this->bulan($user, $bulan->format('Y-m'));

        $this->assertSame($bulan->locale('id')->isoFormat('MMMM YYYY'), $kal['label'], 'Judul bulan harus ikut berubah.');
        $this->assertFalse($kal['isCurrent'], 'Saat di bulan lain harus ada jalan pulang.');
        $this->assertSame(now()->format('Y-m'), $kal['current']);
    }

    public function test_tanggal_hari_ini_hanya_disorot_di_bulan_berjalan(): void
    {
        $user = $this->user();

        $bulanIni = $this->bulan($user, now()->format('Y-m'));
        $sorot = collect($bulanIni['days'])->where('today', true);
        $this->assertCount(1, $sorot, 'Tanggal hari ini harus disorot saat melihat bulan berjalan.');
        $this->assertSame(now()->toDateString(), $sorot->first()['date']);

        $bulanLain = $this->bulan($user, now()->copy()->subMonth()->format('Y-m'));
        $this->assertCount(0, collect($bulanLain['days'])->where('today', true),
            'Bulan lain tidak boleh ikut menyorot tanggal hari ini.');
    }

    public function test_agenda_bulan_yang_dibuka_ikut_tampil(): void
    {
        $user  = $this->user();
        $bulan = now()->copy()->subMonth();
        $tgl   = $bulan->copy()->startOfMonth()->addDays(9);

        $this->agenda($user, $tgl->toDateString(), 'Kalibrasi alat');
        $this->agenda($user, now()->toDateString(), 'Agenda bulan ini');

        $judul = $this->judulAgenda($this->bulan($user, $bulan->format('Y-m')));

        $this->assertContains('Kalibrasi alat', $judul);
        $this->assertNotContains('Agenda bulan ini', $judul, 'Agenda bulan lain tidak boleh ikut terbawa.');
    }

    public function test_agenda_orang_lain_tidak_ikut_terbawa(): void
    {
        $saya   = $this->user();
        $orang2 = User::factory()->create(['role' => 'operator']);

        $this->agenda($orang2, now()->toDateString(), 'Punya orang lain');

        $this->assertNotContains('Punya orang lain', $this->judulAgenda($this->bulan($saya, now()->format('Y-m'))));
    }

    public function test_bulan_tidak_valid_ditolak(): void
    {
        $user = $this->user();

        foreach (['2026-13', 'bukan-bulan', '2026', ''] as $buruk) {
            $this->actingAs($user)
                ->getJson(route('api.dashboard.calendar', ['month' => $buruk]))
                ->assertStatus(422);
        }
    }

    public function test_bulan_terlalu_jauh_ditolak(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->getJson(route('api.dashboard.calendar', ['month' => now()->copy()->addYears(6)->format('Y-m')]))
            ->assertStatus(422);

        $this->actingAs($user)
            ->getJson(route('api.dashboard.calendar', ['month' => now()->copy()->subYears(6)->format('Y-m')]))
            ->assertStatus(422);
    }

    public function test_butuh_login(): void
    {
        $this->get(route('api.dashboard.calendar', ['month' => now()->format('Y-m')]))
            ->assertRedirect(route('login'));
    }

    public function test_pergantian_bulan_hanya_mengirim_kalender_bukan_seluruh_dashboard(): void
    {
        $user = $this->user();

        $dashboard = strlen($this->actingAs($user)->get(route('dashboard'))->getContent());
        $kalender  = strlen($this->actingAs($user)
            ->getJson(route('api.dashboard.calendar', ['month' => now()->format('Y-m')]))->getContent());

        fwrite(STDERR, sprintf("dashboard penuh %d KB vs kalender %d KB\n", $dashboard / 1024, $kalender / 1024));

        $this->assertLessThan($dashboard / 3, $kalender,
            'Pindah bulan seharusnya jauh lebih ringan daripada memuat ulang dashboard.');
    }

    public function test_jumlah_hari_mengikuti_bulannya(): void
    {
        $tanggal = collect($this->bulan($this->user(), '2028-02')['days'])->pluck('date');

        $this->assertContains('2028-02-29', $tanggal, 'Tahun kabisat harus punya 29 Februari.');
        $this->assertNotContains('2028-02-30', $tanggal);
    }

    /**
     * Dulu bulan yang dibuka bergantung pada tanggal HARI INI: tanggal 29–31,
     * Februari meluber ke Maret. Waktu dipaku supaya test ini tidak cuma merah
     * tiga hari dalam sebulan.
     */
    public function test_bulan_tidak_bergeser_saat_dibuka_di_akhir_bulan(): void
    {
        $user = $this->user();

        foreach (['2026-01-29', '2026-01-30', '2026-01-31', '2026-08-31'] as $hariIni) {
            $this->travelTo(\Illuminate\Support\Carbon::parse($hariIni . ' 10:00'));

            foreach (['2026-02' => 28, '2026-04' => 30, '2026-06' => 30] as $bulan => $hari) {
                $tanggal = collect($this->bulan($user, $bulan)['days'])->pluck('date');

                $this->assertContains($bulan . '-' . $hari, $tanggal, "Dibuka {$hariIni}: kalender {$bulan} kehilangan tanggal {$hari}.");
                $this->assertContains($bulan . '-01', $tanggal, "Dibuka {$hariIni}: kalender {$bulan} bergeser ke bulan lain.");
                $this->assertCount($hari, $tanggal);
            }
        }

        $this->travelBack();
    }
}
