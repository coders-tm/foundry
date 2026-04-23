<?php

use Foundry\Traits\Helpers;
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
        Schema::create('taxes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('country')->nullable();
            $table->string('code')->nullable();
            $table->string('state')->nullable();
            $table->string('label')->nullable();
            $table->boolean('compounded')->nullable()->default(false);
            $table->double('rate', 10, 2)->default(0.00);
            $table->tinyInteger('priority')->default(0);

            $table->timestamps();
        });

    }
};
