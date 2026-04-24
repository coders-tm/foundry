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
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label')->nullable();
            $table->string('slug')->unique()->index();
            $table->longText('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('default_interval')->default('month');
            $table->string('interval')->default('month')->index();
            $table->unsignedInteger('interval_count')->default(1);
            $table->boolean('is_contract')->default(false)->comment('Whether this is a contract plan requiring multiple billing cycles');
            $table->unsignedInteger('contract_cycles')->nullable()->comment('Total number of billing cycles required for contract (null = unlimited)');
            $table->boolean('allow_freeze')->default(true)->comment('Whether this plan allows membership freeze');
            $table->double('freeze_fee', 10, 2)->nullable()->comment('Fee charged per billing cycle during freeze (null = use global config)');
            $table->unsignedInteger('grace_period_days')->default(0)->comment('Number of grace period days before subscription expires after non-payment (0 = expires immediately)');
            $table->double('price', 12, 2)->default(0.00);
            $table->unsignedInteger('trial_days')->nullable()->default(0);
            $table->{$this->jsonable()}('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->foreignUuid('plan_id');
            $table->foreignUuid('feature_id');
            $table->integer('value')->default(0);

            $table->index(['plan_id', 'feature_id']);
        });
    }
};
