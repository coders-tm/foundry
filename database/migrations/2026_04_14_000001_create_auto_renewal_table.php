<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates a table to store payment provider customer references.
     * Maps application users to their provider-specific customer IDs and options.
     */
    public function up(): void
    {
        Schema::create('users_payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->nullable();
            $table->string('provider')->index();
            $table->string('provider_id')->nullable()->index();
            $table->boolean('is_default')->default(false);
            $table->json('options')->nullable();

            $table->timestamps();

            // Unique index per user, provider, and provider_id
            $table->unique(['user_id', 'provider', 'provider_id']);
        });

        Schema::create('payment_provider_customers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->nullable();
            $table->string('provider')->index();
            $table->string('provider_id')->nullable();
            $table->json('options')->nullable();

            $table->timestamps();

            // Ensure one customer record per user per provider
            $table->unique(['user_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_payment_methods');
        Schema::dropIfExists('payment_provider_customers');
    }
};
