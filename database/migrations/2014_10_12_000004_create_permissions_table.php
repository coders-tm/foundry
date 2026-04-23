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
        Schema::create('permissions', function (Blueprint $table) {
            $table->string('scope')->primary();

            $table->string('module_key')->index();

            $table->string('action');

            $table->timestamps();

            $table->foreign('module_key')->references('key')->on('modules')->cascadeOnDelete();
        });

    }
};
