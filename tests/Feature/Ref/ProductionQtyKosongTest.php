<?php

namespace Tests\Feature\Ref;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kolom UP/BT dikosongkan operator.
 *
 * Kolom up_qty/bt_qty di database NOT NULL DEFAULT 0, tapi
 * field form boleh kosong. Field kosong terkirim sebagai string kosong lalu
 * diubah jadi null oleh middleware, dan null itu sempat ikut ke INSERT sehingga
 * MySQL menolaknya ("Column 'up_qty' cannot be null") dan operator melihat
 * error 500. Paling sering kena produk channel, karena di sana nilai kosong
 * tidak pernah dinormalkan seperti pada produk non-channel.
 */
class ProductionQtyKosongTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::create([
            'name'      => 'Operator Kosong',
            'username'  => 'opkosong',
            'email'     => 'opkosong@uji.test',
            'role'      => 'operator',
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    private function product(string $type, string $categoryName): Product
    {
        $category = Category::create([
            'name'              => $categoryName,
            'is_active'         => true,
            'has_manual_serial' => false,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'type'        => $type,
            'name'        => strtoupper($type),
            'series'      => '26C0251099',
            'kva'         => '100',
            'is_active'   => true,
        ]);
    }

    public function test_channel_dengan_bt_kosong_tersimpan_sebagai_nol(): void
    {
        $operator = $this->operator();
        $product  = $this->product('channel', 'Channel-Uji');
        $date     = today()->toDateString();

        $response = $this->actingAs($operator)->post('/production', [
            'product_id'      => $product->id,
            'production_date' => $date,
            'up_qty'      => 8,
            'bt_qty'      => '',   // operator mengosongkan kolom BT
            'total_qty'       => 4,
            'notes'           => 'UP NO.001-008',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $log = ProductionLog::where('product_id', $product->id)->firstOrFail();

        $this->assertSame(8, $log->up_qty);
        $this->assertSame(0, $log->bt_qty, 'BT kosong harus tersimpan sebagai 0, bukan null.');
        $this->assertNotNull($log->bt_qty);
    }

    public function test_channel_dengan_up_dan_bt_kosong_tidak_menimbulkan_error(): void
    {
        $operator = $this->operator();
        $product  = $this->product('channel', 'Channel-Uji-2');

        $response = $this->actingAs($operator)->post('/production', [
            'product_id'      => $product->id,
            'production_date' => today()->toDateString(),
            'up_qty'      => '',
            'bt_qty'      => '',
            'total_qty'       => 0,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $log = ProductionLog::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(0, $log->up_qty);
        $this->assertSame(0, $log->bt_qty);
    }

    public function test_non_channel_dengan_up_bt_kosong_tetap_aman(): void
    {
        $operator = $this->operator();
        $product  = $this->product('regular', 'Tangki-Uji');

        $response = $this->actingAs($operator)->post('/production', [
            'product_id'      => $product->id,
            'production_date' => today()->toDateString(),
            'up_qty'          => '',
            'bt_qty'          => '',
            'total_qty'       => 5,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $log = ProductionLog::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(0, $log->up_qty);
        $this->assertSame(0, $log->bt_qty);
    }

    public function test_angka_nol_yang_diketik_operator_tidak_hilang(): void
    {
        // Penjaga regresi: normalisasi tidak boleh memperlakukan "0" sebagai kosong.
        $operator = $this->operator();
        $product  = $this->product('channel', 'Channel-Uji-3');

        $this->actingAs($operator)->post('/production', [
            'product_id'      => $product->id,
            'production_date' => today()->toDateString(),
            'up_qty'      => '0',
            'bt_qty'      => '6',
            'total_qty'       => 3,
        ])->assertRedirect();

        $log = ProductionLog::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(0, $log->up_qty);
        $this->assertSame(6, $log->bt_qty);
    }
}
