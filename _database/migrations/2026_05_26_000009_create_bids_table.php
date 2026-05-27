<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('rfq_id');
            $table->uuid('provider_id');
            $table->integer('amount');
            $table->integer('estimated_duration_hours')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'selected', 'rejected', 'expired'])->default('pending');
            $table->timestamps();

            $table->foreign('rfq_id')->references('id')->on('rfqs')->onDelete('cascade');
            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('status');
            $table->unique(['rfq_id', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
