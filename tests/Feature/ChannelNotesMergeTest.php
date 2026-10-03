<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChannelNotesMergeTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::create([
            'name'      => 'Operator Channel',
            'username'  => 'opchannel',
            'email'     => 'opchannel@uji.test',
            'role'      => 'operator',
            'is_active' => true,
            'password'  => Hash::make('rahasia123'),
        ]);
    }

    private function channelProduct(): Product
    {
        $category = Category::create([
            'name'              => 'Channel-PLN',
            'is_active'         => true,
            'has_manual_serial' => false,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'type'        => 'channel',
            'name'        => 'CHANNEL',
            'series'      => '26C0251002',
            'kva'         => '100',
            'is_active'   => true,
        ]);
    }

    public function test_merge_channel_tidak_menduplikasi_baris_notes(): void
    {
        $operator = $this->operator();
        $product  = $this->channelProduct();
        $date     = today()->toDateString();

        // Input pertama (UP saja)
        $this->actingAs($operator)->post('/production', [
            'product_id'      => $product->id,
            'production_date' => $date,
            'up_qty'      => 10,
            'bt_qty'      => 0,
            'total_qty'       => 10,
            'notes'           => 'UP NO.001-010',
        ])->assertRedirect();

        // Input kedua (BT) — notes mengandung baris yang sebagian sudah ada
        $this->actingAs($operator)->post('/production', [
            'product_id'      => $product->id,
            'production_date' => $date,
            'up_qty'      => 0,
            'bt_qty'      => 10,
            'total_qty'       => 10,
            'notes'           => "UP NO.001-010\nBT NO.001-010",
        ])->assertRedirect();

        // Channel dengan produk+tanggal sama harus DI-MERGE jadi satu entri
        $logs = ProductionLog::where('product_id', $product->id)
            ->whereDate('production_date', $date)
            ->get();
        $this->assertCount(1, $logs, 'Channel seharusnya digabung menjadi satu entri.');

        $notes = $logs->first()->notes;

        // Baris "UP NO.001-010" hanya boleh muncul sekali (tidak duplikat)
        $this->assertSame(1, substr_count($notes, 'UP NO.001-010'));
        $this->assertStringContainsString('BT NO.001-010', $notes);
    }

    public function test_total_channel_setengah_dari_up_ditambah_bt_dan_reject_ikut_digabung(): void
    {
        $operator = $this->operator();
        $product  = $this->channelProduct();
        $date     = today()->toDateString();

        $this->actingAs($operator)->post('/production', [
            'product_id' => $product->id, 'production_date' => $date,
            'up_qty' => 3, 'bt_qty' => 2, 'total_qty' => 2.5, 'notes' => "UP NO.001-003\nBT NO.001-002",
            'reject_qty' => 1, 'reject_notes' => 'retak',
        ])->assertRedirect();

        $this->actingAs($operator)->post('/production', [
            'product_id' => $product->id, 'production_date' => $date,
            'up_qty' => '', 'bt_qty' => 1, 'total_qty' => 0.5, 'notes' => 'BT NO.003-003',
            'reject_qty' => 2, 'reject_notes' => 'bocor',
        ])->assertRedirect();

        $log = ProductionLog::where('product_id', $product->id)->sole();
        $this->assertEquals(3, (float) $log->total_qty);
        $this->assertSame(3, (int) $log->reject_qty);
        $this->assertSame('retak; bocor', $log->reject_notes);
        $this->assertStringContainsString('BT NO.001-003', $log->notes);
    }

    public function test_nomor_urut_dibuang_bila_jumlahnya_nol(): void
    {
        $operator = $this->operator();
        $product  = $this->channelProduct();

        $this->actingAs($operator)->post('/production', [
            'product_id' => $product->id, 'production_date' => today()->toDateString(),
            'up_qty' => 4, 'bt_qty' => 0, 'total_qty' => 2, 'notes' => "UP NO.001-004\nBT NO.001-004",
        ])->assertRedirect();

        $this->assertSame('UP NO.001-004', ProductionLog::sole()->notes);
    }
}
