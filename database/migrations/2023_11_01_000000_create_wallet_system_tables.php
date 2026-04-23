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
        // Prevent duplicate table creation
        if (Schema::hasTable('wallet_balances')) {
            return;
        }

        // Wallet balances table
        Schema::create('wallet_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->double('balance', 10, 2)->default(0.00);
            $table->timestamps();

            $table->unique('user_id');
        });

        // Wallet transactions table
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_balance_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // credit, debit
            $table->string('source'); // refund, advance_payment, subscription_renewal
            $table->double('amount', 10, 2);
            $table->double('balance_before', 10, 2);
            $table->double('balance_after', 10, 2);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            // Polymorphic relation to source (Order, Subscription, Payment, etc.)
            $table->nullableUuidMorphs('transactionable', 'wallet_txn_morph_index');

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('type');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_balances');
    }
};
