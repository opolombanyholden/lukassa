<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 20)->unique();
            $table->string('email')->unique()->nullable();
            $table->string('password');
            $table->enum('type', ['client', 'prestataire', 'admin'])->default('client');
            $table->enum('status', ['pending', 'active', 'suspended', 'deleted'])->default('pending');
            $table->timestamp('identity_verified_at')->nullable();
            $table->text('firebase_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
