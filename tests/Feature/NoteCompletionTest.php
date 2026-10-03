<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Catatan broadcast punya banyak penerima: centang selesai satu orang tidak
 * boleh mencoret catatan itu di layar orang lain.
 */
class NoteCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $nama, string $role = 'operator'): User
    {
        $u = new User([
            'name' => $nama, 'username' => strtolower($nama), 'email' => strtolower($nama) . '@uji.test',
            'role' => $role, 'password' => Hash::make('rahasia123'),
        ]);
        $u->forceFill(['is_active' => true])->save();

        return $u;
    }

    /**
     * Ganti pengguna aktif. Sesi dikosongkan dulu: AuthenticateSession menyimpan
     * hash password pengguna sebelumnya di sesi dan akan mengeluarkan pengguna
     * baru (401) — perilaku yang benar di produksi, tapi bukan yang diuji di sini.
     */
    private function sebagai(User $u): static
    {
        $this->flushSession();

        return $this->actingAs($u);
    }

    private function statusUntuk(User $u, int $noteId): bool
    {
        $baris = collect($this->sebagai($u)->getJson('/notes/list')->assertOk()->json())
            ->firstWhere('id', $noteId);

        return (bool) $baris['is_done'];
    }

    public function test_selesai_broadcast_per_penerima(): void
    {
        $pemilik = $this->user('Pemilik', 'admin');
        $andi    = $this->user('Andi');
        $budi    = $this->user('Budi');

        $id = $this->sebagai($pemilik)->postJson('/notes', [
            'title' => 'Cek APD', 'target_user_id' => 'all',
        ])->assertOk()->json('id');

        // Andi menandai selesai
        $this->sebagai($andi)->putJson("/notes/{$id}", ['is_done' => 1])->assertOk();

        $this->assertTrue($this->statusUntuk($andi, $id));
        $this->assertFalse($this->statusUntuk($budi, $id), 'Centang Andi tidak boleh mencoret catatan Budi');
        $this->assertFalse($this->statusUntuk($pemilik, $id));

        // Pemilik melihat jumlah yang sudah selesai
        $baris = collect($this->sebagai($pemilik)->getJson('/notes/list')->json())->firstWhere('id', $id);
        $this->assertSame(1, $baris['done_count']);
        $this->assertArrayNotHasKey('completions', $baris);

        // Andi membatalkan
        $this->sebagai($andi)->putJson("/notes/{$id}", ['is_done' => 0])->assertOk();
        $this->assertFalse($this->statusUntuk($andi, $id));
    }

    public function test_catatan_personal_tetap_memakai_kolom_is_done(): void
    {
        $pemilik = $this->user('Pemilik', 'admin');
        $andi    = $this->user('Andi');

        $id = $this->sebagai($pemilik)->postJson('/notes', [
            'title' => 'Untuk Andi', 'target_user_id' => $andi->id,
        ])->json('id');

        $this->sebagai($andi)->putJson("/notes/{$id}", ['is_done' => 1])->assertOk();

        $this->assertTrue((bool) Note::find($id)->is_done);
        $this->assertTrue($this->statusUntuk($pemilik, $id));
    }

    public function test_orang_lain_tidak_bisa_mengubah_catatan_pribadi(): void
    {
        $pemilik = $this->user('Pemilik', 'admin');
        $andi    = $this->user('Andi');
        $budi    = $this->user('Budi');

        $id = $this->sebagai($pemilik)->postJson('/notes', [
            'title' => 'Untuk Andi', 'target_user_id' => $andi->id,
        ])->json('id');

        $this->sebagai($budi)->putJson("/notes/{$id}", ['is_done' => 1])->assertForbidden();
        $this->assertNotContains($id, collect($this->sebagai($budi)->getJson('/notes/list')->json())->pluck('id'));
    }

    public function test_foto_catatan_disajikan_lewat_route_berlogin(): void
    {
        Storage::fake('public');
        $pemilik = $this->user('Pemilik', 'admin');

        $url = $this->sebagai($pemilik)->post('/notes', [
            'title' => 'Ada foto',
            'photo' => UploadedFile::fake()->image('foto.jpg', 3000, 2000),
        ], ['Accept' => 'application/json'])->assertOk()->json('photo_url');

        $this->assertStringContainsString('/file/notes/photos/', $url);
        $this->assertStringNotContainsString('/storage/', $url);

        // Foto besar dikecilkan (lebar maks 1280 px)
        $path = Note::sole()->photo_path;
        [$lebar] = getimagesize(Storage::disk('public')->path($path));
        $this->assertLessThanOrEqual(1280, $lebar);
    }
}
