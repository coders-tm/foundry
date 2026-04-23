<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->string('key')->primary();

            $table->string('name')->nullable();
            $table->string('url')->nullable();
            $table->boolean('show_menu')->default(1);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });

    }
};
