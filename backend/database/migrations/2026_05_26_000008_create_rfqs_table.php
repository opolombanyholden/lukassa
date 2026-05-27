<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('service_id');
            $table->text('description');
            $table->string('address', 255);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('preferred_date')->nullable();
            $table->enum('status', ['open', 'assigned', 'completed', 'cancelled', 'expired'])->default('open');
            $table->boolean('is_anonymous')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->index('status');
            $table->index(['latitude', 'longitude']);
            $table->index('preferred_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfqs');
    }
};
