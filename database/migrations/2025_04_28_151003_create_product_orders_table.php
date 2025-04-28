<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductOrdersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products', 'product_id')->onDelete('cascade');
            $table->integer('quantity');
            $table->timestamp('order_date')->nullable();
            $table->enum('order_status', ['Pending', 'Processing', 'Shipped', 'Delivered', 'Canceled'])->default('Pending');
            $table->enum('payment_status', ['Paid', 'Unpaid'])->default('Unpaid');
            $table->string('delivery_address');
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_orders');
    }
}
