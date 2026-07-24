<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $categories = [
            ['name' => 'Channel Swasta',   'code' => 'CHN-SWT', 'type' => 'channel'],
            ['name' => 'Channel Typetest', 'code' => 'CHN-TYP', 'type' => 'channel'],
            ['name' => 'Cover Swasta',     'code' => 'CVR-SWT', 'type' => 'regular'],
            ['name' => 'Cover Typetest',   'code' => 'CVR-TYP', 'type' => 'regular'],
            ['name' => 'Tangki Swasta',    'code' => 'TNK-SWT', 'type' => 'regular'],
            ['name' => 'Tangki Typetest',  'code' => 'TNK-TYP', 'type' => 'regular'],
        ];

        foreach ($categories as $cat) {
            $catId = DB::table('categories')->insertGetId([
                'name'              => $cat['name'],
                'code'              => $cat['code'],
                'description'       => null,
                'is_active'         => true,
                'has_manual_serial' => true,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            DB::table('products')->insert([
                'category_id' => $catId,
                'type'        => $cat['type'],
                'name'        => $cat['name'],
                'series'      => null,
                'kva'         => null,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        $names = [
            'Channel Swasta', 'Channel Typetest',
            'Cover Swasta',   'Cover Typetest',
            'Tangki Swasta',  'Tangki Typetest',
        ];

        $catIds = DB::table('categories')->whereIn('name', $names)->pluck('id');
        DB::table('products')->whereIn('category_id', $catIds)->delete();
        DB::table('categories')->whereIn('id', $catIds)->delete();
    }
};
