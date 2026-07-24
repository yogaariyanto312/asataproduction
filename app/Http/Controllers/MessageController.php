<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MessageController extends Controller
{
    // Semua role: kirim pesan (dengan optional recipient_id)
    public function store(Request $request)
    {
        $request->validate([
            'message'      => ['required', 'string', 'max:1000'],
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        \App\Models\Message::create([
            'sender_id'    => auth()->id(),
            'recipient_id' => $request->recipient_id,
            'message'      => $request->message,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('chat_sent', 'Pesan terkirim.');
    }

    // Semua role: ambil pesan miliknya — dikirim ATAU diterima (AJAX)
    public function myMessages()
    {
        $userId   = auth()->id();
        $messages = \App\Models\Message::with('sender:id,name,role,department,avatar,last_seen_at')
            ->with('recipient:id,name,role,department,avatar,last_seen_at')
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('recipient_id', $userId);
            })
            ->orderBy('created_at')
            ->get(['id', 'sender_id', 'recipient_id', 'message', 'reply', 'replied_at', 'is_read', 'created_at']);

        return response()->json($messages);
    }

    // Admin: balas pesan (AJAX)
    public function reply(Request $request, \App\Models\Message $message)
    {
        $request->validate(['reply' => ['required', 'string', 'max:1000']]);

        $message->update([
            'reply'      => $request->reply,
            'replied_at' => now(),
            'is_read'    => true,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Balasan terkirim.');
    }

    // Notifikasi global: pesan baru + catatan jatuh tempo
    public function notifications(Request $request)
    {
        $userId   = auth()->id();
        $lastId   = (int) $request->input('last_msg_id', 0);

        // Pesan baru
        $newMessages = \App\Models\Message::with('sender:id,name,role,avatar')
            ->where('id', '>', $lastId)
            ->where('sender_id', '!=', $userId)
            ->where(function ($q) use ($userId) {
                $q->where('recipient_id', $userId)
                  ->orWhereNull('recipient_id');
            })
            ->orderBy('id')
            ->get(['id', 'sender_id', 'message', 'created_at'])
            ->map(fn($m) => [
                'id'          => $m->id,
                'sender_name' => $m->sender->name ?? 'Seseorang',
                'preview'     => \Illuminate\Support\Str::limit($m->message, 60),
            ]);

        // Catatan jatuh tempo hari ini (belum selesai) milik atau ditujukan ke user ini
        $noteReminders = \App\Models\Note::where('is_done', false)
            ->whereDate('due_date', today())
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('target_user_id', $userId);
            })
            ->get(['id', 'title', 'due_date'])
            ->map(fn($n) => [
                'id'    => $n->id,
                'title' => \Illuminate\Support\Str::limit($n->title, 60),
            ]);

        // Hitung total pesan belum dibaca milik user ini
        $unreadCount = \App\Models\Message::where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->where(function ($q) use ($userId) {
                $q->where('recipient_id', $userId)
                  ->orWhereNull('recipient_id');
            })
            ->count();

        return response()->json([
            'messages'       => $newMessages,
            'note_reminders' => $noteReminders,
            'last_msg_id'    => $newMessages->max('id') ?? $lastId,
            'unread_count'   => $unreadCount,
        ]);
    }

    // Fetch pesan baru saja (id > last_id) — smart polling
    public function since(Request $request)
    {
        $lastId = (int) $request->input('last_id', 0);
        $userId = auth()->id();

        $messages = \App\Models\Message::with([
                'sender:id,name,role,avatar',
                'recipient:id,name,role,avatar',
            ])
            ->where('id', '>', $lastId)
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('recipient_id', $userId)
                  ->orWhereNull('recipient_id');
            })
            ->orderBy('id')
            ->get(['id','sender_id','recipient_id','message','reply','replied_at','is_read','created_at']);

        $messages->each(function ($m) {
            if ($m->sender)    $m->sender->avatar    = $m->sender->avatarUrl();
            if ($m->recipient) $m->recipient->avatar = $m->recipient->avatarUrl();
        });

        return response()->json([
            'messages' => $messages,
            'has_new'  => $messages->isNotEmpty(),
        ]);
    }

    // Admin: tandai sudah dibaca
    public function markRead(\App\Models\Message $message)
    {
        $message->update(['is_read' => true]);
        return response()->json(['ok' => true]);
    }

    // Semua role: tandai seluruh pesan dari satu lawan bicara sebagai dibaca.
    // Aman: hanya menandai pesan yang ditujukan ke user ini (broadcast lama hanya
    // untuk role privileged, sesuai perilaku inbox admin/supervisor).
    public function markConversationRead($partnerId)
    {
        $myId         = auth()->id();
        $isPrivileged = \in_array(auth()->user()->role, ['developer', 'admin', 'supervisor', 'mandor'], true);

        $updated = \App\Models\Message::where('sender_id', $partnerId)
            ->where('is_read', false)
            ->where(function ($q) use ($myId, $isPrivileged) {
                $q->where('recipient_id', $myId);
                if ($isPrivileged) {
                    $q->orWhereNull('recipient_id'); // pesan lama tanpa recipient (operator→admin)
                }
            })
            ->update(['is_read' => true]);

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    // Admin: hapus satu pesan
    public function destroy(\App\Models\Message $message)
    {
        $message->delete();
        return response()->json(['ok' => true]);
    }

    // Hapus seluruh percakapan antara user saat ini dan kontak tertentu (semua role)
    public function destroyConversation($partnerId)
    {
        $myId = auth()->id();

        // Pastikan user hanya bisa hapus percakapan yang melibatkan dirinya
        \App\Models\Message::where(function ($q) use ($partnerId, $myId) {
            $q->where(fn($q) => $q->where('sender_id', $partnerId)->where('recipient_id', $myId))
              ->orWhere(fn($q) => $q->where('sender_id', $myId)->where('recipient_id', $partnerId));
        })->delete();

        return response()->json(['ok' => true]);
    }

    // Operator: halaman chat (lama)
    public function chat()
    {
        return view('chat.index');
    }

    // Semua role: halaman chat terpadu (WhatsApp-style)
    public function chatUnified()
    {
        return view('chat.unified');
    }

    // Semua role: update last_seen_at (ping)
    public function ping()
    {
        auth()->user()->update(['last_seen_at' => now()]);
        return response()->json(['ok' => true]);
    }

    // Semua role: tandai sedang mengetik ke recipient tertentu
    public function typing(Request $request)
    {
        $request->validate(['recipient_id' => ['required', 'integer', 'exists:users,id']]);
        auth()->user()->update([
            'typing_to' => $request->recipient_id,
            'typing_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    // Semua role: daftar kontak sesuai role masing-masing
    public function contacts()
    {
        $user = auth()->user();

        if ($user->role === 'developer') {
            $users = \App\Models\User::where('id', '!=', $user->id)
                ->where('is_active', true)
                ->whereIn('role', ['admin', 'supervisor', 'mandor', 'operator', 'visitor'])
                ->orderByRaw("FIELD(role, 'admin', 'supervisor', 'mandor', 'operator', 'visitor')")
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'department', 'avatar', 'last_seen_at']);
        } elseif ($user->role === 'admin') {
            $users = \App\Models\User::whereIn('role', ['developer', 'supervisor', 'mandor', 'operator', 'visitor'])
                ->where('is_active', true)
                ->orderByRaw("FIELD(role, 'developer', 'supervisor', 'mandor', 'operator', 'visitor')")
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'department', 'avatar', 'last_seen_at']);
        } elseif ($user->role === 'supervisor') {
            $users = \App\Models\User::whereIn('role', ['developer', 'admin', 'supervisor', 'mandor', 'operator'])
                ->where('id', '!=', $user->id)
                ->where('is_active', true)
                ->orderByRaw("FIELD(role, 'developer', 'admin', 'supervisor', 'mandor', 'operator')")
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'department', 'avatar', 'last_seen_at']);
        } elseif ($user->role === 'mandor') {
            $users = \App\Models\User::whereIn('role', ['developer', 'admin', 'supervisor', 'mandor', 'operator'])
                ->where('id', '!=', $user->id)
                ->where('is_active', true)
                ->orderByRaw("FIELD(role, 'developer', 'admin', 'supervisor', 'mandor', 'operator')")
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'department', 'avatar', 'last_seen_at']);
        } elseif ($user->role === 'visitor') {
            $users = \App\Models\User::whereIn('role', ['developer', 'admin'])
                ->where('is_active', true)
                ->orderByRaw("FIELD(role, 'developer', 'admin')")
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'department', 'avatar', 'last_seen_at']);
        } else {
            // Operator
            $users = \App\Models\User::whereIn('role', ['developer', 'admin', 'supervisor', 'mandor'])
                ->where('is_active', true)
                ->orderByRaw("FIELD(role, 'developer', 'admin', 'supervisor', 'mandor')")
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'department', 'avatar', 'last_seen_at']);
        }

        $typingIds = \App\Models\User::where('typing_to', $user->id)
            ->where('typing_at', '>=', now()->subSeconds(8))
            ->pluck('id')
            ->all();

        $result = $users->map(fn($u) => [
            'id'           => $u->id,
            'name'         => $u->name,
            'role'         => $u->role,
            'department'   => $u->department,
            'avatar'       => $u->avatarUrl(),
            'last_seen_at' => $u->last_seen_at,
            'is_typing'    => in_array($u->id, $typingIds),
        ]);

        return response()->json($result);
    }

    // Admin/Supervisor: halaman + AJAX semua pesan (dikirim & diterima)
    public function adminMessages(Request $request)
    {
        $userId   = auth()->id();
        $messages = \App\Models\Message::with('sender:id,name,department,role,avatar,last_seen_at')
            ->with('recipient:id,name,department,role,avatar,last_seen_at')
            ->where(function ($q) use ($userId) {
                $q->whereNull('recipient_id')       // pesan lama (operator→admin tanpa recipient)
                  ->orWhere('recipient_id', $userId) // pesan yang ditujukan ke saya
                  ->orWhere('sender_id', $userId);   // pesan yang saya kirim
            })
            ->orderByDesc('created_at')
            ->get();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($messages);
        }

        $unreadCount = $messages->where('is_read', false)
            ->where('sender_id', '!=', $userId)->count();
        return view('chat.admin', compact('messages', 'unreadCount'));
    }
}
