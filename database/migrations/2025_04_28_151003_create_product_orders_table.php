<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_orders', function (Blueprint $table) {
            $table->id('product_order_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('order_date')->nullable();
            $table->enum('order_status', ['Pending', 'Processing', 'Shipped', 'Delivered', 'Canceled'])->default('Pending');
            $table->enum('payment_status', ['Paid', 'Unpaid'])->default('Unpaid');
            $table->string('delivery_address');
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_orders');
    }
};
