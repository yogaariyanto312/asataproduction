<?php

namespace App\Filament\Widgets;

use App\Models\CalendarEvent;
use App\Services\HolidayService;
use Carbon\Carbon;
use Filament\Widgets\Widget;

/**
 * Kalender bulanan beserta agenda pribadi dan hari libur nasional.
 *
 * Navigasi bulan ditangani Livewire, jadi tombol bulan lalu / bulan depan
 * bekerja tanpa memuat ulang halaman.
 */
class KalenderAgenda extends Widget
{
    protected static ?int $sort = 8;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.kalender-agenda';

    public int $bulan;

    public int $tahun;

    public function mount(): void
    {
        $this->bulan = (int) now()->month;
        $this->tahun = (int) now()->year;
    }

    public function bulanSebelumnya(): void
    {
        $acuan = Carbon::create($this->tahun, $this->bulan, 1)->subMonthNoOverflow();
        $this->bulan = (int) $acuan->month;
        $this->tahun = (int) $acuan->year;
    }

    public function bulanBerikutnya(): void
    {
        $acuan = Carbon::create($this->tahun, $this->bulan, 1)->addMonthNoOverflow();
        $this->bulan = (int) $acuan->month;
        $this->tahun = (int) $acuan->year;
    }

    public function bulanIni(): void
    {
        $this->bulan = (int) now()->month;
        $this->tahun = (int) now()->year;
    }

    /**
     * Data satu bulan: label, deretan sel (termasuk sel kosong sebelum tanggal 1),
     * agenda per tanggal, dan daftar agenda untuk ditampilkan di bawah kalender.
     *
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        $awal    = Carbon::create($this->tahun, $this->bulan, 1)->startOfDay();
        $akhir   = $awal->copy()->endOfMonth();
        $hariIni = now()->toDateString();

        $libur = app(HolidayService::class)->getHolidays($this->tahun);

        $agenda = CalendarEvent::query()
            ->where('user_id', auth()->id())
            ->whereBetween('event_date', [$awal->toDateString(), $akhir->toDateString()])
            ->orderBy('event_date')
            ->orderBy('created_at')
            ->get(['id', 'event_date', 'title', 'description', 'created_by_name'])
            ->groupBy(fn ($e) => $e->event_date->toDateString());

        // Senin sebagai kolom pertama, sesuai kalender lama.
        $kosong = ($awal->dayOfWeekIso - 1);
        $sel    = array_fill(0, $kosong, null);

        for ($hari = 1; $hari <= $akhir->day; $hari++) {
            $tanggal   = $awal->copy()->day($hari);
            $tanggalStr = $tanggal->toDateString();
            $namaLibur = $libur[$tanggalStr] ?? null;

            $sel[] = [
                'hari'     => $hari,
                'tanggal'  => $tanggalStr,
                'iniHari'  => $tanggalStr === $hariIni,
                'akhirPekan' => $tanggal->isoWeekday() >= 6,
                'libur'    => is_array($namaLibur) ? ($namaLibur['name'] ?? null) : $namaLibur,
                'agenda'   => $agenda->get($tanggalStr, collect())->values()->all(),
            ];
        }

        return [
            'label'   => $awal->locale('id')->isoFormat('MMMM YYYY'),
            'sel'     => $sel,
            'agenda'  => $agenda,
            'iniBulanBerjalan' => $awal->isSameMonth(now()),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return $this->data();
    }
}
