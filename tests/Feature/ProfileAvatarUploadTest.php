<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        $u = new User(['name' => 'Op', 'username' => 'op', 'email' => 'op@uji.test', 'role' => 'operator', 'password' => Hash::make('x12345678')]);
        $u->forceFill(['is_active' => true])->save();

        return $u;
    }

    public function test_tombol_ganti_foto_menyimpan_berkas_dan_menghapus_yang_lama(): void
    {
        Storage::fake('public');
        $u = $this->operator();

        $this->actingAs($u)->post('/profile/avatar', ['photo' => UploadedFile::fake()->image('a.png', 300, 300)])
            ->assertRedirect()->assertSessionHasNoErrors();
        $lama = $u->fresh()->avatar;
        $this->assertStringStartsWith('avatars/', $lama);
        Storage::disk('public')->assertExists($lama);

        $this->post('/profile/avatar', ['photo' => UploadedFile::fake()->image('b.jpg', 300, 300)])->assertSessionHasNoErrors();
        $baru = $u->fresh()->avatar;
        $this->assertNotSame($lama, $baru);
        Storage::disk('public')->assertMissing($lama);
        Storage::disk('public')->assertExists($baru);
    }

    public function test_berkas_bukan_gambar_ditolak_dengan_pesan_jelas(): void
    {
        Storage::fake('public');
        $u = $this->operator();

        $this->actingAs($u)->post('/profile/avatar', ['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors(['photo' => 'File harus berupa gambar.']);
        $this->assertNull($u->fresh()->avatar);
    }

    public function test_url_avatar_tetap_didukung_seperti_acuan(): void
    {
        $u = $this->operator();

        $this->actingAs($u)->postJson('/profile/avatar', ['avatar' => 'https://api.dicebear.com/9.x/adventurer/svg?seed=Felix'])
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('https://api.dicebear.com/9.x/adventurer/svg?seed=Felix', $u->fresh()->avatar);
    }
}
