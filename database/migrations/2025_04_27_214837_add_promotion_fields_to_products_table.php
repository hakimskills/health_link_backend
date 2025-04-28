<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('original_price', 10, 2)->nullable()->after('price');
            $table->boolean('is_on_promo')->default(false)->after('stock');
            $table->dateTime('promo_end_date')->nullable()->after('is_on_promo');
            $table->boolean('is_active')->default(true)->after('promo_end_date');
            $table->string('sku', 50)->nullable()->after('category');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'original_price',
                'is_on_promo',
                'promo_end_date',
                'is_active',
                'sku'
            ]);
        });
    }
};