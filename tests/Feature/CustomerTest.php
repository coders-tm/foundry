<?php

use App\Models\User;
use Carbon\Carbon;
use Foundry\Tests\Feature\FeatureTestCase;

uses(FeatureTestCase::class);

it('customer can be put on a generic trial', function () {
    $user = new User;

    expect($user->onGenericTrial())->toBeFalse();

    $user->trial_ends_at = Carbon::tomorrow();

    expect($user->onTrial())->toBeTrue();
    expect($user->onGenericTrial())->toBeTrue();

    $user->trial_ends_at = Carbon::today()->subDays(5);

    expect($user->onGenericTrial())->toBeFalse();
});

it('we can check if a generic trial has expired', function () {
    $user = new User;

    $user->trial_ends_at = Carbon::yesterday();

    expect($user->onTrialExpired())->toBeTrue();
    expect($user->hasExpiredGenericTrial())->toBeTrue();
});
