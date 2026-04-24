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
        Schema::create('blogs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('category')->nullable()->index();
            $table->string('slug')->unique()->index();
            $table->longText('description');
            $table->{$this->jsonable()}('options')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('meta_description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('userable_type');
            $table->uuid('userable_id');

            $table->string('commentable_type');
            $table->uuid('commentable_id');

            $table->longText('message');
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['userable_type', 'userable_id']);
            $table->index(['commentable_type', 'commentable_id']);
        });

        Schema::create('blogs_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('label')->nullable()->index();
            $table->string('slug')->nullable()->unique()->index();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('blogs_taggables', function (Blueprint $table) {
            $table->string('taggable_type')->nullable();
            $table->uuid('taggable_id')->nullable();

            $table->uuid('tag_id')->index();

            $table->foreign('tag_id')->references('id')->on('blogs_tags')->cascadeOnDelete();

            $table->index(['taggable_type', 'taggable_id']);
        });
    }
};
