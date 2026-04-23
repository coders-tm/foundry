<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title')->nullable();
            $table->date('date_at')->nullable();
            $table->string('start_at', 8)->nullable();   // HH:MM or HH:MM:SS
            $table->string('end_at', 8)->nullable();     // HH:MM or HH:MM:SS
            $table->timestamp('sign_off_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
