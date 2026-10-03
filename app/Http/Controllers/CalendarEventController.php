<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    /** Ambil semua event untuk bulan tertentu (JSON), dikelompokkan per tanggal. */
    public function byMonth(Request $request)
    {
        // Dijaga di rentang wajar (±5 tahun): tiap tahun yang berbeda memicu
        // panggilan Google Calendar API & entri cache baru di HolidayService,
        // jadi input bebas bisa dipakai menghabiskan kuota API.
        $data = $request->validate([
            'year'  => ['nullable', 'integer', 'between:' . (now()->year - 5) . ',' . (now()->year + 5)],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $year  = (int) ($data['year'] ?? now()->year);
        $month = (int) ($data['month'] ?? now()->month);

        $awal    = \Illuminate\Support\Carbon::create($year, $month, 1)->toDateString();
        $sesudah = \Illuminate\Support\Carbon::create($year, $month, 1)->addMonth()->toDateString();

        $bolehAgenda = \App\Support\MenuAccess::can(auth()->user(), 'dashboard.agenda');

        $events = ! $bolehAgenda ? collect() : CalendarEvent::where('user_id', auth()->id())
            ->where('event_date', '>=', $awal)
            ->where('event_date', '<', $sesudah)
            ->orderBy('event_date')
            ->orderBy('created_at')
            ->get(['id', 'event_date', 'title', 'description', 'user_id', 'created_by_name']);

        $grouped = [];
        foreach ($events as $e) {
            $key = $e->event_date->toDateString();
            $grouped[$key][] = [
                'id'          => $e->id,
                'title'       => $e->title,
                'description' => $e->description,
                'by'          => $e->created_by_name,
                'can_delete'  => auth()->id() === $e->user_id || auth()->user()->isPrivileged(),
            ];
        }

        // Hari libur ikut dikirim supaya kalender React bisa berpindah bulan
        // (termasuk lintas tahun) tanpa kehilangan penanda hari libur.
        $holidays = app(\App\Services\HolidayService::class)
            ->getHolidays($year)
            ->filter(fn ($v, $date) => str_starts_with($date, sprintf('%04d-%02d', $year, $month)));

        return response()->json([
            'events'   => $grouped,
            'holidays' => $holidays,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_date'  => ['required', 'date'],
            'title'       => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $event = CalendarEvent::create([
            'event_date'      => $data['event_date'],
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'user_id'         => auth()->id(),
            'created_by_name' => auth()->user()->name,
        ]);

        ActivityLog::record('create', "Tambah agenda: {$event->title} (" . $event->event_date->format('d/m/Y') . ')', $event);

        return response()->json([
            'success' => true,
            'event'   => [
                'id'          => $event->id,
                'title'       => $event->title,
                'description' => $event->description,
                'by'          => $event->created_by_name,
                'can_delete'  => true,
            ],
        ]);
    }

    public function destroy(CalendarEvent $calendarEvent)
    {
        abort_unless(
            auth()->id() === $calendarEvent->user_id || auth()->user()->isPrivileged(),
            403
        );

        $info = $calendarEvent->title;
        $calendarEvent->delete();
        ActivityLog::record('delete', "Hapus agenda: {$info}");

        return response()->json(['success' => true]);
    }
}
