<?php

use Foundry\Concerns\Helpers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use Helpers;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tax_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('taxable_type')->nullable();
            $table->uuid('taxable_id')->nullable();

            $table->string('label');
            $table->enum('type', ['normal', 'harmonized', 'compounded'])->default('normal');
            $table->double('rate', 5, 2)->default(0);
            $table->double('amount', 10, 2)->default(0);

            $table->index(['taxable_type', 'taxable_id']);
        });

        Schema::create('discount_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('discountable_type')->nullable();
            $table->uuid('discountable_id')->nullable();

            $table->enum('type', ['percentage', 'fixed_amount', 'price_override'])->default('fixed_amount');
            $table->double('value', 20, 2)->default(0.00);
            $table->string('description')->nullable();
            $table->uuid('coupon_id')->nullable();
            $table->string('coupon_code')->nullable();

            $table->index(['discountable_type', 'discountable_id']);
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
            $table->index(['coupon_id', 'coupon_code']);
        });
    }
};
