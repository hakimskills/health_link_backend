<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // First check if table exists
        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
                $table->string('name');
                $table->text('description');
                $table->string('phone', 20);
                $table->string('email');
                $table->string('address', 500);
                $table->json('specialties')->nullable();
                $table->string('logo_path')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->timestamps();
            });
        } else {
            // Table exists - modify it instead
            Schema::table('stores', function (Blueprint $table) {
                // Add any new columns or modifications here
                if (!Schema::hasColumn('stores', 'specialties')) {
                    $table->json('specialties')->nullable()->after('address');
                }
                // Add other missing columns similarly
            });
        }
    }

    public function down()
    {
        // Only drop if in development
        if (app()->environment('local')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            Schema::dropIfExists('stores');
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
};
