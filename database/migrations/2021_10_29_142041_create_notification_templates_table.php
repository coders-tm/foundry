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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('label')->nullable();
            $table->string('subject')->nullable();
            $table->string('type')->nullable()->index();
            $table->text('content')->nullable();
            $table->boolean('is_default')->default(false)->index();

            $table->timestamps();
        });
    }
};
