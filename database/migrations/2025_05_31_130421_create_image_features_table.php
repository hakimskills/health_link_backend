<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('image_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_id')->constrained('product_images')->onDelete('cascade');
            $table->json('feature_vector');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('image_features');
    }
};
