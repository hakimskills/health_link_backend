<?php

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
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
            'role' => 'Doctor',
            'password' => Hash::make('hakimad123'),
        ]);
    }
}
