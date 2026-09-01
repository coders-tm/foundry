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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Rename canceled_at to cancels_at
            if (! Schema::hasColumn('subscriptions', 'cancels_at')) {
                $table->renameColumn('canceled_at', 'cancels_at');
            }

            // Add credit_resets_at column
           if (! Schema::hasColumn('subscriptions', 'credit_resets_at')) {
                $table->timestamp('credit_resets_at')->nullable()->after('is_free_forever');
           }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do nothing
    }
};
