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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('label')->nullable();
            $table->string('provider')->default('manual')->index();
            $table->string('integration_via')->nullable();
            $table->string('link')->nullable();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->{$this->jsonable()}('credentials')->nullable();
            $table->{$this->jsonable()}('methods')->nullable();
            $table->{$this->jsonable()}('supported_currencies')->nullable();
            $table->boolean('active')->default(false)->index();
            $table->string('capture')->default('manual')->index();
            $table->string('additional_details')->nullable();
            $table->string('payment_instructions')->nullable();
            $table->boolean('test_mode')->default(false);
            $table->double('transaction_fee', 10, 2)->default(0.00);
            $table->text('webhook')->nullable();
            $table->{$this->jsonable()}('options')->nullable();
            $table->integer('order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }
};
