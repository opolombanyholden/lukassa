<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->enum('zone_type', ['city', 'district', 'region', 'custom'])->default('city');
            $table->geometry('boundary', 'polygon')->nullable();
            $table->decimal('center_lat', 10, 8);
            $table->decimal('center_lng', 11, 8);
            $table->integer('radius_km')->default(10);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('zone_type');
            $table->index('is_active');
            $table->spatialIndex('boundary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_zones');
    }
};
