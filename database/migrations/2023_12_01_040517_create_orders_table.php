<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number')->nullable()->unique();
            $table->string('status')->default('draft');

            $table->nullableUuidMorphs('orderable');
            $table->uuid('customer_id')->nullable()->index();
            $table->uuid('location_id')->nullable()->index();
            $table->text('note')->nullable();
            $table->boolean('collect_tax')->default(true);
            $table->json('billing_address')->nullable();
            $table->json('metadata')->nullable();
            $table->string('source')->nullable();
            $table->double('sub_total')->default(0.00);
            $table->double('tax_total')->default(0.00);
            $table->double('discount_total')->default(0.00);
            $table->double('grand_total')->default(0.00);
            $table->double('paid_total')->default(0.00);
            $table->double('refund_total')->default(0.00);
            $table->integer('line_items_quantity')->default(0);
            $table->string('payment_status')->default('pending');
            $table->dateTime('due_date')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->timestamps();
            $table->softDeletes()->index();
            $table->index(['status'], 'orders_status_index');
            $table->index(['payment_status', 'due_date'], 'orders_payment_status_due_date_index');
        });

        Schema::create('line_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('itemable_type')->nullable();
            $table->uuid('itemable_id')->nullable();
            $table->string('title')->nullable();
            $table->string('variant_title')->nullable();
            $table->string('sku')->nullable();
            $table->boolean('taxable')->default(true);
            $table->boolean('is_custom')->nullable()->default(false);
            $table->integer('quantity')->nullable()->default(1);
            $table->double('price')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['itemable_type', 'itemable_id']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->nullable();
            $table->uuid('payment_id')->nullable();
            $table->double('amount')->default(0.00);
            $table->text('reason')->nullable();
            $table->boolean('to_wallet')->default(false);
            $table->foreignUuid('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
        });

        Schema::create('order_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contactable_type')->nullable();
            $table->uuid('contactable_id')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
        });
    }
};
