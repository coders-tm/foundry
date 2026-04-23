<?php

use Foundry\Traits\Helpers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use Helpers;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('type')->default('plan')->comment('Type of coupon: plan, product, or cart');
            $table->enum('discount_type', ['percentage', 'fixed', 'override'])->default('percentage');
            $table->double('value', 10, 2)->default(0);
            $table->string('promotion_code')->unique()->index();
            $table->string('duration');
            $table->unsignedInteger('duration_in_months')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->boolean('auto_apply')->default(false);
            $table->boolean('active')->default(true)->index();
            $table->dateTime('expires_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coupon_plans', function (Blueprint $table) {
            $table->uuid('coupon_id');
            $table->uuid('plan_id');

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();

            $table->index(['coupon_id', 'plan_id']);
        });

        Schema::create('redeems', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('redeemable_type');
            $table->uuid('redeemable_id');
            $table->uuid('coupon_id')->index();
            $table->double('amount', 20, 2)->default(0.00)->nullable();
            $table->uuid('user_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();

            $table->index(['redeemable_type', 'redeemable_id']);
        });

    }
};
