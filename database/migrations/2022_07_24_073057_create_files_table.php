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
        Schema::create('files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('disk')->nullable()->index();
            $table->string('url')->nullable();
            $table->string('path')->nullable();
            $table->string('original_file_name')->nullable();
            $table->string('hash')->nullable();
            $table->string('mime_type')->nullable()->index();
            $table->string('extension')->nullable();
            $table->string('size')->nullable();
            $table->string('alt')->nullable();
            $table->json('conversions')->nullable();
            $table->string('share_token')->unique()->nullable();
            $table->string('ref')->default('default')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
