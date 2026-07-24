<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categoryMap = Category::pluck('id', 'code')->toArray();

        $products = [
            // Tanki
            ['category' => 'TNK', 'name' => 'Tanki 160', 'series' => '2601601004(160)', 'unit' => 'unit'],
            ['category' => 'TNK', 'name' => 'Tanki 200', 'series' => '2601602004(200)', 'unit' => 'unit'],
            ['category' => 'TNK', 'name' => 'Tanki 300', 'series' => '2601603004(300)', 'unit' => 'unit'],
            // Cover
            ['category' => 'CVR', 'name' => 'Cover Atas A1', 'series' => 'CVR-A1-001', 'unit' => 'pcs'],
            ['category' => 'CVR', 'name' => 'Cover Bawah A1', 'series' => 'CVR-A1-002', 'unit' => 'pcs'],
            ['category' => 'CVR', 'name' => 'Cover Samping B2', 'series' => 'CVR-B2-001', 'unit' => 'pcs'],
            // Body
            ['category' => 'BDY', 'name' => 'Body Utama V1',  'series' => 'BDY-V1-001', 'unit' => 'unit'],
            ['category' => 'BDY', 'name' => 'Body Rangka V2', 'series' => 'BDY-V2-001', 'unit' => 'unit'],
            // Bracket
            ['category' => 'BRK', 'name' => 'Bracket Motor',  'series' => 'BRK-MT-001', 'unit' => 'pcs'],
            ['category' => 'BRK', 'name' => 'Bracket Dudukan','series' => 'BRK-DK-001', 'unit' => 'pcs'],
            // Panel
            ['category' => 'PNL', 'name' => 'Panel Depan',    'series' => 'PNL-DP-001', 'unit' => 'lembar'],
            ['category' => 'PNL', 'name' => 'Panel Belakang', 'series' => 'PNL-BL-001', 'unit' => 'lembar'],
        ];

        foreach ($products as $p) {
            Product::create([
                'category_id' => $categoryMap[$p['category']] ?? 1,
                'name'        => $p['name'],
                'series'      => $p['series'],
                'unit'        => $p['unit'],
                'is_active'   => true,
            ]);
        }
    }
}
