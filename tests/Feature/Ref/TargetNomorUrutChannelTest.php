<?php

namespace Tests\Feature\Ref;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Target "Sampai No. Urut" untuk produk channel: API memberi kumulatif UP dan BT terpisah. */
class TargetNomorUrutChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_actual_qty_channel_memberi_kumulatif_up_dan_bt(): void
    {
        $dev = User::create(['name' => 'Dev', 'username' => 'dev', 'email' => 'dev@uji.test', 'role' => 'developer', 'is_active' => true, 'password' => Hash::make('rahasia123')]);
        $kat = Category::create(['name' => 'Channel Uji', 'is_active' => true, 'has_manual_serial' => false]);
        $p = Product::create(['category_id' => $kat->id, 'type' => 'channel', 'name' => 'CHANNEL', 'series' => '26C0001', 'kva' => '100', 'is_active' => true]);

        foreach ([[10, 6, 8], [4, 4, 4]] as $i => [$up, $bt, $total]) {
            ProductionLog::create([
                'product_id' => $p->id, 'user_id' => $dev->id, 'operator_name' => 'Dev', 'production_date' => now()->subDays($i)->toDateString(),
                'up_qty' => $up, 'bt_qty' => $bt, 'total_qty' => $total, 'reject_qty' => 0,
            ]);
        }

        $this->actingAs($dev)->getJson(route('api.targets.actual-qty', ['product_id' => $p->id]))
            ->assertOk()
            ->assertJsonPath('up', 14)
            ->assertJsonPath('bt', 10)
            ->assertJsonPath('actual', 12);
    }

    public function test_target_channel_disimpan_terpisah_dan_progres_per_deret(): void
    {
        $dev = User::create(['name' => 'Dev', 'username' => 'dev2', 'email' => 'dev2@uji.test', 'role' => 'developer', 'is_active' => true, 'password' => Hash::make('rahasia123')]);
        $kat = Category::create(['name' => 'Channel Uji', 'is_active' => true, 'has_manual_serial' => false]);
        $p = Product::create(['category_id' => $kat->id, 'type' => 'channel', 'name' => 'CHANNEL', 'series' => '26C0002', 'kva' => '100', 'is_active' => true]);

        // riwayat lama (jadi baseline): UP 20, BT 10
        ProductionLog::create(['product_id' => $p->id, 'user_id' => $dev->id, 'operator_name' => 'Dev', 'production_date' => today()->subDay()->toDateString(), 'up_qty' => 20, 'bt_qty' => 10, 'total_qty' => 15, 'reject_qty' => 0]);

        $this->actingAs($dev)->post(route('production.targets.store'), ['product_id' => $p->id, 'target_up' => 10, 'target_bt' => 6])
            ->assertSessionHasNoErrors();

        $t = \App\Models\ProductionTarget::firstOrFail();
        $this->assertSame([10, 6, 8, 20, 10], [$t->target_up, $t->target_bt, $t->target_qty, $t->baseline_up, $t->baseline_bt]);

        // produksi baru: UP +10 (tercapai), BT +2 (belum)
        ProductionLog::create(['product_id' => $p->id, 'user_id' => $dev->id, 'operator_name' => 'Dev', 'production_date' => today()->toDateString(), 'up_qty' => 10, 'bt_qty' => 2, 'total_qty' => 6, 'reject_qty' => 0]);

        $j = $this->actingAs($dev)->getJson(route('api.targets.live'))->assertOk()->json("actuals.{$p->id}");
        $this->assertSame(['target' => 10, 'actual' => 10, 'pct' => 100, 'done' => true], $j['split']['up']);
        $this->assertSame(['target' => 6, 'actual' => 2, 'pct' => 33, 'done' => false], $j['split']['bt']);
        $this->assertFalse($j['done'], 'tercapai hanya bila UP dan BT sama-sama tercapai');
        $this->assertSame(75, $j['pct']); // (10 + 2) / 16
    }

    public function test_target_biasa_tidak_terpengaruh_dan_menghapus_pemisahan_lama(): void
    {
        $dev = User::create(['name' => 'Dev', 'username' => 'dev3', 'email' => 'dev3@uji.test', 'role' => 'developer', 'is_active' => true, 'password' => Hash::make('rahasia123')]);
        $kat = Category::create(['name' => 'Channel Uji', 'is_active' => true, 'has_manual_serial' => false]);
        $p = Product::create(['category_id' => $kat->id, 'type' => 'channel', 'name' => 'CHANNEL', 'series' => '26C0003', 'kva' => '100', 'is_active' => true]);

        $this->actingAs($dev)->post(route('production.targets.store'), ['product_id' => $p->id, 'target_up' => 4, 'target_bt' => 4])->assertSessionHasNoErrors();
        // klien lama (Android) hanya mengirim target_qty: pemisahan dibuang
        $this->actingAs($dev)->post(route('production.targets.store'), ['product_id' => $p->id, 'target_qty' => 9])->assertSessionHasNoErrors();

        $t = \App\Models\ProductionTarget::firstOrFail();
        $this->assertSame([9, null, null], [$t->target_qty, $t->target_up, $t->target_bt]);

        $this->actingAs($dev)->post(route('production.targets.store'), ['product_id' => $p->id, 'target_up' => 0, 'target_bt' => 0])->assertSessionHasErrors('target_qty');
    }
}
