<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin utama
        User::create([
            'name'       => 'Administrator',
            'username'   => 'admin',
            'email'      => 'admin@asataproduction.com',
            'password'   => Hash::make('admin123'),
            'role'       => 'admin',
            'department' => 'Management',
            'is_active'  => true,
        ]);

        // Operator shift 1
        User::create([
            'name'       => 'Budi Santoso',
            'username'   => 'operator1',
            'email'      => 'operator1@asataproduction.com',
            'password'   => Hash::make('operator123'),
            'role'       => 'operator',
            'department' => 'Produksi',
            'is_active'  => true,
        ]);

        // Operator shift 2
        User::create([
            'name'       => 'Siti Rahayu',
            'username'   => 'operator2',
            'email'      => 'operator2@asataproduction.com',
            'password'   => Hash::make('operator123'),
            'role'       => 'operator',
            'department' => 'Produksi',
            'is_active'  => true,
        ]);

        // Operator shift 3
        User::create([
            'name'       => 'Ahmad Fauzi',
            'username'   => 'operator3',
            'email'      => 'operator3@asataproduction.com',
            'password'   => Hash::make('operator123'),
            'role'       => 'operator',
            'department' => 'Produksi',
            'is_active'  => true,
        ]);
    }
}
