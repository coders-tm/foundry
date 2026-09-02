<?php

use App\Models\User;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;

uses(TestCase::class);

it('subscription can be frozen immediately', function () {
    // Create plan and subscription
    $plan = Plan::factory()->create(['price' => 2000]);
    $user = User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = SubscriptionStatus::ACTIVE;
    $subscription->save();

    $releaseAt = now()->addDays(60);
    $reason = 'Going abroad';

    // Freeze subscription
    $subscription->freeze($releaseAt, $reason, 200);

    expect($subscription->status)->toEqual(SubscriptionStatus::PAUSED);
    expect($subscription->frozen_at)->not->toBeNull();
    expect($subscription->release_at->format('Y-m-d'))->toEqual($releaseAt->format('Y-m-d'));
    expect($subscription->onFreeze())->toBeTrue();

    // Check log contains the reason
    $log = $subscription->logs()->where('message', 'LIKE', '%frozen%')->latest()->first();
    expect($log)->not->toBeNull();
    expect($log->message)->toContain($reason);
    expect($log->message)->toContain($releaseAt->format('Y-m-d'));
});

it('subscription can be unfrozen', function () {
    $plan = Plan::factory()->create(['price' => 2000]);
    $user = User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = SubscriptionStatus::ACTIVE;
    $subscription->save();

    // Freeze subscription
    $releaseAt = now()->addDays(30);
    $subscription->freeze($releaseAt, 'Testing');

    expect($subscription->onFreeze())->toBeTrue();

    // Unfreeze
    $subscription->unfreeze();

    expect($subscription->onFreeze())->toBeFalse();
    expect($subscription->status)->toEqual(SubscriptionStatus::ACTIVE);
    expect($subscription->frozen_at)->toBeNull();
    expect($subscription->release_at)->toBeNull();
    expect($subscription->freeze_reason)->toBeNull();
    expect($subscription->freeze_fee)->toBeNull();
});

it('freeze extends expires at', function () {
    // Create plan
    $plan = Plan::factory()->create([
        'price' => 10000,
        'interval' => 'month',
        'interval_count' => 1,
    ]);

    $user = User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = SubscriptionStatus::ACTIVE;
    $subscription->expires_at = now()->addYear();
    $subscription->save();

    $originalExpiresAt = $subscription->expires_at->copy();
    $frozenAt = now();

    // Freeze for 60 days
    $releaseAt = now()->addDays(60);
    $subscription->freeze($releaseAt);

    // Verify it's frozen
    expect($subscription->onFreeze())->toBeTrue();

    // Manually set frozen_at to 60 days ago to simulate passage of time
    $subscription->frozen_at = $frozenAt->copy()->subDays(60);
    $subscription->save();
    $subscription->refresh();

    // Unfreeze
    $subscription->unfreeze();

    // expires_at should be extended by 60 days
    expect($subscription->expires_at->format('Y-m-d'))->toEqual(
        $originalExpiresAt->addDays(60)->format('Y-m-d')
    );
});

it('cannot freeze if already frozen', function () {
    $plan = Plan::factory()->create(['price' => 2000]);
    $user = User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = SubscriptionStatus::ACTIVE;
    $subscription->save();

    // Freeze subscription
    $subscription->freeze(now()->addDays(30));

    expect($subscription->onFreeze())->toBeTrue();

    // Try to freeze again
    expect(fn () => $subscription->freeze(now()->addDays(60)))->toThrow(LogicException::class, 'Subscription cannot be frozen at this time');
});

it('cannot freeze canceled subscription', function () {
    $plan = Plan::factory()->create(['price' => 2000]);
    $user = User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = SubscriptionStatus::CANCELED;
    $subscription->cancels_at = now();
    $subscription->save();

    expect(fn () => $subscription->freeze(now()->addDays(30)))->toThrow(LogicException::class, 'Subscription cannot be frozen at this time');
});

it('frozen scope returns only frozen subscriptions', function () {
    $plan = Plan::factory()->create();
    $user = User::factory()->create();

    // Create active subscription
    $activeSubscription = $user->newSubscription('active', $plan->id)->saveWithoutInvoice();
    $activeSubscription->status = SubscriptionStatus::ACTIVE;
    $activeSubscription->save();

    // Create frozen subscription
    $frozenSubscription = $user->newSubscription('frozen', $plan->id)->saveWithoutInvoice();
    $frozenSubscription->status = SubscriptionStatus::PAUSED;
    $frozenSubscription->frozen_at = now();
    $frozenSubscription->release_at = now()->addDays(30);
    $frozenSubscription->save();

    expect(Subscription::frozen()->count())->toEqual(1);

    $frozen = Subscription::frozen()->first();
    expect($frozen->id)->toEqual($frozenSubscription->id);
});

it('due for unfreeze scope', function () {
    $plan = Plan::factory()->create();
    $user = User::factory()->create();

    // Create frozen subscription due for unfreeze
    $dueSubscription = $user->newSubscription('due', $plan->id)->saveWithoutInvoice();
    $dueSubscription->status = SubscriptionStatus::PAUSED;
    $dueSubscription->frozen_at = now()->subDays(30);
    $dueSubscription->release_at = now()->subDay(); // Past release date
    $dueSubscription->save();

    // Create frozen subscription not yet due
    $notDueSubscription = $user->newSubscription('notdue', $plan->id)->saveWithoutInvoice();
    $notDueSubscription->status = SubscriptionStatus::PAUSED;
    $notDueSubscription->frozen_at = now();
    $notDueSubscription->release_at = now()->addDays(30);
    $notDueSubscription->save();

    expect(Subscription::dueForUnfreeze()->count())->toEqual(1);

    $due = Subscription::dueForUnfreeze()->first();
    expect($due->id)->toEqual($dueSubscription->id);
});

it('freeze fee uses config default', function () {
    config(['foundry.subscription.freeze_fee' => 250.00]);

    $plan = Plan::factory()->create(['price' => 2000]);
    $user = User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = SubscriptionStatus::ACTIVE;
    $subscription->save();

    // Freeze without specifying fee
    $subscription->freeze(now()->addDays(30), 'Testing');

    // Verify invoice was created with the config fee
    $invoice = $subscription->invoices()->latest()->first();
    expect($invoice)->not->toBeNull();
    expect((float) $invoice->grand_total)->toEqual(250.00);
});

it('freeze can be disabled via config', function () {
    config(['foundry.subscription.allow_freeze' => false]);

    $plan = Plan::factory()->create(['price' => 2000]);
    $user = User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = SubscriptionStatus::ACTIVE;
    $subscription->save();

    expect($subscription->canFreeze())->toBeFalse();

    expect(fn () => $subscription->freeze(now()->addDays(30)))->toThrow(LogicException::class);
});
