<?php

uses(\Foundry\Tests\TestCase::class);

beforeEach(function () {
    $this->user = \Foundry\Models\User::factory()->create();
    $this->monthlyPlan = \Foundry\Models\Subscription\Plan::factory()->create([
        'label' => 'Monthly', 'interval' => 'month', 'interval_count' => 1, 'price' => 10.00, 'trial_days' => 0,
    ]);
    $this->yearlyPlan = \Foundry\Models\Subscription\Plan::factory()->create([
        'label' => 'Yearly', 'interval' => 'year', 'interval_count' => 1, 'price' => 100.00, 'trial_days' => 0,
    ]);
    $this->paymentMethod = \Foundry\Services\PaymentProvider::STRIPE;
});

it('update without mark as paid custom starts at auto calculates expires at', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $customStart = \Carbon\Carbon::parse('2026-06-01');
    $service = app(\Foundry\Services\Admin\SubscriptionService::class);
    $updated = $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id, 'starts_at' => $customStart->toDateTimeString()], $subscription);
    $this->assertEquals($customStart->format('Y-m-d'), $updated->starts_at->format('Y-m-d'));
    $this->assertEquals($customStart->copy()->addYear()->format('Y-m-d'), $updated->expires_at->format('Y-m-d'));
});

it('update without mark as paid preserves both custom dates', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $customStart = \Carbon\Carbon::parse('2026-06-01');
    $customExpiry = \Carbon\Carbon::parse('2026-09-01');
    $service = app(\Foundry\Services\Admin\SubscriptionService::class);
    $updated = $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id, 'starts_at' => $customStart->toDateTimeString(), 'expires_at' => $customExpiry->toDateTimeString()], $subscription);
    $this->assertEquals($customStart->format('Y-m-d'), $updated->starts_at->format('Y-m-d'));
    $this->assertEquals($customExpiry->format('Y-m-d'), $updated->expires_at->format('Y-m-d'));
});

it('update without mark as paid no custom dates sets expires at from starts at', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $service = app(\Foundry\Services\Admin\SubscriptionService::class);
    $updated = $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id], $subscription);
    $this->assertNotNull($updated->starts_at);
    $this->assertNotNull($updated->expires_at);
    $this->assertEquals($updated->starts_at->copy()->addYear()->format('Y-m-d'), $updated->expires_at->format('Y-m-d'));
});

it('update with mark as paid custom starts at auto calculates expires at', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $customStart = \Carbon\Carbon::parse('2026-06-01');
    $service = app(\Foundry\Services\Admin\SubscriptionService::class);
    $updated = $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id, 'starts_at' => $customStart->toDateTimeString(), 'mark_as_paid' => true, 'payment_method' => $this->paymentMethod], $subscription);
    $this->assertEquals($customStart->format('Y-m-d'), $updated->starts_at->format('Y-m-d'));
    $this->assertEquals($customStart->copy()->addYear()->format('Y-m-d'), $updated->expires_at->format('Y-m-d'));
    $this->assertEquals(\Foundry\Contracts\SubscriptionStatus::ACTIVE, $updated->status);
});

it('update with mark as paid preserves both custom dates', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $customStart = \Carbon\Carbon::parse('2026-06-01');
    $customExpiry = \Carbon\Carbon::parse('2026-09-01');
    $service = app(\Foundry\Services\Admin\SubscriptionService::class);
    $updated = $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id, 'starts_at' => $customStart->toDateTimeString(), 'expires_at' => $customExpiry->toDateTimeString(), 'mark_as_paid' => true, 'payment_method' => $this->paymentMethod], $subscription);
    $this->assertEquals($customStart->format('Y-m-d'), $updated->starts_at->format('Y-m-d'));
    $this->assertEquals($customExpiry->format('Y-m-d'), $updated->expires_at->format('Y-m-d'));
    $this->assertEquals(\Foundry\Contracts\SubscriptionStatus::ACTIVE, $updated->status);
});

it('update with mark as paid no custom dates sets expires at from starts at', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $service = app(\Foundry\Services\Admin\SubscriptionService::class);
    $updated = $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id, 'mark_as_paid' => true, 'payment_method' => $this->paymentMethod], $subscription);
    $this->assertNotNull($updated->starts_at);
    $this->assertNotNull($updated->expires_at);
    $this->assertEquals($updated->starts_at->copy()->addYear()->format('Y-m-d'), $updated->expires_at->format('Y-m-d'));
    $this->assertEquals(\Foundry\Contracts\SubscriptionStatus::ACTIVE, $updated->status);
});

it('database reflects correct dates after update', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $customStart = \Carbon\Carbon::parse('2026-07-01');
    $service = app(\Foundry\Services\Admin\SubscriptionService::class);
    $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id, 'starts_at' => $customStart->toDateTimeString(), 'mark_as_paid' => true, 'payment_method' => $this->paymentMethod], $subscription);
    $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'starts_at' => $customStart->format('Y-m-d H:i:s'), 'expires_at' => $customStart->copy()->addYear()->format('Y-m-d H:i:s'), 'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE]);
});
