<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\User;
use App\Support\HtmlCatatan;
use App\Support\ImageThumbnail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class NoteController extends Controller
{
    /** Batas jumlah catatan yang dikirim ke layar sekali muat (jaga ringan di HP). */
    private const BATAS_DAFTAR = 300;

    /** Relasi yang selalu dimuat untuk membentuk data layar (hindari N+1). */
    private const RELASI = ['user:id,name,role', 'targetUser:id,name', 'completions:id,note_id,user_id'];

    public function index()
    {
        // Urutan peran disusun di PHP, bukan FIELD() yang hanya ada di MySQL.
        $urutan = array_flip(['developer', 'admin', 'supervisor', 'mandor', 'operator', 'visitor']);

        $targets = User::where('id', '!=', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'department'])
            ->sortBy([
                fn ($a, $b) => ($urutan[$a->role] ?? 99) <=> ($urutan[$b->role] ?? 99),
                fn ($a, $b) => strcasecmp($a->name, $b->name),
            ])
            ->values();

        return Inertia::render('Notes/Index', [
            'listUrl'  => route('notes.list'),
            'storeUrl' => route('notes.store'),
            'baseUrl'  => url('/notes'),
            'targets'  => $targets->map(fn ($u) => [
                'value' => $u->id,
                'label' => $u->name . ' (' . $u->role . ($u->department ? ' - ' . $u->department : '') . ')',
            ])->values(),
        ]);
    }

    public function list()
    {
        $userId = auth()->id();

        $notes = Note::with(self::RELASI)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('target_user_id', $userId)
                  ->orWhere('is_broadcast', true);
            })
            ->orderByRaw('is_done ASC, CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC, due_date ASC, created_at DESC')
            ->limit(self::BATAS_DAFTAR)
            ->get();

        return response()->json($notes->map(fn (Note $n) => $this->bentuk($n, $userId))->values());
    }

    public function store(Request $request)
    {
        $request->validate($this->aturan());

        $photoPath = $this->simpanFoto($request);

        $isBroadcast  = $request->input('target_user_id') === 'all';
        $targetUserId = $isBroadcast ? null : ($request->input('target_user_id') ?: null);

        $note = Note::create([
            'user_id'        => auth()->id(),
            'title'          => $request->input('title'),
            'content'        => HtmlCatatan::bersihkan($request->input('content')),
            'due_date'       => $request->input('due_date'),
            'color'          => $request->input('color', 'blue'),
            'target_user_id' => $targetUserId,
            'is_broadcast'   => $isBroadcast,
            'photo_path'     => $photoPath,
        ]);

        return response()->json($this->bentuk($note->fresh()->load(self::RELASI), auth()->id()));
    }

    public function update(Request $request, Note $note)
    {
        $userId = auth()->id();

        $isOwner    = $note->user_id === $userId;
        $isReceiver = $note->target_user_id === $userId || $note->is_broadcast;

        if (!$isOwner && !$isReceiver) {
            abort(403);
        }

        // Penerima hanya bisa mengubah status selesai MILIKNYA sendiri.
        if (!$isOwner) {
            $this->setSelesai($note, $userId, $request->boolean('is_done'));

            return response()->json($this->bentuk($note->fresh()->load(self::RELASI), $userId));
        }

        $request->validate($this->aturan() + [
            'is_done'      => ['nullable', 'boolean'],
            'remove_photo' => ['nullable'],
        ]);

        $isBroadcast  = $request->input('target_user_id') === 'all';
        $targetUserId = $isBroadcast ? null : ($request->input('target_user_id') ?: null);

        $updateData = [
            'title'          => $request->input('title'),
            'content'        => HtmlCatatan::bersihkan($request->input('content')),
            'due_date'       => $request->input('due_date'),
            'color'          => $request->input('color', 'blue'),
            'target_user_id' => $targetUserId,
            'is_broadcast'   => $isBroadcast,
        ];

        // Status selesai hanya disentuh bila field-nya memang dikirim.
        $ubahSelesai = $request->has('is_done') ? $request->boolean('is_done') : null;

        if ($ubahSelesai !== null && ! $isBroadcast) {
            $updateData['is_done'] = $ubahSelesai;
            $updateData['done_at'] = $ubahSelesai ? now() : null;
        }

        // Foto: simpan yang baru dulu, hapus yang lama hanya bila berhasil.
        if ($request->hasFile('photo')) {
            $newPath = $this->simpanFoto($request);
            $this->hapusFoto($note->photo_path);
            $updateData['photo_path'] = $newPath;
        } elseif ($request->input('remove_photo')) {
            $this->hapusFoto($note->photo_path);
            $updateData['photo_path'] = null;
        }

        $note->update($updateData);

        // Catatan broadcast: status selesai milik pemilik pun per orang, supaya
        // centangnya tidak ikut mencoret catatan di layar penerima lain.
        if ($ubahSelesai !== null && $isBroadcast) {
            $this->setSelesai($note, $userId, $ubahSelesai);
        }

        return response()->json($this->bentuk($note->fresh()->load(self::RELASI), $userId));
    }

    public function destroy(Note $note)
    {
        abort_if($note->user_id !== auth()->id(), 403);

        $this->hapusFoto($note->photo_path);

        $note->delete();

        return response()->json(['ok' => true]);
    }

    // ── Pembantu ─────────────────────────────────────────────────────────────

    private function aturan(): array
    {
        return [
            'title'          => ['required', 'string', 'max:255'],
            // Isi berupa HTML dari editor: batas 5000 dihitung dari teksnya
            // (aturanPanjangIsi), 20000 hanya pagar ukuran mentah.
            'content'        => ['nullable', 'string', 'max:20000', $this->aturanPanjangIsi()],
            'due_date'       => ['nullable', 'date'],
            'color'          => ['nullable', 'string', 'in:blue,green,yellow,amber,red,purple,teal,slate'],
            'target_user_id' => ['nullable', function ($attr, $val, $fail) {
                if ($val === null || $val === '' || $val === 'all') {
                    return;
                }
                if (!is_numeric($val) || !User::where('id', $val)->exists()) {
                    $fail('User tujuan tidak valid.');
                }
            }],
            'photo'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * Batas panjang dihitung dari teksnya saja: menghitung HTML mentah-mentah
     * menghukum orang yang cuma memakai tombol rapikan (tebal, daftar, rata
     * tengah) padahal tulisannya pendek.
     */
    private function aturanPanjangIsi(): \Closure
    {
        return function (string $atribut, $nilai, \Closure $gagal) {
            if (is_string($nilai) && mb_strlen(HtmlCatatan::teks($nilai)) > 5000) {
                $gagal('Isi catatan maksimal 5000 karakter.');
            }
        };
    }

    /**
     * Simpan foto catatan lalu kecilkan di tempat. Foto HP mudah beberapa MB,
     * padahal di layar hanya pratinjau. Bila turunan gagal dibuat, yang asli
     * tetap dipakai supaya fotonya tidak hilang.
     */
    private function simpanFoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        $path = $request->file('photo')->store('notes/photos', 'public');
        abort_if($path === false, 500, 'Gagal menyimpan foto.');

        if ($kecil = ImageThumbnail::untuk($path, ImageThumbnail::LEBAR_FOTO)) {
            Storage::disk('public')->put($path, Storage::disk('public')->get($kecil));
            Storage::disk('public')->delete($kecil);
        }

        return $path;
    }

    /** Hapus foto beserta turunan kecilnya. */
    private function hapusFoto(?string $path): void
    {
        if (! $path) {
            return;
        }

        ImageThumbnail::hapusTurunan($path, ImageThumbnail::LEBAR_FOTO);
        ImageThumbnail::hapusTurunan($path);
        Storage::disk('public')->delete($path);
    }

    /** Ubah status selesai satu orang, sesuai jenis catatannya. */
    private function setSelesai(Note $note, int $userId, bool $selesai): void
    {
        if ($note->is_broadcast) {
            $note->tandaiSelesaiUntuk($userId, $selesai);

            return;
        }

        $note->update([
            'is_done' => $selesai,
            'done_at' => $selesai ? now() : null,
        ]);
    }

    /**
     * Data catatan untuk layar. `is_done` = status menurut orang yang membuka,
     * jadi tampilan tidak perlu tahu beda catatan personal & broadcast.
     */
    private function bentuk(Note $note, ?int $userId): array
    {
        $data = $note->toArray();
        $data['is_done'] = $note->selesaiUntuk($userId);

        // content_html: sudah disaring, untuk tampilan & mengisi editor.
        // content_text: teks polos, untuk cuplikan & pencarian. Catatan lama
        // yang masih teks biasa barisnya ikut terjaga lewat penyaring yang sama.
        $data['content_html'] = HtmlCatatan::tampil($note->content);
        $data['content_text'] = HtmlCatatan::teks($note->content);

        if ($note->is_broadcast) {
            $data['done_count'] = $note->completions->count();
        }

        unset($data['completions']);

        return $data;
    }
}
