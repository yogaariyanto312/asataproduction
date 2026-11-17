<?php

namespace Tests\Feature\Ref;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Sisa-sisa "shift" sudah dibuang dari basis data.
 *
 * Angkanya sejak lama disebut UP dan BT di layar, tapi di basis data namanya
 * masih shift1_qty/shift2_qty, ditambah shift3_qty yang selalu nol, kolom enum
 * `shift` yang tidak pernah diisi, dan tabel `shifts` yang tidak dipakai
 * siapa-siapa. Berkas uji ini menjaga supaya tidak diam-diam kembali.
 */
class KolomUpBtTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::create([
            'name'      => 'Operator UpBt',
            'username'  => 'operatorupbt',
            'email'     => 'operatorupbt@uji.test',
            'role'      => 'operator',
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    private function produkChannel(): Product
    {
        $cat = Category::firstOrCreate(
            ['name' => 'Channel PLN'],
            ['is_active' => true, 'has_manual_serial' => false],
        );

        return Product::create([
            'category_id' => $cat->id,
            'type'        => 'channel',
            'name'        => 'CHANNEL UJI',
            'series'      => 'H-UJI',
            'kva'         => '100',
            'is_active'   => true,
        ]);
    }

    public function test_kolom_up_dan_bt_ada_di_basis_data(): void
    {
        $this->assertTrue(Schema::hasColumn('production_logs', 'up_qty'));
        $this->assertTrue(Schema::hasColumn('production_logs', 'bt_qty'));
    }

    public function test_kolom_shift_sudah_tidak_ada(): void
    {
        foreach (['shift1_qty', 'shift2_qty', 'shift3_qty', 'shift'] as $kolom) {
            $this->assertFalse(
                Schema::hasColumn('production_logs', $kolom),
                "Kolom {$kolom} muncul lagi di production_logs."
            );
        }
    }

    public function test_tabel_shifts_sudah_tidak_ada(): void
    {
        $this->assertFalse(Schema::hasTable('shifts'), 'Tabel shifts muncul lagi.');
    }

    public function test_input_produksi_memakai_nama_up_dan_bt(): void
    {
        $operator = $this->operator();
        $produk   = $this->produkChannel();

        $this->actingAs($operator)->post('/production', [
            'product_id'      => $produk->id,
            'production_date' => today()->toDateString(),
            'up_qty'          => 6,
            'bt_qty'          => 4,
            // Formnya selalu mengirim total; untuk channel nilainya dihitung
            // ulang di controller dari UP & BT.
            'total_qty'       => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $log = ProductionLog::firstOrFail();

        $this->assertSame(6, $log->up_qty);
        $this->assertSame(4, $log->bt_qty);
        // Channel: total = (UP + BT) / 2
        $this->assertSame(5.0, (float) $log->total_qty);
    }

    public function test_angka_lama_tetap_terbaca_setelah_kolom_diganti_nama(): void
    {
        $operator = $this->operator();
        $produk   = $this->produkChannel();

        $log = ProductionLog::create([
            'product_id'      => $produk->id,
            'user_id'         => $operator->id,
            'operator_name'   => $operator->name,
            'production_date' => today()->toDateString(),
            'up_qty'          => 10,
            'bt_qty'          => 8,
            'total_qty'       => 9,
            'reject_qty'      => 0,
        ]);

        $this->assertSame(10, $log->fresh()->up_qty);
        $this->assertSame(8, $log->fresh()->bt_qty);
    }

    public function test_layar_produksi_tidak_lagi_menyebut_shift(): void
    {
        $operator = $this->operator();
        $produk   = $this->produkChannel();

        ProductionLog::create([
            'product_id'      => $produk->id,
            'user_id'         => $operator->id,
            'operator_name'   => $operator->name,
            'production_date' => today()->toDateString(),
            'up_qty'          => 3,
            'bt_qty'          => 1,
            'total_qty'       => 2,
            'reject_qty'      => 0,
        ]);

        $html = $this->actingAs($operator)->get('/production')->assertOk()->getContent();

        // Ctrl+Shift dkk di kerangka halaman tidak dihitung — yang dicari
        // penyebutan kolomnya.
        $this->assertStringNotContainsString('shift1_qty', $html);
        $this->assertStringNotContainsString('shift2_qty', $html);
        $this->assertStringNotContainsString('shift3_qty', $html);
    }
}
