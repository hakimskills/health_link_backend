<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // Create a new migration file with:
// php artisan make:migration create_product_images_table

public function up(): void
{
    Schema::create('product_images', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_id');
        $table->string('image_path');
        $table->boolean('is_primary')->default(false);
        $table->timestamps();
        
        $table->foreign('product_id')
              ->references('product_id')
              ->on('products')
              ->onDelete('cascade');
    });
}

public function down(): void
{
    Schema::dropIfExists('product_images');
}
public function feature()
{
    return $this->hasOne(ImageFeature::class, 'image_id');
}
};
