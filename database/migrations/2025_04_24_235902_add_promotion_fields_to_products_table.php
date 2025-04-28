<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // First ensure we have the doctrine/dbal package for column changes
        if (!Schema::hasColumn('products', 'sku')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('sku', 50)->unique()->nullable()->after('category');
                $table->decimal('original_price', 10, 2)->nullable()->after('price');
                $table->integer('original_stock')->nullable()->after('stock');
                $table->boolean('is_on_promo')->default(false)->after('original_stock');
                $table->dateTime('promo_end_date')->nullable()->after('is_on_promo');
                $table->boolean('is_active')->default(true)->after('promo_end_date');
            });
        }
    }

    public function down()
    {
        // Only drop columns if they exist
        Schema::table('products', function (Blueprint $table) {
            $columnsToDrop = [];
            
            if (Schema::hasColumn('products', 'sku')) {
                $columnsToDrop[] = 'sku';
            }
            if (Schema::hasColumn('products', 'original_price')) {
                $columnsToDrop[] = 'original_price';
            }
            if (Schema::hasColumn('products', 'original_stock')) {
                $columnsToDrop[] = 'original_stock';
            }
            if (Schema::hasColumn('products', 'is_on_promo')) {
                $columnsToDrop[] = 'is_on_promo';
            }
            if (Schema::hasColumn('products', 'promo_end_date')) {
                $columnsToDrop[] = 'promo_end_date';
            }
            if (Schema::hasColumn('products', 'is_active')) {
                $columnsToDrop[] = 'is_active';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};