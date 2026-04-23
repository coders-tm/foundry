<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('provider')->nullable()->index();
            $table->boolean('auto_renewal_enabled')->default(false);
            $table->integer('quantity')->nullable();
            $table->foreignUuid('user_id')->nullable();
            $table->foreignUuid('plan_id')->nullable()->index();
            $table->string('next_plan')->nullable();
            $table->foreignUuid('coupon_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->string('billing_interval')->nullable()->comment('Billing cycle frequency (day, week, month, year)');
            $table->unsignedInteger('billing_interval_count')->nullable()->comment('Billing interval count (e.g., 2 for bi-weekly)');
            $table->unsignedInteger('total_cycles')->nullable()->comment('Total number of billing cycles for contract');
            $table->unsignedInteger('current_cycle')->default(0)->comment('Current billing cycle number');
            $table->string('status')->nullable()->index();
            $table->boolean('is_downgrade')->default(false);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->dateTime('expires_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable()->index();
            $table->timestamp('frozen_at')->nullable()->comment('When the subscription was frozen (paused)');
            $table->timestamp('release_at')->nullable()->comment('When the subscription should automatically unfreeze');
            $table->boolean('is_free_forever')->default(false);
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();

            $table->uuid('user_id')->nullable()->change()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['user_id', 'status']);
        });

        Schema::create('subscription_features', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->index();
            $table->string('label');
            $table->enum('type', ['integer', 'boolean'])->default('integer');
            $table->boolean('resetable')->default(false);
            $table->integer('value')->default(0);
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['subscription_id', 'slug']);
            $table->index(['subscription_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_features');
        Schema::dropIfExists('subscriptions');
    }
};
