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
            'shift1_qty'      => 10,
            'shift2_qty'      => 0,
            'total_qty'       => 10,
            'notes'           => 'UP NO.001-010',
        ])->assertRedirect();

        // Input kedua (BT) — notes mengandung baris yang sebagian sudah ada
        $this->actingAs($operator)->post('/production', [
            'product_id'      => $product->id,
            'production_date' => $date,
            'shift1_qty'      => 0,
            'shift2_qty'      => 10,
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
}
