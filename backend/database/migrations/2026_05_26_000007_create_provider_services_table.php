<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');
            $table->uuid('service_id');
            $table->enum('price_model', ['fixed', 'hourly', 'quote'])->default('fixed');
            $table->integer('price_amount')->nullable();
            $table->text('custom_description')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->unique(['provider_id', 'service_id']);
            $table->index('is_available');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_services');
    }
};
