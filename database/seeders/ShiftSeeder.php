<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = [
            ['name' => 'Shift 1', 'start_time' => '07:00:00', 'end_time' => '15:00:00'],
            ['name' => 'Shift 2', 'start_time' => '15:00:00', 'end_time' => '23:00:00'],
            ['name' => 'Shift 3', 'start_time' => '23:00:00', 'end_time' => '07:00:00'],
        ];

        foreach ($shifts as $shift) {
            Shift::create($shift + ['is_active' => true]);
        }
    }
}
