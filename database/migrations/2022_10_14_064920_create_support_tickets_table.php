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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number')->nullable()->unique()->index();
            $table->string('source')->nullable()->index();

            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->nullable()->index();
            $table->boolean('seen')->default(false)->index();
            $table->boolean('is_archived')->default(false)->index();
            $table->boolean('user_archived')->default(false)->index();
            $table->uuid('admin_id')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('user_type')->nullable();
            $table->uuid('user_id')->nullable();

            $table->uuid('support_ticket_id')->index();

            $table->text('message')->nullable();
            $table->boolean('seen')->default(false)->index();
            $table->boolean('staff_only')->default(false)->index();
            $table->timestamps();

            $table->foreign('support_ticket_id')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->index(['user_type', 'user_id']);
        });
    }
};
