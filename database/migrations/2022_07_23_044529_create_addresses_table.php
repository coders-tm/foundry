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
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->nullableUuidMorphs('addressable');

            $table->string('name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('state_code')->nullable();

            $table->string('postal_code')->nullable()->index();
            $table->string('country')->nullable();
            $table->string('country_code')->nullable()->index();

            $table->string('phone_number')->nullable();
            $table->boolean('default')->default(false)->index();

            $table->string('ref')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
