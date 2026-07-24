<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HolidayService
{
    private const CALENDAR_ID = 'id.indonesian#holiday@group.v.calendar.google.com';
    private const CACHE_TTL   = 86400; // 24 jam

    public function getHolidays(int $year): \Illuminate\Support\Collection
    {
        $apiKey = config('services.google.calendar_api_key');

        if (empty($apiKey)) {
            return collect();
        }

        $cacheKey = "holidays_google_{$year}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($year, $apiKey) {
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
        });
    }
}
