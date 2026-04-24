<?php

use Foundry\Concerns\Helpers;
use Foundry\Models\Log;
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
        Schema::create('logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('logable_type')->nullable();
            $table->uuid('logable_id')->nullable();

            $table->string('type')->default('default')->index();
            $table->string('status')->default(Log::STATUS_SUCCESS)->index();
            $table->text('message')->nullable();
            $table->{$this->jsonable()}('options')->nullable();

            $table->uuid('admin_id')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['logable_type', 'logable_id']);
        });
    }
};
