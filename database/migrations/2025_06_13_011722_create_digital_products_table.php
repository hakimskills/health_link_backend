<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDigitalProductsTable extends Migration
{
    public function up(): void
    {
        Schema::create('digital_products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('logo')->nullable(); // e.g., logo.png or full URL
            $table->string('product_image')->nullable(); // separate from logo
            $table->text('description')->nullable();
            $table->string('url')->nullable(); // external or internal link
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_products');
    }
};

