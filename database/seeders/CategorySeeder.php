<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Tanki',   'code' => 'TNK', 'description' => 'Produk tangki berbagai ukuran'],
            ['name' => 'Cover',   'code' => 'CVR', 'description' => 'Produk cover dan penutup'],
            ['name' => 'Body',    'code' => 'BDY', 'description' => 'Produk body dan rangka'],
            ['name' => 'Bracket', 'code' => 'BRK', 'description' => 'Produk bracket dan penopang'],
            ['name' => 'Panel',   'code' => 'PNL', 'description' => 'Produk panel dan plat'],
        ];

        foreach ($categories as $cat) {
            Category::create($cat + ['is_active' => true]);
        }
    }
}
