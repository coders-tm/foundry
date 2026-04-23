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
        Schema::create('groupables', function (Blueprint $table) {
            $table->string('groupable_type');
            $table->uuid('groupable_id');
            $table->uuid('group_id');

            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();

            $table->primary(['groupable_type', 'groupable_id', 'group_id']);

            $table->index(['groupable_type', 'groupable_id']);
        });
    }
};
