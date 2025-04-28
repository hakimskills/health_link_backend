<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'first_name' => 'Hakim',
            'last_name' => 'Admin',
            'email' => 'hakim@gmail.com',
            'phone_number' => '0123456789',
            'wilaya' => 'Algiers',
            'role' => 'Admin',
            'password' => Hash::make('hakimad123'),
        ]);

        User::create([
            'first_name' => 'Hakim',
            'last_name' => 'Admin',
            'email' => 'hakim1@gmail.com',
            'phone_number' => '02123456789',
            'wilaya' => 'Algiers',
            'role' => 'Healthcare Professional',
            'password' => Hash::make('hakimad123'),
        ]);
    }
}
