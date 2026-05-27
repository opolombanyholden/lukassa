<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('cover_image')->nullable();
            $table->integer('min_price_estimate')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_quote')->default(false);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->index('slug');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
