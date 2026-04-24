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
        Schema::create('statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('statusable_type')->nullable();
            $table->uuid('statusable_id')->nullable();

            $table->string('label');

            $table->index(['statusable_type', 'statusable_id']);
        });
    }
};
