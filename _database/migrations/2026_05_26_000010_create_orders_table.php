<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('rfq_id')->nullable();
            $table->uuid('bid_id')->nullable();
            $table->uuid('provider_id');
            $table->uuid('client_id');
            $table->uuid('service_id');
            $table->string('reference', 50)->unique();
            $table->integer('total_amount');
            $table->integer('commission_amount')->default(0);
            $table->integer('net_amount');
            $table->enum('payment_status', ['pending', 'paid', 'refunded', 'failed'])->default('pending');
            $table->enum('order_status', ['pending', 'confirmed', 'in_progress', 'delivered', 'cancelled', 'disputed'])->default('pending');
            $table->timestamp('scheduled_date_start')->nullable();
            $table->timestamp('scheduled_date_end')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('payment_intent_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('rfq_id')->references('id')->on('rfqs')->onDelete('set null');
            $table->foreign('bid_id')->references('id')->on('bids')->onDelete('set null');
            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->index('reference');
            $table->index('payment_status');
            $table->index('order_status');
            $table->index('scheduled_date_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
