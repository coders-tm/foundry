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
        Schema::create('permissionables', function (Blueprint $table) {
            $table->string('permissionable_type');
            $table->uuid('permissionable_id');

            $table->string('permission_scope');

            $table->boolean('access')->default(false);

            $table->foreign('permission_scope')->references('scope')->on('permissions')->cascadeOnDelete();

            $table->unique(['permissionable_type', 'permissionable_id', 'permission_scope'], 'permissionable_unique');
        });
    }
};
