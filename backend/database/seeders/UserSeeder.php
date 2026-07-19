<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Kasir 1
        User::create([
            'name' => 'Kasir Ilham',
            'email' => 'ilham@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'kasir',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Kasir 2
        User::create([
            'name' => 'Kasir Cintya',
            'email' => 'cintya@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'kasir',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Barista 1
        User::create([
            'name' => 'Barista Roni',
            'email' => 'roni@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'barista',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Barista 2
        User::create([
            'name' => 'Barista Siti',
            'email' => 'siti@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'barista',
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Manager
        User::create([
            'name' => 'Manager Kurniawan',
            'email' => 'kurniawan@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
            'created_at' => now(),
        ]);
    }
}
