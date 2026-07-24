<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NoteController extends Controller
{
    public function index()
    {
        $targets = User::where('id', '!=', auth()->id())
            ->where('is_active', true)
            ->orderByRaw("FIELD(role, 'developer', 'admin', 'supervisor', 'mandor', 'operator', 'visitor')")
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'department']);

        return view('notes.index', compact('targets'));
    }

    public function list()
    {
        $userId = auth()->id();

        $notes = Note::with(['user:id,name,role', 'targetUser:id,name'])
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('target_user_id', $userId)
                  ->orWhere('is_broadcast', true);
            })
            ->orderByRaw('is_done ASC, CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC, due_date ASC, created_at DESC')
            ->get();

        return response()->json($notes);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'content'        => ['nullable', 'string'],
            'due_date'       => ['nullable', 'date'],
            'color'          => ['nullable', 'string', 'in:blue,green,yellow,amber,red,purple,teal,slate'],
            'target_user_id' => ['nullable', function ($attr, $val, $fail) {
                if ($val === null || $val === 'all') return;
                if (!is_numeric($val) || !\App\Models\User::where('id', $val)->exists()) {
                    $fail('User tujuan tidak valid.');
                }
            }],
            'photo'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('notes/photos', 'public');
            if ($photoPath === false) {
                abort(500, 'Gagal menyimpan foto.');
            }
        }

        $isBroadcast  = $request->input('target_user_id') === 'all';
        $targetUserId = $isBroadcast ? null : ($request->input('target_user_id') ?: null);

        $note = Note::create([
            'user_id'        => auth()->id(),
            'title'          => $request->input('title'),
            'content'        => $request->input('content'),
            'due_date'       => $request->input('due_date'),
            'color'          => $request->input('color', 'blue'),
            'target_user_id' => $targetUserId,
            'is_broadcast'   => $isBroadcast,
            'photo_path'     => $photoPath,
        ]);

        return response()->json($note->fresh()->load(['user:id,name,role', 'targetUser:id,name']));
    }

    public function update(Request $request, Note $note)
    {
        $userId = auth()->id();

        $isOwner    = $note->user_id === $userId;
        $isReceiver = $note->target_user_id === $userId || $note->is_broadcast;

        if (!$isOwner && !$isReceiver) {
            abort(403);
        }

        // Penerima catatan hanya bisa update is_done
        if (!$isOwner) {
            $isDone = $request->boolean('is_done');
            $note->update([
                'is_done' => $isDone,
                'done_at' => $isDone ? now() : null,
            ]);
            return response()->json($note->fresh()->load(['user:id,name,role', 'targetUser:id,name']));
        }

        $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'content'        => ['nullable', 'string'],
            'due_date'       => ['nullable', 'date'],
            'color'          => ['nullable', 'string', 'in:blue,green,yellow,amber,red,purple,teal,slate'],
            'is_done'        => ['nullable', 'boolean'],
            'target_user_id' => ['nullable', function ($attr, $val, $fail) {
                if ($val === null || $val === 'all') return;
                if (!is_numeric($val) || !\App\Models\User::where('id', $val)->exists()) {
                    $fail('User tujuan tidak valid.');
                }
            }],
            'photo'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo'   => ['nullable'],
        ]);

        $isBroadcast  = $request->input('target_user_id') === 'all';
        $targetUserId = $isBroadcast ? null : ($request->input('target_user_id') ?: null);

        $updateData = [
            'title'          => $request->input('title'),
            'content'        => $request->input('content'),
            'due_date'       => $request->input('due_date'),
            'color'          => $request->input('color', 'blue'),
            'target_user_id' => $targetUserId,
            'is_broadcast'   => $isBroadcast,
        ];

        // Only update is_done/done_at when the field is explicitly sent
        if ($request->has('is_done')) {
            $isDone = $request->boolean('is_done');
            $updateData['is_done'] = $isDone;
            $updateData['done_at'] = $isDone ? now() : null;
        }

        // Handle photo upload / removal — store first, delete old only on success
        if ($request->hasFile('photo')) {
            $newPath = $request->file('photo')->store('notes/photos', 'public');
            if ($newPath === false) {
                abort(500, 'Gagal menyimpan foto.');
            }
            if ($note->photo_path) {
                Storage::disk('public')->delete($note->photo_path);
            }
            $updateData['photo_path'] = $newPath;
        } elseif ($request->input('remove_photo')) {
            if ($note->photo_path) {
                Storage::disk('public')->delete($note->photo_path);
            }
            $updateData['photo_path'] = null;
        }

        $note->update($updateData);

        return response()->json($note->fresh()->load(['user:id,name,role', 'targetUser:id,name']));
    }

    public function destroy(Note $note)
    {
        abort_if($note->user_id !== auth()->id(), 403);

        if ($note->photo_path) {
            Storage::disk('public')->delete($note->photo_path);
        }

        $note->delete();
        return response()->json(['ok' => true]);
    }
}
