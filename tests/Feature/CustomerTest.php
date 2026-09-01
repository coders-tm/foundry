<?php

uses(\Foundry\Tests\Feature\FeatureTestCase::class);

it('customer can be put on a generic trial', function () {
    $user = new \App\Models\User;

    $this->assertFalse($user->onGenericTrial());

    $user->trial_ends_at = \Carbon\Carbon::tomorrow();

    $this->assertTrue($user->onTrial());
    $this->assertTrue($user->onGenericTrial());

    $user->trial_ends_at = \Carbon\Carbon::today()->subDays(5);

    $this->assertFalse($user->onGenericTrial());
});

it('we can check if a generic trial has expired', function () {
    $user = new \App\Models\User;

    $user->trial_ends_at = \Carbon\Carbon::yesterday();

    $this->assertTrue($user->onTrialExpired());
    $this->assertTrue($user->hasExpiredGenericTrial());
});
