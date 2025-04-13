<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id('product_id');
            $table->unsignedBigInteger('store_id'); // Matches stores.id
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('stock')->default(0);
            $table->string('category');
            $table->timestamp('added_date')->useCurrent(); // Keep this for your custom timestamp
            
            // Adding created_at and updated_at columns
            $table->timestamps(); // Adds created_at and updated_at automatically
            
            // ✅ Correct foreign key definition
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

