<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('user_id')->nullable();
            $table->string('reference', 50)->unique();
            $table->enum('gateway', ['airtel_money', 'moov_money', 'stripe', 'paypal', 'cash', 'wallet']);
            $table->string('gateway_transaction_id')->nullable();
            $table->enum('type', ['debit', 'credit', 'commission', 'refund', 'payout']);
            $table->integer('amount');
            $table->integer('fees')->default(0);
            $table->string('currency', 3)->default('XAF');
            $table->json('gateway_response')->nullable();
            $table->enum('status', ['pending', 'success', 'failed', 'reversed'])->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('reference');
            $table->index('gateway_transaction_id');
            $table->index('status');
            $table->index(['order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
