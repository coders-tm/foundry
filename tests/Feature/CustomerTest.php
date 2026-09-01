<?php

use App\Models\User;
use Carbon\Carbon;
use Foundry\Tests\Feature\FeatureTestCase;

uses(FeatureTestCase::class);

it('customer can be put on a generic trial', function () {
    $user = new User;

    $this->assertFalse($user->onGenericTrial());

    $user->trial_ends_at = Carbon::tomorrow();

    $this->assertTrue($user->onTrial());
    $this->assertTrue($user->onGenericTrial());

    $user->trial_ends_at = Carbon::today()->subDays(5);

    $this->assertFalse($user->onGenericTrial());
});

it('we can check if a generic trial has expired', function () {
    $user = new User;

    $user->trial_ends_at = Carbon::yesterday();

    $this->assertTrue($user->onTrialExpired());
    $this->assertTrue($user->hasExpiredGenericTrial());
});
