<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->enum('plan', ['basic', 'premium', 'enterprise'])->default('basic');
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending'])->default('pending');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->integer('price_paid')->default(0);
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('status');
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
