<?php

use Carbon\Carbon;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Services\Admin\SubscriptionService;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->monthlyPlan = Plan::factory()->create([
        'label' => 'Monthly', 'interval' => 'month', 'interval_count' => 1, 'price' => 10.00, 'trial_days' => 0,
    ]);
    $this->yearlyPlan = Plan::factory()->create([
        'label' => 'Yearly', 'interval' => 'year', 'interval_count' => 1, 'price' => 100.00, 'trial_days' => 0,
    ]);
    $this->paymentMethod = PaymentProvider::STRIPE;
});

it('update without mark as paid custom starts at auto calculates expires at', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $customStart = Carbon::parse('2026-06-01');
    $service = app(SubscriptionService::class);
    $updated = $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id, 'starts_at' => $customStart->toDateTimeString()], $subscription);
    expect($updated->starts_at->format('Y-m-d'))->toEqual($customStart->format('Y-m-d'));
    expect($updated->expires_at->format('Y-m-d'))->toEqual($customStart->copy()->addYear()->format('Y-m-d'));
});

it('update without mark as paid preserves both custom dates', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $customStart = Carbon::parse('2026-06-01');
    $customExpiry = Carbon::parse('2026-09-01');
    $service = app(SubscriptionService::class);
    $updated = $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id, 'starts_at' => $customStart->toDateTimeString(), 'expires_at' => $customExpiry->toDateTimeString()], $subscription);
    expect($updated->starts_at->format('Y-m-d'))->toEqual($customStart->format('Y-m-d'));
    expect($updated->expires_at->format('Y-m-d'))->toEqual($customExpiry->format('Y-m-d'));
});

it('update without mark as paid no custom dates sets expires at from starts at', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $service = app(SubscriptionService::class);
    $updated = $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id], $subscription);
    expect($updated->starts_at)->not->toBeNull();
    expect($updated->expires_at)->not->toBeNull();
    expect($updated->expires_at->format('Y-m-d'))->toEqual($updated->starts_at->copy()->addYear()->format('Y-m-d'));
});

it('update with mark as paid custom starts at auto calculates expires at', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $customStart = Carbon::parse('2026-06-01');
    $service = app(SubscriptionService::class);
    $updated = $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id, 'starts_at' => $customStart->toDateTimeString(), 'mark_as_paid' => true, 'payment_method' => $this->paymentMethod], $subscription);
    expect($updated->starts_at->format('Y-m-d'))->toEqual($customStart->format('Y-m-d'));
    expect($updated->expires_at->format('Y-m-d'))->toEqual($customStart->copy()->addYear()->format('Y-m-d'));
    expect($updated->status)->toEqual(SubscriptionStatus::ACTIVE);
});

it('update with mark as paid preserves both custom dates', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $customStart = Carbon::parse('2026-06-01');
    $customExpiry = Carbon::parse('2026-09-01');
    $service = app(SubscriptionService::class);
    $updated = $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id, 'starts_at' => $customStart->toDateTimeString(), 'expires_at' => $customExpiry->toDateTimeString(), 'mark_as_paid' => true, 'payment_method' => $this->paymentMethod], $subscription);
    expect($updated->starts_at->format('Y-m-d'))->toEqual($customStart->format('Y-m-d'));
    expect($updated->expires_at->format('Y-m-d'))->toEqual($customExpiry->format('Y-m-d'));
    expect($updated->status)->toEqual(SubscriptionStatus::ACTIVE);
});

it('update with mark as paid no custom dates sets expires at from starts at', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $service = app(SubscriptionService::class);
    $updated = $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id, 'mark_as_paid' => true, 'payment_method' => $this->paymentMethod], $subscription);
    expect($updated->starts_at)->not->toBeNull();
    expect($updated->expires_at)->not->toBeNull();
    expect($updated->expires_at->format('Y-m-d'))->toEqual($updated->starts_at->copy()->addYear()->format('Y-m-d'));
    expect($updated->status)->toEqual(SubscriptionStatus::ACTIVE);
});

it('database reflects correct dates after update', function () {
    $subscription = Subscription::factory()->create(['user_id' => $this->user->id, 'plan_id' => $this->monthlyPlan->id, 'status' => SubscriptionStatus::ACTIVE, 'starts_at' => now()->subMonth(), 'expires_at' => now()->addDays(5)]);
    $customStart = Carbon::parse('2026-07-01');
    $service = app(SubscriptionService::class);
    $service->createOrUpdate($this->user, ['plan' => $this->yearlyPlan->id, 'starts_at' => $customStart->toDateTimeString(), 'mark_as_paid' => true, 'payment_method' => $this->paymentMethod], $subscription);
    $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'starts_at' => $customStart->format('Y-m-d H:i:s'), 'expires_at' => $customStart->copy()->addYear()->format('Y-m-d H:i:s'), 'status' => SubscriptionStatus::ACTIVE]);
});
