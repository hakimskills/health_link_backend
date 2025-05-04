<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_order_items', function (Blueprint $table) {
            $table->id('item_id');
            $table->unsignedBigInteger('product_order_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity')->default(1);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_order_id')->references('product_order_id')->on('product_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('product_id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_order_items');
    }
};
