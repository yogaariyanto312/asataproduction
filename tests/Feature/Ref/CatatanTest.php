<?php

namespace Tests\Feature\Ref;

use App\Models\Note;
use App\Models\NoteCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Menu Catatan: siapa boleh melihat/mengubah apa, catatan untuk semua user,
 * foto, dan penyaringan daftar.
 */
class CatatanTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $nama, string $role = 'operator'): User
    {
        return User::factory()->create(['name' => $nama, 'role' => $role, 'is_active' => true]);
    }

    private function isiValid(array $ganti = []): array
    {
        return array_merge(['title' => 'Cek panel', 'content' => 'tolong dicek'], $ganti);
    }

    private function gambar(int $w = 2400, int $h = 1600): string
    {
        $im = imagecreatetruecolor($w, $h);
        for ($x = 0; $x < $w; $x += 8) {
            imagefilledrectangle($im, $x, 0, $x + 8, $h, imagecolorallocate($im, $x % 255, ($x / 9) % 255, 90));
        }
        ob_start();
        imagejpeg($im, null, 92);
        $biner = ob_get_clean();
        imagedestroy($im);

        return $biner;
    }

    /** Ambil daftar catatan seperti yang dilihat seorang user. */
    private function daftar(User $user): array
    {
        return $this->actingAs($user)->getJson(route('notes.list'))->assertOk()->json();
    }

    // ── Membuat ───────────────────────────────────────────────────────────────

    public function test_membuat_catatan_pribadi(): void
    {
        $pembuat = $this->user('Pembuat');

        $this->actingAs($pembuat)
            ->postJson(route('notes.store'), $this->isiValid())
            ->assertOk()
            ->assertJsonPath('title', 'Cek panel');

        $this->assertSame($pembuat->id, Note::firstOrFail()->user_id);
    }

    public function test_judul_wajib_dan_isi_dibatasi(): void
    {
        $user = $this->user('A');

        $this->actingAs($user)->postJson(route('notes.store'), ['title' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('title');

        $this->actingAs($user)->postJson(route('notes.store'), $this->isiValid(['content' => str_repeat('a', 5001)]))
            ->assertStatus(422)->assertJsonValidationErrors('content');

        $this->assertSame(0, Note::count());
    }

    public function test_tujuan_harus_user_yang_ada(): void
    {
        $this->actingAs($this->user('A'))
            ->postJson(route('notes.store'), $this->isiValid(['target_user_id' => 999999]))
            ->assertStatus(422)->assertJsonValidationErrors('target_user_id');
    }

    public function test_warna_di_luar_pilihan_ditolak(): void
    {
        $this->actingAs($this->user('A'))
            ->postJson(route('notes.store'), $this->isiValid(['color' => 'pink']))
            ->assertStatus(422)->assertJsonValidationErrors('color');
    }

    // ── Siapa melihat apa ─────────────────────────────────────────────────────

    public function test_catatan_pribadi_tidak_terlihat_orang_lain(): void
    {
        $pembuat = $this->user('Pembuat');
        $tujuan  = $this->user('Tujuan');
        $orang3  = $this->user('Orang Lain');

        $this->actingAs($pembuat)->postJson(route('notes.store'),
            $this->isiValid(['title' => 'Rahasia', 'target_user_id' => $tujuan->id]))->assertOk();

        $this->assertSame(['Rahasia'], array_column($this->daftar($pembuat), 'title'));
        $this->assertSame(['Rahasia'], array_column($this->daftar($tujuan), 'title'));
        $this->assertSame([], array_column($this->daftar($orang3), 'title'),
            'Catatan untuk orang tertentu tidak boleh bocor ke user lain.');
    }

    public function test_catatan_untuk_semua_user_terlihat_semua_orang(): void
    {
        $pembuat = $this->user('Pembuat');
        $lain    = $this->user('Lain');

        $this->actingAs($pembuat)->postJson(route('notes.store'),
            $this->isiValid(['title' => 'Pengumuman', 'target_user_id' => 'all']))->assertOk();

        $this->assertTrue(Note::firstOrFail()->is_broadcast);
        $this->assertSame(['Pengumuman'], array_column($this->daftar($lain), 'title'));
    }

    // ── Status selesai ────────────────────────────────────────────────────────

    public function test_selesai_pada_catatan_semua_user_hanya_berlaku_untuk_yang_mencentang(): void
    {
        $pembuat = $this->user('Pembuat');
        $andi    = $this->user('Andi');
        $budi    = $this->user('Budi');

        $this->actingAs($pembuat)->postJson(route('notes.store'),
            $this->isiValid(['title' => 'Pengumuman', 'target_user_id' => 'all']))->assertOk();
        $note = Note::firstOrFail();

        $this->actingAs($andi)->putJson(route('notes.update', $note), ['is_done' => true])->assertOk();

        $this->assertTrue($this->daftar($andi)[0]['is_done'], 'Andi sudah mencentang, harusnya selesai untuknya.');
        $this->assertFalse($this->daftar($budi)[0]['is_done'],
            'Centang Andi tidak boleh ikut mencoret catatan di layar Budi.');
        $this->assertFalse((bool) $note->fresh()->is_done, 'Kolom bersama tidak boleh ikut berubah.');
        $this->assertSame(1, NoteCompletion::count());
    }

    public function test_batal_centang_mengembalikan_status(): void
    {
        $pembuat = $this->user('Pembuat');
        $andi    = $this->user('Andi');

        $this->actingAs($pembuat)->postJson(route('notes.store'),
            $this->isiValid(['target_user_id' => 'all']))->assertOk();
        $note = Note::firstOrFail();

        $this->actingAs($andi)->putJson(route('notes.update', $note), ['is_done' => true])->assertOk();
        $this->actingAs($andi)->putJson(route('notes.update', $note), ['is_done' => false])->assertOk();

        $this->assertFalse($this->daftar($andi)[0]['is_done']);
        $this->assertSame(0, NoteCompletion::count(), 'Penanda selesai harus ikut terhapus.');
    }

    public function test_pemilik_melihat_berapa_orang_yang_sudah_selesai(): void
    {
        $pembuat = $this->user('Pembuat');
        $andi    = $this->user('Andi');
        $budi    = $this->user('Budi');

        $this->actingAs($pembuat)->postJson(route('notes.store'),
            $this->isiValid(['target_user_id' => 'all']))->assertOk();
        $note = Note::firstOrFail();

        $this->actingAs($andi)->putJson(route('notes.update', $note), ['is_done' => true])->assertOk();
        $this->actingAs($budi)->putJson(route('notes.update', $note), ['is_done' => true])->assertOk();

        $this->assertSame(2, $this->daftar($pembuat)[0]['done_count']);
    }

    public function test_selesai_pada_catatan_pribadi_memakai_kolom_lama(): void
    {
        $pembuat = $this->user('Pembuat');
        $tujuan  = $this->user('Tujuan');

        $this->actingAs($pembuat)->postJson(route('notes.store'),
            $this->isiValid(['target_user_id' => $tujuan->id]))->assertOk();
        $note = Note::firstOrFail();

        $this->actingAs($tujuan)->putJson(route('notes.update', $note), ['is_done' => true])->assertOk();

        $this->assertTrue((bool) $note->fresh()->is_done);
        $this->assertNotNull($note->fresh()->done_at);
        $this->assertTrue($this->daftar($pembuat)[0]['is_done'], 'Pembuat ikut melihat catatannya sudah dikerjakan.');
        $this->assertSame(0, NoteCompletion::count());
    }

    // ── Hak akses ─────────────────────────────────────────────────────────────

    public function test_orang_luar_tidak_bisa_mengubah_catatan(): void
    {
        $pembuat = $this->user('Pembuat');
        $tujuan  = $this->user('Tujuan');
        $orang3  = $this->user('Orang Lain');

        $this->actingAs($pembuat)->postJson(route('notes.store'),
            $this->isiValid(['target_user_id' => $tujuan->id]))->assertOk();
        $note = Note::firstOrFail();

        $this->actingAs($orang3)->putJson(route('notes.update', $note), ['is_done' => true])->assertForbidden();
        $this->actingAs($orang3)->deleteJson(route('notes.destroy', $note))->assertForbidden();
    }

    public function test_penerima_tidak_bisa_mengubah_isi_catatan(): void
    {
        $pembuat = $this->user('Pembuat');
        $tujuan  = $this->user('Tujuan');

        $this->actingAs($pembuat)->postJson(route('notes.store'),
            $this->isiValid(['title' => 'Asli', 'target_user_id' => $tujuan->id]))->assertOk();
        $note = Note::firstOrFail();

        $this->actingAs($tujuan)->putJson(route('notes.update', $note), [
            'title' => 'Diubah penerima', 'content' => 'diubah', 'is_done' => true,
        ])->assertOk();

        $this->assertSame('Asli', $note->fresh()->title, 'Penerima hanya boleh mengubah status selesai.');
    }

    public function test_penerima_tidak_bisa_menghapus_catatan(): void
    {
        $pembuat = $this->user('Pembuat');
        $tujuan  = $this->user('Tujuan');

        $this->actingAs($pembuat)->postJson(route('notes.store'),
            $this->isiValid(['target_user_id' => $tujuan->id]))->assertOk();
        $note = Note::firstOrFail();

        $this->actingAs($tujuan)->deleteJson(route('notes.destroy', $note))->assertForbidden();
        $this->assertSame(1, Note::count());
    }

    public function test_pemilik_bisa_mengubah_dan_menghapus(): void
    {
        $pembuat = $this->user('Pembuat');

        $this->actingAs($pembuat)->postJson(route('notes.store'), $this->isiValid())->assertOk();
        $note = Note::firstOrFail();

        $this->actingAs($pembuat)->putJson(route('notes.update', $note),
            $this->isiValid(['title' => 'Judul baru']))->assertOk();
        $this->assertSame('Judul baru', $note->fresh()->title);

        $this->actingAs($pembuat)->deleteJson(route('notes.destroy', $note))->assertOk();
        $this->assertSame(0, Note::count());
    }

    public function test_tamu_ditolak(): void
    {
        $this->getJson(route('notes.list'))->assertUnauthorized();
        $this->postJson(route('notes.store'), $this->isiValid())->assertUnauthorized();
    }

    // ── Foto ──────────────────────────────────────────────────────────────────

    public function test_foto_dikecilkan_saat_diunggah(): void
    {
        Storage::fake('public');
        $user = $this->user('A');

        $berkas = UploadedFile::fake()->createWithContent('foto.jpg', $this->gambar());
        $asal   = $berkas->getSize();

        $this->actingAs($user)->post(route('notes.store'),
            $this->isiValid(['photo' => $berkas]))->assertOk();

        $path = Note::firstOrFail()->photo_path;
        $this->assertNotNull($path);

        $tersimpan = Storage::disk('public')->size($path);
        [$w] = getimagesizefromstring(Storage::disk('public')->get($path));
        fwrite(STDERR, sprintf("foto catatan: %d KB (2400px) -> %d KB (%dpx)\n", $asal / 1024, $tersimpan / 1024, $w));

        $this->assertSame(\App\Support\ImageThumbnail::LEBAR_FOTO, $w);
        $this->assertLessThan($asal, $tersimpan);
    }

    public function test_foto_terhapus_bersama_catatannya(): void
    {
        Storage::fake('public');
        $user = $this->user('A');

        $this->actingAs($user)->post(route('notes.store'), $this->isiValid([
            'photo' => UploadedFile::fake()->createWithContent('foto.jpg', $this->gambar(600, 400)),
        ]))->assertOk();

        $note = Note::firstOrFail();
        $path = $note->photo_path;
        $this->assertTrue(Storage::disk('public')->exists($path));

        $this->actingAs($user)->deleteJson(route('notes.destroy', $note))->assertOk();
        $this->assertFalse(Storage::disk('public')->exists($path), 'Foto tidak boleh tertinggal di disk.');
    }

    public function test_alamat_foto_lewat_route_ber_login(): void
    {
        Storage::fake('public');
        $user = $this->user('A');

        $this->actingAs($user)->post(route('notes.store'), $this->isiValid([
            'photo' => UploadedFile::fake()->createWithContent('foto.jpg', $this->gambar(600, 400)),
        ]))->assertOk();

        $url = Note::firstOrFail()->photo_url;

        $this->assertStringContainsString('/file/', $url,
            'Foto harus disajikan lewat route ber-middleware auth, bukan URL storage publik.');
        $this->assertStringNotContainsString('/storage/', $url);

        // tanpa login: ditolak
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_berkas_bukan_gambar_ditolak(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user('A'))->post(route('notes.store'), $this->isiValid([
            'photo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('photo');
    }

    // ── Daftar ────────────────────────────────────────────────────────────────

    public function test_daftar_menaruh_yang_belum_selesai_di_atas(): void
    {
        $user = $this->user('A');

        $this->actingAs($user)->postJson(route('notes.store'), $this->isiValid(['title' => 'Sudah']))->assertOk();
        $selesai = Note::firstOrFail();
        $this->actingAs($user)->putJson(route('notes.update', $selesai),
            $this->isiValid(['title' => 'Sudah', 'is_done' => true]))->assertOk();

        $this->actingAs($user)->postJson(route('notes.store'), $this->isiValid(['title' => 'Belum']))->assertOk();

        $this->assertSame(['Belum', 'Sudah'], array_column($this->daftar($user), 'title'));
    }

    public function test_halaman_catatan_terbuka_dan_daftar_tujuan_terurut_peran(): void
    {
        $this->user('Zulkifli', 'developer');
        $this->user('Andi', 'operator');
        $pembuka = $this->user('Pembuka', 'admin');

        $targets = $this->namaTujuan($pembuka);

        $this->assertSame(['Zulkifli', 'Andi'], $targets,
            'Developer harus di atas operator, lepas dari urutan abjad.');
    }

    public function test_user_nonaktif_tidak_muncul_sebagai_tujuan(): void
    {
        User::factory()->create(['name' => 'Nonaktif', 'role' => 'operator', 'is_active' => false]);
        $pembuka = $this->user('Pembuka', 'admin');

        $targets = $this->namaTujuan($pembuka);

        $this->assertNotContains('Nonaktif', $targets);
        $this->assertNotContains('Pembuka', $targets, 'Diri sendiri tidak masuk daftar tujuan.');
    }

    /** Nama di dropdown tujuan (label asata: "Nama (peran - departemen)"). */
    private function namaTujuan(User $pembuka): array
    {
        $targets = $this->actingAs($pembuka)->get(route('notes.index'))->assertOk()
            ->viewData('page')['props']['targets'];

        return array_map(fn ($t) => preg_replace('/ \(.*$/', '', $t['label']), $targets);
    }

    // ── Tampilan modal ────────────────────────────────────────────────────────

    public function test_pratinjau_foto_di_modal_tampil_besar_dan_utuh(): void
    {
        // asata: halaman Inertia — yang diperiksa JSX + CSS-nya.
        $jsx = file_get_contents(resource_path('js/Pages/Notes/Index.jsx'));
        $css = file_get_contents(resource_path('css/asata-ui.css'));

        // Pilihan Yoga (2026-09-19): foto bukti ditampilkan besar mengikuti rasio
        // aslinya, bukan dikecilkan — lebih enak diperiksa langsung di modal.
        $this->assertStringContainsString('<div className="au-nt-foto">', $jsx);
        $this->assertStringContainsString('.au-nt-foto img { display: block; width: 100%; height: auto; }', $css,
            'Foto bukti harus tampil penuh selebar modal mengikuti rasio aslinya.');
        $this->assertDoesNotMatchRegularExpression('/\.au-nt-foto img \{[^}]*max-height/', $css,
            'Pratinjau foto sengaja tidak dibatasi tingginya.');
        $this->assertMatchesRegularExpression('/\.au-nt-modal \{[^}]*max-height: 90vh/', $css,
            'Modal tetap dibatasi tinggi layar supaya tombolnya bisa dijangkau lewat scroll.');
    }

    /** Permintaan Yoga 2026-09-30: di PC modal lebih lebar supaya enak mengetik. */
    public function test_modal_lebih_lebar_di_pc_dan_kolom_tulis_lebih_luas(): void
    {
        $css = file_get_contents(resource_path('css/asata-ui.css'));

        $this->assertMatchesRegularExpression('/\.au-nt-modal \{[^}]*max-width: 672px/', $css, 'HP tetap 672px (max-w-2xl).');
        $this->assertStringContainsString('@media (min-width: 1024px) { .au-nt-modal { max-width: 1024px; } }', $css,
            'PC dilebarkan (lg:max-w-5xl).');
        $this->assertStringContainsString('.au-nt-kolom { grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);', $css,
            'Di PC kolom tulis (kanan) dua kali lebar kolom isian.');
        $this->assertStringContainsString('.au-nt-editor { min-height: 288px; max-height: 50vh;', $css,
            'Area ketik di PC mengikuti tinggi layar.');
    }

    public function test_data_yang_dikirim_ke_layar_lengkap(): void
    {
        $pembuat = $this->user('Pembuat');
        $tujuan  = $this->user('Tujuan');

        $this->actingAs($pembuat)->postJson(route('notes.store'),
            $this->isiValid(['target_user_id' => $tujuan->id, 'due_date' => '2026-10-01']))->assertOk();

        $note = $this->daftar($pembuat)[0];

        foreach (['id', 'title', 'content', 'due_date', 'color', 'is_done', 'is_broadcast',
                  'user_id', 'target_user_id', 'photo_url', 'user', 'target_user'] as $kunci) {
            $this->assertArrayHasKey($kunci, $note, "Field '{$kunci}' dibutuhkan tampilan.");
        }

        $this->assertSame('Pembuat', $note['user']['name']);
        $this->assertSame('Tujuan', $note['target_user']['name']);

        // asata: tanggal polos, bukan tengah malam UTC — "2026-09-30T17:00:00Z"
        // membuat deadline di layar mundur sehari (zona Asia/Jakarta).
        $this->assertSame('2026-10-01', $note['due_date']);
    }

    /* ── Isi catatan berformat (toolbar perapi tulisan) ─────────────────── */

    public function test_format_dari_toolbar_tersimpan(): void
    {
        $user = $this->user('A');

        $this->actingAs($user)->postJson(route('notes.store'), $this->isiValid([
            'content' => '<p><strong>Cek oli</strong></p><ul><li>panel A</li><li>panel B</li></ul>',
        ]))->assertOk();

        $isi = Note::firstOrFail()->content;

        $this->assertStringContainsString('<strong>Cek oli</strong>', $isi);
        $this->assertStringContainsString('<li>panel A</li>', $isi);
    }

    public function test_perataan_paragraf_tersimpan(): void
    {
        $user = $this->user('A');

        $this->actingAs($user)->postJson(route('notes.store'), $this->isiValid([
            'content' => '<p style="text-align:center">Tengah</p>',
        ]))->assertOk();

        $this->assertStringContainsString('text-align:center', Note::firstOrFail()->content);
    }

    public function test_skrip_di_isi_catatan_tidak_ikut_tersimpan(): void
    {
        $user = $this->user('A');

        $this->actingAs($user)->postJson(route('notes.store'), $this->isiValid([
            'content' => '<p onclick="curi()">Halo</p><script>alert(1)</script><img src=x onerror=alert(1)>',
        ]))->assertOk();

        $isi = (string) Note::firstOrFail()->content;

        $this->assertStringNotContainsString('script', strtolower($isi));
        $this->assertStringNotContainsString('onclick', $isi);
        $this->assertStringNotContainsString('onerror', $isi);
        $this->assertStringContainsString('Halo', $isi);
    }

    public function test_isi_disaring_juga_saat_catatan_diubah(): void
    {
        $user = $this->user('A');
        $note = Note::create(['user_id' => $user->id, 'title' => 'Awal', 'content' => 'lama']);

        $this->actingAs($user)->putJson(route('notes.update', $note), [
            'title'   => 'Awal',
            'content' => '<p>Baru</p><script>alert(1)</script>',
        ])->assertOk();

        $this->assertStringNotContainsString('alert(1)', (string) $note->fresh()->content);
        $this->assertStringContainsString('Baru', (string) $note->fresh()->content);
    }

    public function test_batas_panjang_dihitung_dari_teksnya_bukan_penanda_html(): void
    {
        $user = $this->user('A');

        // Tulisan pendek tapi banyak penanda format: harus tetap diterima.
        $isi = str_repeat('<p><strong>oke</strong></p>', 200);

        $this->actingAs($user)->postJson(route('notes.store'), $this->isiValid(['content' => $isi]))
            ->assertOk();

        // Teks yang benar-benar kepanjangan tetap ditolak.
        $this->actingAs($user)->postJson(route('notes.store'), $this->isiValid([
            'content' => '<p>' . str_repeat('a', 5001) . '</p>',
        ]))->assertStatus(422)->assertJsonValidationErrors('content');
    }

    public function test_layar_menerima_isi_siap_tampil_dan_teks_polosnya(): void
    {
        $user = $this->user('A');

        $this->actingAs($user)->postJson(route('notes.store'), $this->isiValid([
            'content' => '<ul><li>satu</li><li>dua</li></ul>',
        ]))->assertOk()
            ->assertJsonPath('content_text', 'satu dua')
            ->assertJsonPath('content_html', '<ul><li>satu</li><li>dua</li></ul>');
    }

    public function test_catatan_lama_berupa_teks_biasa_tetap_terbaca_barisnya(): void
    {
        $user = $this->user('A');
        Note::create(['user_id' => $user->id, 'title' => 'Lama', 'content' => "baris satu
baris dua"]);

        $daftar = $this->daftar($user);

        $this->assertStringContainsString('<br', $daftar[0]['content_html'],
            'Catatan lama harus tetap tampil dengan barisnya.');
        $this->assertSame('baris satu baris dua', $daftar[0]['content_text']);
    }

    public function test_query_daftar_tidak_bertambah_mengikuti_jumlah_catatan(): void
    {
        $user = $this->user('A');
        $lain = $this->user('B');

        $buat = function (int $n) use ($user) {
            for ($i = 0; $i < $n; $i++) {
                Note::create(['user_id' => $user->id, 'title' => 'N' . $i, 'is_broadcast' => true]);
            }
        };

        $hitung = function () use ($lain) {
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            $this->actingAs($lain)->getJson(route('notes.list'))->assertOk();
            $n = count(\DB::getQueryLog());
            \DB::disableQueryLog();

            return $n;
        };

        $buat(3);
        $sedikit = $hitung();
        $buat(40);
        $banyak = $hitung();

        fwrite(STDERR, "query daftar catatan: 3 -> {$sedikit}, 43 -> {$banyak}\n");
        $this->assertLessThanOrEqual($sedikit, $banyak, 'Daftar catatan tidak boleh N+1.');
    }
}
