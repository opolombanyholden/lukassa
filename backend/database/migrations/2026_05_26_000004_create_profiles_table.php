<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('firstname', 100)->nullable();
            $table->string('lastname', 100)->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->default('Gabon');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('intervention_radius_km')->default(10);
            $table->decimal('average_rating', 2, 1)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->string('language', 10)->default('fr');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['latitude', 'longitude']);
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
