<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id(); // This creates `id` as the primary key
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('store_name');
            $table->string('address');
            $table->string('phone');
            $table->timestamps();
        });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};

