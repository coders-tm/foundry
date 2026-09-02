<?php

use App\Models\User;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Feature;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;

uses(TestCase::class);

it('subscription features are reset when plan is swapped via api route', function () {
    // Create features that would be in different plans
    $basicFeature = Feature::factory()->create([
        'slug' => 'basic-users',
        'label' => 'Basic Users',
        'type' => 'integer',
    ]);

    $proFeature = Feature::factory()->create([
        'slug' => 'pro-analytics',
        'label' => 'Pro Analytics',
        'type' => 'boolean',
    ]);

    // Create Basic Plan with 5 users
    $basicPlan = Plan::create([
        'label' => 'Basic Plan',
        'price' => 1000, // $10
        'interval' => 'month',
        'interval_count' => 1,
    ]);
    $basicPlan->features()->attach([
        $basicFeature->id => ['value' => 5],
    ]);

    // Create Pro Plan with 20 users and pro analytics
    $proPlan = Plan::create([
        'label' => 'Pro Plan',
        'price' => 5000, // $50
        'interval' => 'month',
        'interval_count' => 1,
    ]);
    $proPlan->features()->attach([
        $basicFeature->id => ['value' => 20],
        $proFeature->id => ['value' => 1],
    ]);

    // Create user with subscription to Basic Plan
    $user = User::factory()->create();
    $subscription = $user->newSubscription('default', $basicPlan->id)
        ->saveWithoutInvoice();

    // Verify initial state
    expect($subscription->plan_id)->toEqual($basicPlan->id);
    expect($subscription->features)->toHaveCount(1);
    expect($subscription->getFeatureValue('basic-users'))->toEqual(5);
    expect($subscription->getFeatureValue('pro-analytics'))->toBeNull();

    // User uses 3 out of 5 basic users
    $subscription->recordFeatureUsage('basic-users', 3);
    expect($subscription->getFeatureUsage('basic-users'))->toEqual(3);

    // ===== UPGRADE TO PRO PLAN (simulating /api/subscription/subscribe or /api/users/{user}/subscription) =====
    $subscription->swap($proPlan->id, false);

    // Refresh to get the latest data from database
    $subscription->refresh();

    // VERIFICATION: Features should be reset from the swapped plan
    expect($subscription->plan_id)->toEqual($proPlan->id);

    // Should have 2 features now (basic-users and pro-analytics)
    expect($subscription->features)->toHaveCount(2);

    // Feature values should be from Pro plan
    expect($subscription->getFeatureValue('basic-users'))->toEqual(20);
    expect($subscription->getFeatureValue('pro-analytics'))->toEqual(1);

    // Usage should be reset to 0
    expect($subscription->getFeatureUsage('basic-users'))->toEqual(0);
});

it('subscription features are reset when downgrading from pro to basic', function () {
    // Create features
    $storageFeature = Feature::factory()->create([
        'slug' => 'storage-gb',
        'label' => 'Storage GB',
        'type' => 'integer',
    ]);

    $premiumSupportFeature = Feature::factory()->create([
        'slug' => 'premium-support',
        'label' => 'Premium Support',
        'type' => 'boolean',
    ]);

    // Create Pro Plan with 100GB storage and premium support
    $proPlan = Plan::create([
        'label' => 'Pro Plan',
        'price' => 5000,
        'interval' => 'month',
        'interval_count' => 1,
    ]);
    $proPlan->features()->attach([
        $storageFeature->id => ['value' => 100],
        $premiumSupportFeature->id => ['value' => 1],
    ]);

    // Create Basic Plan with only 10GB storage
    $basicPlan = Plan::create([
        'label' => 'Basic Plan',
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
    ]);
    $basicPlan->features()->attach([
        $storageFeature->id => ['value' => 10],
    ]);

    // Create user with Pro subscription
    $user = User::factory()->create();
    $subscription = $user->newSubscription('default', $proPlan->id)
        ->saveWithoutInvoice();

    // Use 50GB of storage
    $subscription->recordFeatureUsage('storage-gb', 50);
    expect($subscription->getFeatureUsage('storage-gb'))->toEqual(50);

    // ===== DOWNGRADE TO BASIC PLAN =====
    $subscription->swap($basicPlan->id, false);
    $subscription->refresh();

    // VERIFICATION: Features should be reset from the downgraded plan
    expect($subscription->plan_id)->toEqual($basicPlan->id);

    // Should only have 1 feature (storage-gb), premium-support should be removed
    expect($subscription->features)->toHaveCount(1);

    // Storage should be reduced to 10GB
    expect($subscription->getFeatureValue('storage-gb'))->toEqual(10);

    // Premium support should be removed
    expect($subscription->getFeatureValue('premium-support'))->toBeNull();

    // Usage should be reset to 0
    expect($subscription->getFeatureUsage('storage-gb'))->toEqual(0);
});

it('scheduled downgrade syncs features on renewal', function () {
    // Create features
    $apiCallsFeature = Feature::factory()->create([
        'slug' => 'api-calls',
        'label' => 'API Calls',
        'type' => 'integer',
    ]);

    $webhooksFeature = Feature::factory()->create([
        'slug' => 'webhooks',
        'label' => 'Webhooks',
        'type' => 'boolean',
    ]);

    // Pro Plan
    $proPlan = Plan::create([
        'label' => 'Pro Plan',
        'price' => 5000,
        'interval' => 'month',
        'interval_count' => 1,
    ]);
    $proPlan->features()->attach([
        $apiCallsFeature->id => ['value' => 10000],
        $webhooksFeature->id => ['value' => 1],
    ]);

    // Basic Plan (no webhooks)
    $basicPlan = Plan::create([
        'label' => 'Basic Plan',
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
    ]);
    $basicPlan->features()->attach([
        $apiCallsFeature->id => ['value' => 1000],
    ]);

    // Create Pro subscription
    $user = User::factory()->create();
    $subscription = $user->newSubscription('default', $proPlan->id)
        ->saveWithoutInvoice();

    // Use some API calls
    $subscription->recordFeatureUsage('api-calls', 5000);

    // Schedule a downgrade (simulating the downgrade controller method)
    $subscription->update([
        'next_plan' => $basicPlan->id,
        'is_downgrade' => true,
        'expires_at' => now()->subDay(), // Subscription expired, ready for renewal
    ]);

    // ===== RENEW SUBSCRIPTION (which applies the downgrade) =====
    $subscription->renew();
    $subscription->refresh();

    // VERIFICATION: Features should be synced from the downgraded plan
    expect($subscription->plan_id)->toEqual($basicPlan->id);

    // Should only have 1 feature
    expect($subscription->features)->toHaveCount(1);

    // API calls should be reduced
    expect($subscription->getFeatureValue('api-calls'))->toEqual(1000);

    // Webhooks should be removed
    expect($subscription->getFeatureValue('webhooks'))->toBeNull();

    // Usage should be reset
    expect($subscription->getFeatureUsage('api-calls'))->toEqual(0);
});

it('swap subscription syncs feature values from new plan', function () {
    $user = User::factory()->create();
    $planA = Plan::factory()->create(['trial_days' => 0]);
    $planB = Plan::factory()->create(['trial_days' => 0]);

    // Create a feature
    $feature = Feature::factory()->create([
        'slug' => 'test-limit',
        'type' => 'integer',
        'resetable' => true,
        'label' => 'Test Limit',
    ]);

    // Attach feature to Plan A with value 10
    $planA->features()->attach($feature->id, ['value' => 10]);
    // Attach feature to Plan B with value 20
    $planB->features()->attach($feature->id, ['value' => 20]);

    // Create subscription on Plan A
    $subscription = $user->newSubscription('default', $planA->id)
        ->saveAndInvoice([], true);

    // Verify initial state
    expect($subscription->getFeatureValue('test-limit'))->toEqual(10);
    expect($subscription->getFeatureUsage('test-limit'))->toEqual(0);

    // Record usage
    $subscription->recordFeatureUsage('test-limit', 5);
    expect($subscription->getFeatureUsage('test-limit'))->toEqual(5);

    // Swap to Plan B
    $subscription->swap($planB->id);

    // Verify state after swap
    expect($subscription->getFeatureUsage('test-limit'))->toEqual(0);

    // Feature value should be updated to Plan B's value (20)
    expect($subscription->getFeatureValue('test-limit'))->toEqual(20);
});

it('subscription features are created on subscription creation', function () {
    // Create a feature
    $feature = Feature::factory()->create([
        'slug' => 'test-feature',
        'type' => 'integer',
        'resetable' => true,
    ]);

    // Create a plan without auto-syncing features
    $plan = new Plan([
        'label' => 'Test Plan',
        'slug' => 'test-plan',
        'description' => 'A test plan',
        'is_active' => true,
        'default_interval' => 'month',
        'interval' => 'month',
        'interval_count' => 1,
        'price' => 1000,
        'trial_days' => 0,
        'options' => null,
    ]);
    $plan->save();

    // Attach feature to plan
    $plan->features()->attach($feature, ['value' => 10]);

    // Create a subscription
    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
    ]);

    // Set status to active after creation (since generateInvoice sets it to pending)
    $subscription->update(['status' => 'active']);

    // Refresh the subscription to get the latest data
    $subscription->refresh();

    // Check if subscription features were created
    expect($subscription->features)->toHaveCount(1);

    $subscriptionFeature = $subscription->features->first();
    expect($subscriptionFeature->slug)->toEqual($feature->slug);
    expect($subscriptionFeature->label)->toEqual($feature->label);
    expect($subscriptionFeature->value)->toEqual(10);
    expect($subscriptionFeature->used)->toEqual(0);
});

it('can use feature works with subscription features', function () {
    // Create a feature
    $feature = Feature::factory()->create([
        'slug' => 'test-feature',
        'type' => 'integer',
        'resetable' => true,
    ]);

    // Create a plan without auto-syncing features
    $plan = new Plan([
        'label' => 'Test Plan',
        'slug' => 'test-plan-2',
        'description' => 'A test plan',
        'is_active' => true,
        'default_interval' => 'month',
        'interval' => 'month',
        'interval_count' => 1,
        'price' => 1000,
        'trial_days' => 0,
        'options' => null,
    ]);
    $plan->save();

    // Attach feature to plan
    $plan->features()->attach($feature, ['value' => 10]);

    // Create a subscription
    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
    ]);

    // Set status to active after creation (since generateInvoice sets it to pending)
    $subscription->update(['status' => 'active']);

    // Refresh the subscription to get the latest data
    $subscription->refresh();

    // Test canUseFeature
    expect($subscription->canUseFeature($feature->slug))->toBeTrue();
});

it('record feature usage works with subscription features', function () {
    // Create a feature
    $feature = Feature::factory()->create([
        'slug' => 'test-feature',
        'type' => 'integer',
        'resetable' => true,
    ]);

    // Create a plan without auto-syncing features
    $plan = new Plan([
        'label' => 'Test Plan',
        'slug' => 'test-plan-3',
        'description' => 'A test plan',
        'is_active' => true,
        'default_interval' => 'month',
        'interval' => 'month',
        'interval_count' => 1,
        'price' => 1000,
        'trial_days' => 0,
        'options' => null,
    ]);
    $plan->save();

    // Attach feature to plan
    $plan->features()->attach($feature, ['value' => 10]);

    // Create a subscription
    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
    ]);

    // Set status to active after creation (since generateInvoice sets it to pending)
    $subscription->update(['status' => 'active']);

    // Refresh the subscription to get the latest data
    $subscription->refresh();

    // Record feature usage
    $subscription->recordFeatureUsage($feature->slug, 3);

    // Check usage
    expect($subscription->getFeatureUsage($feature->slug))->toEqual(3);
    expect($subscription->getFeatureRemainings($feature->slug))->toEqual(7);
});

it('reduce feature usage works with subscription features', function () {
    // Create a feature
    $feature = Feature::factory()->create([
        'slug' => 'test-feature',
        'type' => 'integer',
        'resetable' => true,
    ]);

    // Create a plan without auto-syncing features
    $plan = new Plan([
        'label' => 'Test Plan',
        'slug' => 'test-plan-4',
        'description' => 'A test plan',
        'is_active' => true,
        'default_interval' => 'month',
        'interval' => 'month',
        'interval_count' => 1,
        'price' => 1000,
        'trial_days' => 0,
        'options' => null,
    ]);
    $plan->save();

    // Attach feature to plan
    $plan->features()->attach($feature, ['value' => 10]);

    // Create a subscription
    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
    ]);

    // Set status to active after creation (since generateInvoice sets it to pending)
    $subscription->update(['status' => 'active']);

    // Refresh the subscription to get the latest data
    $subscription->refresh();

    // Record feature usage
    $subscription->recordFeatureUsage($feature->slug, 5);

    // Reduce feature usage
    $subscription->reduceFeatureUsage($feature->slug, 2);

    // Check usage
    expect($subscription->getFeatureUsage($feature->slug))->toEqual(3);
    expect($subscription->getFeatureRemainings($feature->slug))->toEqual(7);
});

it('reset usages works with subscription features', function () {
    // Create a feature
    $feature = Feature::factory()->create([
        'slug' => 'test-feature',
        'type' => 'integer',
        'resetable' => true,
    ]);

    // Create a plan without auto-syncing features
    $plan = new Plan([
        'label' => 'Test Plan',
        'slug' => 'test-plan-5',
        'description' => 'A test plan',
        'is_active' => true,
        'default_interval' => 'month',
        'interval' => 'month',
        'interval_count' => 1,
        'price' => 1000,
        'trial_days' => 0,
        'options' => null,
    ]);
    $plan->save();

    // Attach feature to plan
    $plan->features()->attach($feature, ['value' => 10]);

    // Create a subscription
    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
    ]);

    // Set status to active after creation (since generateInvoice sets it to pending)
    $subscription->update(['status' => 'active']);

    // Refresh the subscription to get the latest data
    $subscription->refresh();

    // Record feature usage
    $subscription->recordFeatureUsage($feature->slug, 5);

    // Reset usages
    $subscription->resetUsages();

    // Check usage
    expect($subscription->getFeatureUsage($feature->slug))->toEqual(0);
    expect($subscription->getFeatureRemainings($feature->slug))->toEqual(10);
});

it('cannot use feature with expired subscription', function () {
    $subscription = Subscription::factory()->create([
        'status' => 'expired',
        'expires_at' => now()->subDay(),
    ]);

    // Get the first subscription feature
    $subscriptionFeature = $subscription->features->first();
    expect($subscriptionFeature)->not->toBeNull();

    // Even though the feature has remaining usage, it should not be usable
    // because the subscription itself is expired/invalid
    expect($subscription->canUseFeature($subscriptionFeature->slug))->toBeFalse();
});

it('swap updates billing interval', function () {
    // Create user
    $user = User::factory()->create();

    // Create monthly plan
    $monthlyPlan = Plan::create([
        'label' => 'Monthly Plan',
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
    ]);

    // Create yearly plan
    $yearlyPlan = Plan::create([
        'label' => 'Yearly Plan',
        'price' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
    ]);

    // Create quarterly plan
    $quarterlyPlan = Plan::create([
        'label' => 'Quarterly Plan',
        'price' => 2500,
        'interval' => 'month',
        'interval_count' => 3,
    ]);

    // Create subscription with monthly plan
    $subscription = $user->newSubscription('default', $monthlyPlan->id)
        ->saveWithoutInvoice();

    // Verify initial state
    expect($subscription->billing_interval)->toEqual('month');
    expect($subscription->billing_interval_count)->toEqual(1);

    // Swap to yearly plan
    $subscription->swap($yearlyPlan->id, false);
    $subscription->refresh();

    // Verify billing interval is updated
    expect($subscription->billing_interval)->toEqual('year');
    expect($subscription->billing_interval_count)->toEqual(1);

    // Swap to quarterly plan
    $subscription->swap($quarterlyPlan->id, false);
    $subscription->refresh();

    // Verify fields are updated again
    expect($subscription->billing_interval)->toEqual('month');
    expect($subscription->billing_interval_count)->toEqual(3);
});

it('force swap updates billing interval', function () {
    // Create user
    $user = User::factory()->create();

    // Create monthly plan
    $monthlyPlan = Plan::create([
        'label' => 'Monthly Plan',
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
    ]);

    // Create yearly plan
    $yearlyPlan = Plan::create([
        'label' => 'Yearly Plan',
        'price' => 5000,
        'interval' => 'year',
        'interval_count' => 1,
    ]);

    // Create subscription
    $subscription = $user->newSubscription('default', $monthlyPlan->id)
        ->saveWithoutInvoice();

    // Admin force swap to yearly plan
    $subscription->forceSwap($yearlyPlan->id, false);
    $subscription->refresh();

    // Verify all fields are updated
    expect($subscription->billing_interval)->toEqual('year');
    expect($subscription->billing_interval_count)->toEqual(1);
    expect($subscription->plan_id)->toEqual($yearlyPlan->id);
});
