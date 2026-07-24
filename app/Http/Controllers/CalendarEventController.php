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
        $year  = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $events = CalendarEvent::where('user_id', auth()->id())
            ->whereYear('event_date', $year)
            ->whereMonth('event_date', $month)
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

        return response()->json(['events' => $grouped]);
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
