<?php

namespace Tests\Feature\Ref;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Support\FaseBulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Keterangan pada kalender dashboard: hari libur nasional, fase bulan, dan agenda.
 *
 * Keterangannya muncul sebagai tooltip saat tanggalnya disentuh/di-hover — itu
 * pilihan Yoga (2026-09-19); daftar di bawah kalender sempat dicoba lalu dibuang
 * karena bikin kartunya panjang. (asata: data dari JSON api.dashboard.calendar,
 * tooltip dirender komponen Calendar.jsx.)
 */
class KalenderKeteranganTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'developer']);
    }

    private function palsukanLibur(array $items): void
    {
        config(['services.google.calendar_api_key' => 'kunci-uji']);
        Cache::flush();

        Http::fake([
            'www.googleapis.com/*' => Http::response(['items' => $items], 200),
        ]);
    }

    private function hari(User $user, string $bulan): \Illuminate\Support\Collection
    {
        return collect($this->actingAs($user)
            ->getJson(route('api.dashboard.calendar', ['month' => $bulan]))
            ->assertOk()->json('days'))->keyBy('date');
    }

    public function test_hari_libur_nasional_tampil_di_kalender(): void
    {
        $this->palsukanLibur([
            ['start' => ['date' => '2026-08-17'], 'summary' => 'Hari Proklamasi Kemerdekaan R.I.'],
        ]);

        $this->assertSame('Hari Proklamasi Kemerdekaan R.I.', $this->hari($this->user(), '2026-08')['2026-08-17']['holiday'],
            'Nama hari libur harus ada di tooltip tanggalnya.');
    }

    public function test_kegagalan_ambil_libur_tidak_tersimpan_seharian(): void
    {
        config(['services.google.calendar_api_key' => 'kunci-uji']);
        Cache::flush();

        // Satu tiruan yang jawabannya berubah: pertama gagal, lalu pulih.
        $sehat = false;
        Http::fake(function () use (&$sehat) {
            return $sehat
                ? Http::response(['items' => [
                    ['start' => ['date' => '2026-08-17'], 'summary' => 'Hari Proklamasi Kemerdekaan R.I.'],
                ]], 200)
                : Http::response('', 500);
        });

        $layanan = app(\App\Services\HolidayService::class);

        $this->assertCount(0, $layanan->getHolidays(2026), 'Saat layanan mati, hasilnya memang kosong.');

        // Hasil kosong hanya boleh ditahan sebentar (5 menit), bukan 24 jam.
        $this->travel(6)->minutes();
        $sehat = true;

        $this->assertCount(1, $layanan->getHolidays(2026), 'Harus pulih begitu layanannya normal lagi.');
    }

    public function test_hasil_yang_berhasil_diambil_dipakai_ulang_dari_cache(): void
    {
        $this->palsukanLibur([
            ['start' => ['date' => '2026-08-17'], 'summary' => 'Kemerdekaan'],
        ]);

        $layanan = app(\App\Services\HolidayService::class);
        $this->assertCount(1, $layanan->getHolidays(2026));

        // Panggilan berikutnya tidak menembak API lagi.
        Http::fake(['www.googleapis.com/*' => Http::response('', 500)]);
        $this->assertCount(1, $layanan->getHolidays(2026), 'Data yang sudah ada tidak boleh hilang.');
    }

    public function test_fase_bulan_muncul_sebagai_keterangan(): void
    {
        $this->palsukanLibur([]);

        $hari = $this->hari($this->user(), '2026-08');

        $this->assertSame('purnama', $hari['2026-08-28']['phase']);
        $this->assertSame('baru', $hari['2026-08-13']['phase']);

        $jsx = file_get_contents(resource_path('js/Components/Calendar.jsx'));
        $this->assertStringContainsString('Bulan purnama', $jsx);
        $this->assertStringContainsString('Bulan baru', $jsx);
        $this->assertStringContainsString('Purnama / bulan baru', $jsx, 'Legenda fase bulan tidak ada.');
    }

    public function test_keterangan_berupa_tooltip_bukan_daftar_di_bawah_kalender(): void
    {
        $jsx = file_get_contents(resource_path('js/Components/Calendar.jsx'));
        $css = file_get_contents(resource_path('css/asata-ui.css'));

        // Pilihan Yoga: kartu kalender tetap ringkas, keterangan muncul saat
        // tanggalnya di-hover.
        $this->assertDoesNotMatchRegularExpression("/Keterangan\\s*(\\{|'\\s*\\+)\\s*cal\\.label/", $jsx,
            'Daftar keterangan di bawah kalender sengaja tidak dipakai.');
        $this->assertStringContainsString("className={'au-cal-tip is-' + d.tip}", $jsx, 'Tooltip keterangan harus ada.');
        $this->assertStringContainsString('.au-cal-cell:hover .au-cal-tip { display: block; }', $css);
    }

    public function test_tooltip_tidak_terpotong_kartu(): void
    {
        $this->palsukanLibur([]);
        $hari = $this->hari($this->user(), '2026-08');

        // Kartu tidak boleh memotong isinya, kalau tidak tooltip ikut terpangkas.
        $css = file_get_contents(resource_path('css/asata-ui.css'));
        $this->assertDoesNotMatchRegularExpression('/\.au-db-cal \{[^}]*overflow: hidden/', $css,
            'Kartu kalender tidak boleh memotong tooltip yang menjulur keluar.');

        // Senin (kolom paling kiri) dan Minggu (paling kanan) dirapatkan ke sisinya.
        $this->assertSame('kiri', $hari['2026-08-17']['tip'], 'Tooltip kolom paling kiri harus rata kiri.');
        $this->assertSame('kanan', $hari['2026-08-30']['tip'], 'Tooltip kolom paling kanan harus rata kanan.');
        $this->assertSame('tengah', $hari['2026-08-20']['tip'], 'Tanggal di tengah tetap dipusatkan.');
        $this->assertStringContainsString('.au-cal-tip.is-kiri { left: 0; transform: none; }', $css);
        $this->assertStringContainsString('.au-cal-tip.is-kanan { left: auto; right: 0; transform: none; }', $css);
    }

    public function test_perhitungan_fase_bulan_cocok_dengan_data_astronomi(): void
    {
        // Pembanding: purnama & bulan baru yang sudah diketahui (waktu Indonesia).
        $diketahui = [
            ['2025-01', '2025-01-14', 'purnama'],
            ['2025-01', '2025-01-29', 'baru'],
            ['2026-08', '2026-08-28', 'purnama'],
            ['2026-08', '2026-08-13', 'baru'],
        ];

        foreach ($diketahui as [$bulan, $tanggal, $fase]) {
            [$thn, $bln] = explode('-', $bulan);
            $hasil = FaseBulan::untukBulan((int) $thn, (int) $bln);

            $this->assertArrayHasKey($tanggal, $hasil, "Fase {$fase} pada {$tanggal} tidak terdeteksi.");
            $this->assertSame($fase, $hasil[$tanggal]);
        }
    }

    public function test_fase_bulan_selalu_ada_setiap_bulan(): void
    {
        foreach (range(1, 12) as $bulan) {
            $hasil = FaseBulan::untukBulan(2026, $bulan);
            $this->assertNotEmpty($hasil, "Bulan {$bulan}/2026 tidak punya fase sama sekali.");

            foreach ($hasil as $tgl => $fase) {
                $this->assertSame((int) $bulan, (int) date('n', strtotime($tgl)), 'Fase dari bulan lain ikut terbawa.');
                $this->assertContains($fase, ['purnama', 'baru']);
            }
        }
    }

    public function test_agenda_ikut_jadi_keterangan_tanggal(): void
    {
        $this->palsukanLibur([]);
        $user = $this->user();

        CalendarEvent::create([
            'user_id'         => $user->id,
            'event_date'      => '2026-08-20',
            'title'           => 'Audit internal',
            'created_by_name' => $user->name,
        ]);

        $this->assertSame('Audit internal', $this->hari($user, '2026-08')['2026-08-20']['events'][0]['title'],
            'Judul agenda tampil di tooltip tanggalnya.');
    }

    public function test_keterangan_hanya_untuk_bulan_yang_dibuka(): void
    {
        $this->palsukanLibur([
            ['start' => ['date' => '2026-08-17'], 'summary' => 'Kemerdekaan'],
            ['start' => ['date' => '2026-12-25'], 'summary' => 'Hari Raya Natal'],
        ]);

        $libur = $this->hari($this->user(), '2026-08')->pluck('holiday')->filter()->values()->all();

        $this->assertSame(['Kemerdekaan'], $libur, 'Libur bulan lain tidak boleh ikut terbawa.');
    }
}
