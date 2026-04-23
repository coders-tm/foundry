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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone_number')->nullable()->after('email');
            $table->boolean('is_active')->nullable()->default(true)->after('remember_token');
            $table->string('status')->nullable()->after('is_active')->default('pending');
            $table->string('gender')->nullable()->after('name');
            $table->date('dob')->nullable()->after('gender');
            $table->string('username')->nullable()->after('name');
            $table->string('note')->nullable()->after('phone_number');
            $table->json('settings')->nullable()->after('phone_number');
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            $table->softDeletes();
        });
    }
};
