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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid()->unique()->nullable()->index();

            $table->string('paymentable_type')->nullable();
            $table->uuid('paymentable_id')->nullable();

            $table->uuid('payment_method_id')->index()->nullable();
            $table->string('transaction_id')->index()->nullable();
            $table->string('currency', 3)->default('USD');
            $table->double('amount', 20, 2)->default(0.00);
            $table->double('fees', 10, 2)->nullable();
            $table->double('net_amount', 10, 2)->nullable();
            $table->double('refund_amount', 10, 2)->default(0);
            $table->boolean('capturable')->default(true);
            $table->string('status')->index()->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->cascadeOnDelete();

            $table->index(['paymentable_type', 'paymentable_id']);
        });
    }
};
