<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Fix users table if primary key was modified
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'user_id')) {
                $table->dropColumn('user_id'); // Remove if accidentally created
            }
        });
        
        // 2. Fix product_orders foreign key
        Schema::table('product_orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']); // Remove old incorrect constraint
            
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }
    
    public function down()
    {
        // Revert changes if needed
    }
};
