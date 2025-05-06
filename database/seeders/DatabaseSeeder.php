<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create users
        $admin = User::create([
            'first_name' => 'Hakim',
            'last_name' => 'Admin',
            'email' => 'hakim@gmail.com',
            'phone_number' => '0123456789',
            'wilaya' => 'Algiers',
            'role' => 'Admin',
            'password' => Hash::make('hakimad123'),
        ]);

        $doctor = User::create([
            'first_name' => 'Hakim',
            'last_name' => 'Admin',
            'email' => 'hakim1@gmail.com',
            'phone_number' => '02123456789',
            'wilaya' => 'Algiers',
            'role' => 'Doctor',
            'password' => Hash::make('hakimad123'),
        ]);

        // Create stores
        $store1 = Store::create([
            'owner_id' => $admin->id,
            'store_name' => 'Medical Supply Center',
            'address' => 'Algiers Center',
            'phone' => '021112233',
        ]);

        $store2 = Store::create([
            'owner_id' => $doctor->id,
            'store_name' => 'Doctor’s Equipment Depot',
            'address' => 'Bab El Oued',
            'phone' => '021445566',
        ]);

        // Create products for store1
        Product::create([
            'store_id' => $store1->id,
            'product_name' => 'Surgical Gloves',
            'description' => 'High-quality latex gloves for surgical procedures.',
            'price' => 200.00,
            'inventory_price' => 150.00,
            'stock' => 100,
            'category' => 'Gloves',
            'type' => 'new',
        ]);

        Product::create([
            'store_id' => $store1->id,
            'product_name' => 'Face Masks (Box of 50)',
            'description' => 'Medical grade masks for hospital use.',
            'price' => 500.00,
            'inventory_price' => 350.00,
            'stock' => 50,
            'category' => 'Masks',
            'type' => 'new',
        ]);

        Product::create([
            'store_id' => $store1->id,
            'product_name' => 'Stethoscope',
            'description' => 'Standard stethoscope for medical examinations.',
            'price' => 1200.00,
            'inventory_price' => 900.00,
            'stock' => 20,
            'category' => 'Instruments',
            'type' => 'inventory',
        ]);

        // Create products for store2
        Product::create([
            'store_id' => $store2->id,
            'product_name' => 'Thermometer',
            'description' => 'Digital thermometer with quick readout.',
            'price' => 300.00,
            'inventory_price' => 220.00,
            'stock' => 30,
            'category' => 'Diagnostics',
            'type' => 'inventory',
        ]);

        Product::create([
            'store_id' => $store2->id,
            'product_name' => 'Blood Pressure Monitor',
            'description' => 'Automatic monitor with cuff.',
            'price' => 2000.00,
            'inventory_price' => 1700.00,
            'stock' => 15,
            'category' => 'Diagnostics',
            'type' => 'new',
        ]);

        Product::create([
            'store_id' => $store2->id,
            'product_name' => 'Wheelchair',
            'description' => 'Foldable steel wheelchair for patient mobility.',
            'price' => 5000.00,
            'inventory_price' => 4000.00,
            'stock' => 5,
            'category' => 'Mobility',
            'type' => 'new',
        ]);
    }
}
