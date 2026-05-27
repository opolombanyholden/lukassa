<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('raised_by');
            $table->enum('reason', ['service_not_delivered', 'quality_issue', 'payment_issue', 'misconduct', 'other']);
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->enum('status', ['open', 'investigating', 'resolved', 'closed', 'escalated'])->default('open');
            $table->uuid('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->enum('resolution_action', ['refund', 'partial_refund', 'none'])->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('raised_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
