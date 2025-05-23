<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
        $table->id('product_id');
        $table->unsignedBigInteger('store_id')->nullable();
        $table->string('product_name');
        $table->text('description')->nullable();
        $table->decimal('price', 10, 2)->nullable();
        $table->decimal('inventory_price', 10, 2)->nullable();
        $table->integer('stock')->default(0);
        $table->string('category');
        $table->enum('type', ['new', 'inventory', 'used_equipment'])->default('new');
        $table->string('condition')->nullable();
        $table->timestamp('added_date')->useCurrent();
        $table->timestamps();
        $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
});
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
