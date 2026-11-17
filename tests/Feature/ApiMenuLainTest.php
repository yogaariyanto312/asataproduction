<?php

namespace Tests\Feature;

use App\Models\Accessory;
use App\Models\Category;
use App\Models\Department;
use App\Models\Note;
use App\Models\Product;
use App\Models\Replacement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** API menu lain aplikasi Android: route 'jembatan' ke controller web. */
class ApiMenuLainTest extends TestCase
{
    use RefreshDatabase;

    protected Product $produk;

    protected function setUp(): void
    {
        parent::setUp();
        Department::create(['name' => 'QC-WELDING', 'is_active' => true]);
        Department::create(['name' => 'ASSEMBLY', 'is_active' => true]);
        $kat = Category::create(['name' => 'Tangki-PLN', 'is_active' => true]);
        $this->produk = Product::create(['category_id' => $kat->id, 'type' => 'regular', 'name' => 'TANGKI', 'series' => '26Q0001', 'kva' => '50', 'unit' => 'unit', 'is_active' => true]);
    }

    protected function akun(string $role, string $dept = 'QC-WELDING', ?string $username = null): User
    {
        $u = new User(['name' => ucfirst($username ?? $role), 'username' => $username ?? $role, 'email' => ($username ?? $role) . '@uji.test', 'role' => $role, 'department' => $dept, 'password' => Hash::make('rahasia123')]);
        $u->forceFill(['is_active' => true])->save();

        return $u;
    }

    protected function api(User $u): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($u->createToken('uji')->plainTextToken)->withHeader('Accept', 'application/json');
    }

    public function test_catatan_buat_lihat_selesai_hapus(): void
    {
        $op = $this->akun('operator');
        $mandor = $this->akun('mandor');

        $this->api($op)->getJson('/api/v1/catatan/opsi')->assertOk()
            ->assertJsonFragment(['value' => $mandor->id]);

        $id = $this->api($op)->postJson('/api/v1/catatan', ['title' => 'Cek las', 'content' => 'Isi <b>tebal</b>', 'color' => 'green', 'target_user_id' => $mandor->id])
            ->assertOk()->assertJsonPath('title', 'Cek las')->json('id');

        $this->api($mandor)->getJson('/api/v1/catatan')->assertOk()->assertJsonPath('0.id', $id)->assertJsonPath('0.content_text', 'Isi tebal');

        // Penerima hanya boleh mencentang selesai, judul tidak ikut berubah.
        $this->api($mandor)->putJson("/api/v1/catatan/$id", ['is_done' => true, 'title' => 'Diubah'])->assertOk()
            ->assertJsonPath('is_done', true)->assertJsonPath('title', 'Cek las');

        $this->api($mandor)->deleteJson("/api/v1/catatan/$id")->assertForbidden();
        $this->api($op)->postJson('/api/v1/catatan', ['title' => ''])->assertStatus(422)->assertJsonValidationErrors('title');
        $this->api($op)->deleteJson("/api/v1/catatan/$id")->assertOk();
        $this->assertSame(0, Note::count());
    }

    public function test_barang_pengganti_crud_lewat_jembatan(): void
    {
        $op = $this->akun('operator');

        $this->api($op)->postJson('/api/v1/pengganti', ['product_id' => $this->produk->id, 'replacement_date' => '2026-10-01', 'qty' => 2, 'original_serial' => '0001, 0002'])
            ->assertOk()->assertJson(['success' => true, 'message' => 'Data barang pengganti berhasil disimpan. (2 unit)']);

        $r = $this->api($op)->getJson('/api/v1/pengganti')->assertOk()->assertJsonPath('rows.data.0.qty', 2)
            ->assertJsonPath('rows.data.0.serials', ['0001', '0002'])->assertJsonPath('can.create', true);
        $id = $r->json('rows.data.0.id');

        $this->api($op)->patchJson("/api/v1/pengganti/$id/selesai")->assertOk()->assertJsonPath('message', 'Data barang pengganti ditandai selesai.');
        $this->assertNotNull(Replacement::find($id)->completed_at);

        $this->api($op)->postJson('/api/v1/pengganti', ['qty' => 0])->assertStatus(422)->assertJsonValidationErrors(['product_id', 'replacement_date', 'qty']);

        // Data departemen lain tidak terlihat & tidak bisa disentuh.
        $lain = $this->akun('operator', 'ASSEMBLY', 'op2');
        $this->api($lain)->getJson('/api/v1/pengganti')->assertOk()->assertJsonCount(0, 'rows.data');
        $this->api($lain)->deleteJson("/api/v1/pengganti/$id")->assertNotFound();

        $this->api($op)->deleteJson("/api/v1/pengganti/$id")->assertOk();
        $this->assertSame(0, Replacement::count());
    }

    public function test_aksesoris_crud_dan_izin(): void
    {
        $op = $this->akun('operator');

        $this->api($op)->postJson('/api/v1/aksesoris', ['accessory_date' => '2026-10-01', 'name' => 'BOX', 'serial_number' => 'NO.15', 'qty' => 1, 'unit' => 'Unit', 'product_id' => $this->produk->id])
            ->assertOk()->assertJsonPath('success', true);
        $a = Accessory::first();
        $this->assertSame('unit', $a->unit);

        $this->api($op)->getJson('/api/v1/aksesoris')->assertOk()->assertJsonPath('rows.data.0.name', 'BOX')
            ->assertJsonPath('lastSerials', ['BOX|26Q0001' => ['serial_number' => 'NO.15', 'date' => '1 Okt 2026', 'series' => '26Q0001', 'last_number' => 15]]);

        $this->api($op)->putJson("/api/v1/aksesoris/{$a->id}", ['accessory_date' => '2026-10-02', 'name' => 'BOX', 'qty' => 3])->assertOk();
        $this->assertSame(3, (int) $a->fresh()->qty);

        // Visitor tidak punya aksi tambah.
        $this->api($this->akun('visitor'))->postJson('/api/v1/aksesoris', ['accessory_date' => '2026-10-01', 'name' => 'X', 'qty' => 1])->assertForbidden();

        $this->api($op)->deleteJson("/api/v1/aksesoris/{$a->id}")->assertOk();
    }

    public function test_kategori_aksesoris_filter_ubah_gabung_hapus(): void
    {
        $op = $this->akun('operator');
        $simpan = fn ($nama) => $this->api($op)->postJson('/api/v1/aksesoris', ['accessory_date' => '2026-10-01', 'name' => $nama, 'qty' => 1])->assertOk();
        $simpan('Alumunium');
        $simpan('alumunium ');   // beda huruf/spasi → pakai kategori yang sama
        $simpan('BOX');
        $this->assertSame(['Alumunium', 'BOX'], \App\Models\AccessoryCategory::orderBy('name')->pluck('name')->all());
        $this->assertSame(2, Accessory::where('name', 'Alumunium')->count());

        // Filter kategori + daftar kategori dengan jumlah pemakaian.
        $res = $this->api($op)->getJson('/api/v1/aksesoris?category=BOX')->assertOk();
        $this->assertSame(['BOX'], array_column($res->json('rows.data'), 'name'));
        $this->assertSame([['Alumunium', 2], ['BOX', 1]], array_map(fn ($k) => [$k['name'], $k['count']], $res->json('categories')));
        $box = \App\Models\AccessoryCategory::where('name', 'BOX')->sole();
        $alu = \App\Models\AccessoryCategory::where('name', 'Alumunium')->sole();

        // Visitor tidak boleh mengubah/menghapus kategori.
        $this->api($this->akun('visitor'))->patchJson("/api/v1/aksesoris/kategori/{$box->id}", ['name' => 'X'])->assertForbidden();

        // Ubah nama: data ikut berganti.
        $this->api($op)->patchJson("/api/v1/aksesoris/kategori/{$box->id}", ['name' => 'Box Panel'])->assertOk();
        $this->assertSame(1, Accessory::where('name', 'Box Panel')->count());

        // Hapus kategori yang masih dipakai ditolak, data tetap utuh.
        $this->api($op)->deleteJson("/api/v1/aksesoris/kategori/{$alu->id}")->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->assertSame(2, Accessory::where('name', 'Alumunium')->count());

        // Gabung: ubah "Box Panel" menjadi "alumunium" → masuk ke kategori Alumunium yang sudah ada.
        $this->api($op)->patchJson("/api/v1/aksesoris/kategori/{$box->id}", ['name' => 'alumunium'])->assertOk();
        $this->assertSame(['Alumunium'], \App\Models\AccessoryCategory::pluck('name')->all());
        $this->assertSame(3, Accessory::where('name', 'Alumunium')->count());

        // Kategori tanpa data bisa dihapus.
        $kosong = \App\Models\AccessoryCategory::create(['name' => 'Tangga']);
        $this->api($op)->deleteJson("/api/v1/aksesoris/kategori/{$kosong->id}")->assertOk();
        $this->assertNull($kosong->fresh());
    }

    public function test_chat_kontak_kirim_baru_baca_hapus(): void
    {
        $op = $this->akun('operator');
        $mandor = $this->akun('mandor');
        $this->akun('visitor'); // operator tidak boleh menghubungi visitor

        $kontak = $this->api($op)->getJson('/api/v1/chat/kontak')->assertOk()->json();
        $this->assertSame(['mandor'], array_column($kontak, 'role'));

        // Developer melihat sesama developer (selain dirinya) & admin melihat sesama admin.
        $dev = $this->akun('developer');
        $this->akun('developer', 'QC-WELDING', 'developer2');
        $admin = $this->akun('admin');
        $this->akun('admin', 'QC-WELDING', 'admin2');
        $peranDev = array_column($this->api($dev)->getJson('/api/v1/chat/kontak')->assertOk()->json(), 'role');
        $this->assertSame(['developer', 'admin', 'admin', 'mandor', 'operator', 'visitor'], $peranDev);
        $peranAdmin = array_column($this->api($admin)->getJson('/api/v1/chat/kontak')->assertOk()->json(), 'role');
        $this->assertSame(['developer', 'developer', 'admin', 'mandor', 'operator', 'visitor'], $peranAdmin);

        $this->api($op)->postJson('/api/v1/chat', ['message' => 'Halo', 'recipient_id' => $mandor->id])->assertOk()->assertJson(['ok' => true]);
        $this->api($op)->postJson('/api/v1/chat', ['message' => ''])->assertStatus(422);

        $baru = $this->api($mandor)->getJson('/api/v1/chat/baru?last_id=0')->assertOk();
        $this->assertTrue($baru->json('has_new'));
        $this->assertSame('Halo', $baru->json('messages.0.message'));

        $this->api($mandor)->patchJson("/api/v1/chat/{$op->id}/baca")->assertOk()->assertJsonPath('updated', 1);
        $this->api($mandor)->patchJson('/api/v1/chat/ping')->assertOk();
        $this->assertNotNull($mandor->fresh()->last_seen_at);

        $this->api($op)->deleteJson("/api/v1/chat/{$mandor->id}")->assertOk();
        $this->assertSame([], $this->api($op)->getJson('/api/v1/chat')->assertOk()->json());
    }

    public function test_notifikasi_badge_chat_dan_pesan_baru(): void
    {
        $op = $this->akun('operator');
        $mandor = $this->akun('mandor');

        $this->api($op)->postJson('/api/v1/chat', ['message' => 'Cek gambar', 'recipient_id' => $mandor->id])->assertOk();
        $this->api($op)->postJson('/api/v1/chat', ['message' => 'Sudah?', 'recipient_id' => $mandor->id])->assertOk();

        $n = $this->api($mandor)->getJson('/api/v1/notifikasi?last_msg_id=0')->assertOk();
        $this->assertSame(2, $n->json('unread_count'));
        $this->assertSame(['Operator', 'Operator'], array_column($n->json('messages'), 'sender_name'));
        $last = $n->json('last_msg_id');

        // Patokan last_msg_id: pesan yang sudah dilihat tidak dikirim ulang.
        $this->assertSame([], $this->api($mandor)->getJson("/api/v1/notifikasi?last_msg_id={$last}")->json('messages'));

        // Pengirim tidak menghitung pesannya sendiri; setelah dibaca badge kembali 0.
        $this->assertSame(0, $this->api($op)->getJson('/api/v1/notifikasi')->json('unread_count'));
        $this->api($mandor)->patchJson("/api/v1/chat/{$op->id}/baca")->assertOk();
        $this->assertSame(0, $this->api($mandor)->getJson('/api/v1/notifikasi')->json('unread_count'));

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/notifikasi', ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_gambar_kerja_upload_lihat_berkas_hapus(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $admin = $this->akun('admin');
        $op = $this->akun('operator');

        $this->api($op)->post('/api/v1/gambar-kerja', ['judul' => 'X'])->assertForbidden();

        $this->api($admin)->post('/api/v1/gambar-kerja', [
            'judul' => 'Tangki 50', 'seri' => '26Q0001', 'kva' => '50',
            'files' => [\Illuminate\Http\UploadedFile::fake()->image('a.png', 40, 40)],
        ])->assertOk()->assertJsonPath('message', '1 file berhasil diupload.');

        $this->api($op)->getJson('/api/v1/gambar-kerja')->assertOk()->assertJsonPath('years.0.groups.0.judul', 'Tangki 50');
        $grup = $this->api($op)->getJson('/api/v1/gambar-kerja/grup?judul=Tangki+50&seri=26Q0001&kva=50')->assertOk()
            ->assertJsonPath('can.delete', false);
        $url = $grup->json('files.0.url');
        $path = substr($url, strpos($url, '/file/') + 6);

        $this->api($op)->get('/api/v1/file/' . $path)->assertOk();
        $this->api($this->akun('visitor'))->get('/api/v1/file/notes/x.png')->assertForbidden();

        $this->api($admin)->patchJson('/api/v1/gambar-kerja/grup/info', ['judul' => 'Tangki 50', 'seri' => '26Q0001', 'kva' => '50', 'judul_baru' => 'Tangki 50 Baru'])
            ->assertOk()->assertJsonPath('message', 'Perubahan gambar kerja berhasil disimpan.');
        $this->api($admin)->deleteJson('/api/v1/gambar-kerja/grup?judul=Tangki+50+Baru&seri=26Q0001&kva=50')->assertOk();
        $this->assertSame(0, \App\Models\GambarKerja::count());
    }

    public function test_keterangan_per_file_gambar_kerja(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $admin = $this->akun('admin');
        $op = $this->akun('operator');
        $this->api($admin)->post('/api/v1/gambar-kerja', [
            'judul' => 'Tangki 50', 'seri' => '26Q0001', 'kva' => '50',
            'files' => [\Illuminate\Http\UploadedFile::fake()->image('a.png', 40, 40), \Illuminate\Http\UploadedFile::fake()->image('b.png', 40, 40)],
        ])->assertOk();
        [$satu, $dua] = \App\Models\GambarKerja::orderBy('urutan')->get()->all();

        $this->api($op)->patchJson("/api/v1/gambar-kerja/{$dua->id}/catatan", ['catatan' => 'X'])->assertForbidden();
        $this->api($admin)->patchJson("/api/v1/gambar-kerja/{$dua->id}/catatan", ['catatan' => '  Revisi ke-2  '])
            ->assertOk()->assertJsonPath('message', 'Keterangan file berhasil disimpan.');

        $this->api($op)->getJson('/api/v1/gambar-kerja/grup?judul=Tangki+50&seri=26Q0001&kva=50')->assertOk()
            ->assertJsonPath('files.0.catatan', null)->assertJsonPath('files.1.catatan', 'Revisi ke-2');

        // Edit info grup tidak boleh menimpa keterangan per file.
        $this->api($admin)->patchJson('/api/v1/gambar-kerja/grup/info', ['judul' => 'Tangki 50', 'seri' => '26Q0001', 'kva' => '50', 'judul_baru' => 'Tangki 50', 'keterangan' => 'grup'])->assertOk();
        $this->assertSame('Revisi ke-2', $dua->fresh()->catatan_file);

        $this->api($admin)->patchJson("/api/v1/gambar-kerja/{$dua->id}/catatan", ['catatan' => ''])->assertOk();
        $this->assertNull($dua->fresh()->catatan_file);
        $this->assertNull($satu->fresh()->catatan_file);
    }

    public function test_master_produk_dan_kategori(): void
    {
        $admin = $this->akun('admin');
        $dev = $this->akun('developer');

        $this->api($admin)->getJson('/api/v1/produk')->assertOk()->assertJsonFragment(['name' => 'TANGKI']);
        $this->api($admin)->getJson('/api/v1/produk/form')->assertOk()->assertJsonPath('units.0', 'unit');

        $this->api($admin)->postJson('/api/v1/produk', ['category_id' => $this->produk->category_id, 'type' => 'regular', 'name' => 'TANGKI', 'series' => '26Q0002', 'kva' => '100', 'unit' => 'unit'])
            ->assertOk()->assertJsonPath('message', "Produk 'TANGKI' berhasil ditambahkan.");
        $baru = Product::where('series', '26Q0002')->first();

        $this->api($admin)->getJson("/api/v1/produk/{$baru->id}/edit")->assertOk()->assertJsonPath('product.series', '26Q0002');
        $this->api($admin)->patchJson("/api/v1/produk/{$baru->id}/aktif")->assertOk()->assertJsonPath('is_active', false);

        // Produk yang sudah punya data produksi tidak bisa dihapus → 422 + pesan web.
        \App\Models\ProductionLog::create(['user_id' => $admin->id, 'product_id' => $this->produk->id, 'production_date' => '2026-10-01', 'total_qty' => 1, 'operator_name' => 'A']);
        $this->api($admin)->deleteJson("/api/v1/produk/{$this->produk->id}")->assertStatus(422)
            ->assertJsonPath('message', "Produk 'TANGKI' tidak bisa dihapus karena sudah memiliki data produksi.");
        $this->api($admin)->deleteJson("/api/v1/produk/{$baru->id}")->assertOk();

        // Kategori bawaannya developer saja.
        $this->api($admin)->getJson('/api/v1/kategori')->assertForbidden();
        $this->api($dev)->postJson('/api/v1/kategori', ['name' => 'Cover', 'has_manual_serial' => true])->assertOk();
        $k = Category::where('name', 'Cover')->first();
        $this->assertTrue((bool) $k->has_manual_serial);
        $this->api($dev)->getJson('/api/v1/kategori')->assertOk()->assertJsonFragment(['name' => 'Cover']);
        $this->api($dev)->putJson("/api/v1/kategori/{$k->id}", ['name' => 'Cover-PLN'])->assertOk();
        $this->api($dev)->deleteJson("/api/v1/kategori/{$k->id}")->assertOk();
    }

    public function test_laporan_bulanan_harian_dan_export(): void
    {
        $mandor = $this->akun('mandor');
        \App\Models\ProductionLog::create(['user_id' => $mandor->id, 'product_id' => $this->produk->id, 'production_date' => now()->toDateString(), 'total_qty' => 4, 'operator_name' => 'M', 'notes' => '0001-0004', 'department' => 'QC-WELDING']);

        $this->api($mandor)->getJson('/api/v1/laporan')->assertOk()->assertJsonPath('summary.total', 4);
        $this->api($mandor)->getJson('/api/v1/laporan/harian')->assertOk()->assertJsonPath('logs.0.notes', '0001-0004');
        $this->assertStringContainsString('spreadsheetml', $this->api($mandor)->get('/api/v1/laporan/excel')->assertOk()->headers->get('content-type'));
        $this->api($this->akun('operator'))->getJson('/api/v1/laporan')->assertForbidden();
    }

    public function test_manajemen_mengikuti_batas_peran_web(): void
    {
        $admin = $this->akun('admin');
        $dev = $this->akun('developer');

        $this->api($admin)->getJson('/api/v1/manajemen?tab=operator')->assertOk()->assertJsonPath('tab', 'operator');
        $this->api($admin)->getJson('/api/v1/manajemen/operators/form')->assertOk()->assertJsonPath('resource.role', 'operator');

        $this->api($admin)->postJson('/api/v1/manajemen/operators', ['name' => 'Op Baru', 'username' => 'opbaru', 'password' => 'rahasia1', 'password_confirmation' => 'rahasia1', 'department' => 'QC-WELDING'])
            ->assertOk()->assertJsonPath('message', "Operator 'Op Baru' berhasil ditambahkan.");
        $op = User::where('username', 'opbaru')->first();

        $this->api($admin)->patchJson("/api/v1/manajemen/operators/{$op->id}/aktif")->assertOk();
        $this->assertFalse((bool) $op->fresh()->is_active);

        // Admin tidak boleh membuat admin/developer atau mengelola departemen.
        $this->api($admin)->postJson('/api/v1/manajemen/admins', ['name' => 'X'])->assertForbidden();
        $this->api($admin)->postJson('/api/v1/manajemen/departments', ['name' => 'X'])->assertForbidden();

        $this->api($dev)->postJson('/api/v1/manajemen/departments', ['name' => 'PAINTING'])->assertOk();
        $this->api($dev)->getJson('/api/v1/manajemen?tab=department')->assertOk()->assertJsonFragment(['name' => 'PAINTING']);

        $this->api($admin)->deleteJson("/api/v1/manajemen/operators/{$op->id}")->assertOk();
        $this->api($this->akun('operator', username: 'op9'))->getJson('/api/v1/manajemen')->assertForbidden();
    }

    public function test_hak_akses_settings_developer_saja(): void
    {
        $admin = $this->akun('admin');
        $dev = $this->akun('developer');

        $this->api($admin)->getJson('/api/v1/hak-akses')->assertForbidden();
        $this->api($admin)->getJson('/api/v1/settings')->assertForbidden();

        $this->api($dev)->getJson('/api/v1/hak-akses')->assertOk()->assertJsonPath('mode', 'role')
            ->assertJsonPath('state', fn ($s) => $s['operator|input-produksi'] === true);

        // Cabut izin Catatan dari operator lewat API → API catatan operator ikut tertutup.
        $state = $this->api($dev)->getJson('/api/v1/hak-akses')->json('state');
        $allowed = [];
        foreach ($state as $sel => $nilai) {
            [$role, $key] = explode('|', $sel);
            if ($nilai && ! str_starts_with($key, 'notes')) {
                $allowed[$role][$key] = '1';
            } elseif ($nilai && $role !== 'operator') {
                $allowed[$role][$key] = '1';
            }
        }
        $this->api($dev)->postJson('/api/v1/hak-akses', ['allowed' => $allowed])->assertOk()->assertJsonPath('success', true);
        $this->api($this->akun('operator'))->getJson('/api/v1/catatan')->assertForbidden();

        $this->api($dev)->getJson('/api/v1/settings')->assertOk()->assertJsonStructure(['setting' => ['maintenance_mode']]);
        $this->api($dev)->postJson('/api/v1/settings', ['reject_threshold' => 7])->assertOk();
        $this->assertEquals(7, \App\Models\BotSetting::instance()->fresh()->reject_threshold);
    }

    /** Rute lama DELETE target/{id} dulu menelan 'target/jadwal' → hapus foto jadwal dari aplikasi selalu 404. */
    public function test_hapus_foto_jadwal_dan_target_lewat_api(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $dev = $this->akun('developer');
        \Illuminate\Support\Facades\Storage::disk('public')->put('schedule-photos/2026-10-12/a.pdf', '%PDF');
        // Tanggal polos (kolom DATE di MySQL); create() lewat cast menulis '... 00:00:00' di SQLite.
        \Illuminate\Support\Facades\DB::table('schedule_photos')->insert(['target_date' => '2026-10-12', 'file_path' => 'schedule-photos/2026-10-12/a.pdf', 'uploaded_by' => $dev->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->api($dev)->deleteJson('/api/v1/target/jadwal', ['target_date' => '2026-10-12'])->assertOk();
        $this->assertSame(0, \App\Models\SchedulePhoto::count());
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing('schedule-photos/2026-10-12/a.pdf');

        // Hapus target per id (aplikasi Android lama) tetap jalan.
        $t = (new \App\Models\ProductionTarget)->forceFill(['product_id' => $this->produk->id, 'target_qty' => 10, 'target_date' => '2026-10-12', 'created_by' => $dev->id]);
        $t->save();
        $this->api($dev)->deleteJson('/api/v1/target/' . $t->id)->assertOk();
        $this->assertSame(0, \App\Models\ProductionTarget::count());
    }

    public function test_tutorial_dan_tentang(): void
    {
        $dev = $this->akun('developer');
        $op = $this->akun('operator');

        $this->api($op)->getJson('/api/v1/tutorial')->assertOk()->assertJsonPath('canEdit', false);
        $this->api($op)->postJson('/api/v1/tutorial', ['iframe_url' => 'https://x.test'])->assertForbidden();
        $this->api($dev)->postJson('/api/v1/tutorial', ['iframe_url' => 'https://www.youtube.com/embed/abc'])->assertOk();
        $this->api($op)->getJson('/api/v1/tutorial')->assertJsonPath('iframeUrl', 'https://www.youtube.com/embed/abc');

        $this->api($dev)->postJson('/api/v1/tentang/changelog', ['type' => 'feature', 'version' => '2.0', 'title' => 'Aplikasi Android'])->assertOk();
        $t = $this->api($op)->getJson('/api/v1/tentang')->assertOk()->assertJsonPath('changelogs.0.title', 'Aplikasi Android')
            ->assertJsonPath('developer.name', 'Developer');
        $this->api($op)->deleteJson('/api/v1/tentang/changelog/' . $t->json('changelogs.0.id'))->assertForbidden();
        $this->api($dev)->deleteJson('/api/v1/tentang/changelog/' . $t->json('changelogs.0.id'))->assertOk();
    }
}
