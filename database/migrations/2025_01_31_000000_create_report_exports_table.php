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
        Schema::create('report_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->constrained('admins')->onDelete('cascade');
            $table->string('type'); // 'subscriptions', 'orders', 'customers'
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('file_size')->nullable(); // in bytes
            $table->unsignedInteger('total_records')->nullable();
            $table->json('filters')->nullable(); // store applied filters
            $table->json('metadata')->nullable(); // additional data
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // auto-delete old files
            $table->timestamps();

            $table->index(['admin_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
