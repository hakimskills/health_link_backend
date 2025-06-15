<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductOrderItem;

class GenerateRecommendationCSV extends Command
{
    protected $signature = 'generate:recommendation-csv';
    protected $description = 'Generate CSV for product recommendations';

    public function handle()
    {
        // Get all users and products
        $users = User::all();
        $products = Product::all();

        // Prepare data
        $data = [];
        foreach ($users as $user) {
            $userName = $user->first_name . ' ' . $user->last_name;
            foreach ($products as $product) {
                // Check if the user has purchased the product
                $interaction = ProductOrderItem::whereHas('order', function ($query) use ($user) {
                    $query->where('buyer_id', $user->id);
                })->where('product_id', $product->product_id)->exists() ? 1 : 0;

                $data[] = [
                    'user_id' => $user->id,
                    'user_name' => $userName,
                    'product' => $product->product_name,
                    'interaction' => $interaction
                ];
            }
        }

        // Write CSV
        $csvPath = base_path('scripts/doctor_product_dataset_50x50.csv');
        $file = fopen($csvPath, 'w');
        fputcsv($file, ['user_id', 'user_name', 'product', 'interaction']);
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        $this->info('CSV generated successfully at ' . $csvPath);
    }
}