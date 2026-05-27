<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->unique();
            $table->uuid('reviewer_id');
            $table->uuid('reviewed_id');
            $table->integer('rating');
            $table->text('comment')->nullable();
            $table->json('quality_metrics')->nullable();
            $table->boolean('is_verified_purchase')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('reviewer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('reviewed_id');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
