<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductionLogSeeder extends Seeder
{
    public function run(): void
    {
        $products  = Product::pluck('id')->toArray();
        $operators = User::where('role', 'operator')->pluck('id')->toArray();

        if (empty($products) || empty($operators)) return;

        // Generate 60 hari data historis
        for ($daysAgo = 60; $daysAgo >= 0; $daysAgo--) {
            $date = now()->subDays($daysAgo)->toDateString();

            // Pilih 3-6 produk acak per hari
            $dailyProducts = array_rand(array_flip($products), min(rand(3, 6), count($products)));
            if (!is_array($dailyProducts)) $dailyProducts = [$dailyProducts];

            foreach ($dailyProducts as $productId) {
                $operatorId = $operators[array_rand($operators)];

                ProductionLog::create([
                    'product_id'      => $productId,
                    'user_id'         => $operatorId,
                    'production_date' => $date,
                    'shift1_qty'      => rand(2, 15),
                    'shift2_qty'      => rand(2, 15),
                    'shift3_qty'      => rand(0, 10),
                    'notes'           => rand(0, 5) === 0 ? 'Produksi normal, tidak ada kendala' : null,
                    'status'          => 'confirmed',
                ]);
            }
        }
    }
}
