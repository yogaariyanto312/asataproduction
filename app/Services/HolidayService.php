<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HolidayService
{
    private const CALENDAR_ID = 'id.indonesian#holiday@group.v.calendar.google.com';
    private const CACHE_TTL   = 86400; // 24 jam untuk data yang berhasil diambil

    /**
     * Kegagalan hanya ditahan sebentar.
     *
     * Sebelumnya hasil kosong ikut disimpan 24 jam: sekali panggilan ke Google
     * gagal (atau API key belum terpasang), kalender kehilangan seluruh tanda
     * hari libur selama sehari penuh walau jaringannya sudah pulih semenit
     * kemudian.
     */
    private const CACHE_TTL_GAGAL = 300; // 5 menit

    public function getHolidays(int $year): \Illuminate\Support\Collection
    {
        $apiKey = config('services.google.calendar_api_key');

        if (empty($apiKey)) {
            return collect();
        }

        $cacheKey = "holidays_google_{$year}";

        if ($tersimpan = Cache::get($cacheKey)) {
            return collect($tersimpan);
        }

        $hasil = $this->ambilDariGoogle($year, $apiKey);

        Cache::put($cacheKey, $hasil->all(), $hasil->isEmpty() ? self::CACHE_TTL_GAGAL : self::CACHE_TTL);

        return $hasil;
    }

    private function ambilDariGoogle(int $year, string $apiKey): \Illuminate\Support\Collection
    {
        try {
            $response = Http::timeout(10)->get(
                'https://www.googleapis.com/calendar/v3/calendars/' . urlencode(self::CALENDAR_ID) . '/events',
                [
                    'key'          => $apiKey,
                    'timeMin'      => "{$year}-01-01T00:00:00Z",
                    'timeMax'      => "{$year}-12-31T23:59:59Z",
                    'singleEvents' => 'true',
                    'orderBy'      => 'startTime',
                    'maxResults'   => 50,
                ]
            );

            if (! $response->successful()) {
                Log::warning('Google Calendar API gagal', ['status' => $response->status()]);
                return collect();
            }

            return collect($response->json('items', []))
                ->filter(fn($item) => isset($item['start']['date']))
                ->mapWithKeys(fn($item) => [
                    $item['start']['date'] => ['name' => $item['summary']],
                ]);
        } catch (\Exception $e) {
            Log::warning('HolidayService error: ' . $e->getMessage());
            return collect();
        }
    }
}
