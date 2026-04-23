<?php

namespace Foundry\Tests\Feature\Subscription;

use App\Models\User;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Feature;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;

class SubscriptionFeatureTest extends TestCase
{
    public function test_subscription_features_are_reset_when_plan_is_swapped_via_api_route()
    {
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
        $this->assertEquals($basicPlan->id, $subscription->plan_id);
        $this->assertCount(1, $subscription->features);
        $this->assertEquals(5, $subscription->getFeatureValue('basic-users'));
        $this->assertNull($subscription->getFeatureValue('pro-analytics'));

        // User uses 3 out of 5 basic users
        $subscription->recordFeatureUsage('basic-users', 3);
        $this->assertEquals(3, $subscription->getFeatureUsage('basic-users'));

        // ===== UPGRADE TO PRO PLAN (simulating /api/subscription/subscribe or /api/users/{user}/subscription) =====
        $subscription->swap($proPlan->id, false);

        // Refresh to get the latest data from database
        $subscription->refresh();

        // VERIFICATION: Features should be reset from the swapped plan
        $this->assertEquals($proPlan->id, $subscription->plan_id, 'Plan should be updated to Pro');

        // Should have 2 features now (basic-users and pro-analytics)
        $this->assertCount(2, $subscription->features, 'Should have 2 features from Pro plan');

        // Feature values should be from Pro plan
        $this->assertEquals(20, $subscription->getFeatureValue('basic-users'), 'Basic users should be 20 from Pro plan');
        $this->assertEquals(1, $subscription->getFeatureValue('pro-analytics'), 'Pro analytics should be available');

        // Usage should be reset to 0
        $this->assertEquals(0, $subscription->getFeatureUsage('basic-users'), 'Usage should be reset to 0 after swap');
    }

    public function test_subscription_features_are_reset_when_downgrading_from_pro_to_basic()
    {
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
        $this->assertEquals(50, $subscription->getFeatureUsage('storage-gb'));

        // ===== DOWNGRADE TO BASIC PLAN =====
        $subscription->swap($basicPlan->id, false);
        $subscription->refresh();

        // VERIFICATION: Features should be reset from the downgraded plan
        $this->assertEquals($basicPlan->id, $subscription->plan_id, 'Plan should be downgraded to Basic');

        // Should only have 1 feature (storage-gb), premium-support should be removed
        $this->assertCount(1, $subscription->features, 'Should only have 1 feature from Basic plan');

        // Storage should be reduced to 10GB
        $this->assertEquals(10, $subscription->getFeatureValue('storage-gb'), 'Storage should be 10GB from Basic plan');

        // Premium support should be removed
        $this->assertNull($subscription->getFeatureValue('premium-support'), 'Premium support should be removed');

        // Usage should be reset to 0
        $this->assertEquals(0, $subscription->getFeatureUsage('storage-gb'), 'Usage should be reset to 0 after downgrade');
    }

    public function test_scheduled_downgrade_syncs_features_on_renewal()
    {
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
        $this->assertEquals($basicPlan->id, $subscription->plan_id, 'Plan should be downgraded to Basic on renewal');

        // Should only have 1 feature
        $this->assertCount(1, $subscription->features, 'Should only have 1 feature from Basic plan');

        // API calls should be reduced
        $this->assertEquals(1000, $subscription->getFeatureValue('api-calls'), 'API calls should be 1000 from Basic plan');

        // Webhooks should be removed
        $this->assertNull($subscription->getFeatureValue('webhooks'), 'Webhooks should be removed');

        // Usage should be reset
        $this->assertEquals(0, $subscription->getFeatureUsage('api-calls'), 'Usage should be reset on renewal');
    }

    public function test_swap_subscription_syncs_feature_values_from_new_plan()
    {
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
        $this->assertEquals(10, $subscription->getFeatureValue('test-limit'), 'Initial feature value should be 10');
        $this->assertEquals(0, $subscription->getFeatureUsage('test-limit'), 'Initial feature usage should be 0');

        // Record usage
        $subscription->recordFeatureUsage('test-limit', 5);
        $this->assertEquals(5, $subscription->getFeatureUsage('test-limit'), 'Usage should be 5');

        // Swap to Plan B
        $subscription->swap($planB->id);

        // Verify state after swap
        $this->assertEquals(0, $subscription->getFeatureUsage('test-limit'), 'Usage should be reset to 0 after swap');

        // Feature value should be updated to Plan B's value (20)
        $this->assertEquals(20, $subscription->getFeatureValue('test-limit'), 'Feature value should update to 20 after swap');
    }

    public function test_subscription_features_are_created_on_subscription_creation()
    {
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
        $this->assertCount(1, $subscription->features);

        $subscriptionFeature = $subscription->features->first();
        $this->assertEquals($feature->slug, $subscriptionFeature->slug);
        $this->assertEquals($feature->label, $subscriptionFeature->label);
        $this->assertEquals(10, $subscriptionFeature->value);
        $this->assertEquals(0, $subscriptionFeature->used);
    }

    public function test_can_use_feature_works_with_subscription_features()
    {
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
        $this->assertTrue($subscription->canUseFeature($feature->slug));
    }

    public function test_record_feature_usage_works_with_subscription_features()
    {
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
        $this->assertEquals(3, $subscription->getFeatureUsage($feature->slug));
        $this->assertEquals(7, $subscription->getFeatureRemainings($feature->slug));
    }

    public function test_reduce_feature_usage_works_with_subscription_features()
    {
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
        $this->assertEquals(3, $subscription->getFeatureUsage($feature->slug));
        $this->assertEquals(7, $subscription->getFeatureRemainings($feature->slug));
    }

    public function test_reset_usages_works_with_subscription_features()
    {
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
        $this->assertEquals(0, $subscription->getFeatureUsage($feature->slug));
        $this->assertEquals(10, $subscription->getFeatureRemainings($feature->slug));
    }

    public function test_cannot_use_feature_with_expired_subscription()
    {
        $subscription = Subscription::factory()->create([
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);

        // Get the first subscription feature
        $subscriptionFeature = $subscription->features->first();
        $this->assertNotNull($subscriptionFeature, 'No subscription features found');

        // Even though the feature has remaining usage, it should not be usable
        // because the subscription itself is expired/invalid
        $this->assertFalse($subscription->canUseFeature($subscriptionFeature->slug));
    }

    public function test_swap_updates_billing_interval_and_contract_fields()
    {
        // Create user
        $user = User::factory()->create();

        // Create monthly non-contract plan
        $monthlyPlan = Plan::create([
            'label' => 'Monthly Plan',
            'price' => 1000,
            'interval' => 'month',
            'interval_count' => 1,
            'is_contract' => false,
            'contract_cycles' => null,
        ]);

        // Create yearly contract plan (12 month contract)
        $yearlyContractPlan = Plan::create([
            'label' => 'Yearly Contract',
            'price' => 10000,
            'interval' => 'year',
            'interval_count' => 1,
            'is_contract' => true,
            'contract_cycles' => 12, // 12 billing cycles
        ]);

        // Create quarterly plan
        $quarterlyPlan = Plan::create([
            'label' => 'Quarterly Plan',
            'price' => 2500,
            'interval' => 'month',
            'interval_count' => 3,
            'is_contract' => false,
            'contract_cycles' => null,
        ]);

        // Create subscription with monthly plan
        $subscription = $user->newSubscription('default', $monthlyPlan->id)
            ->saveWithoutInvoice();

        // Verify initial state
        $this->assertEquals('month', $subscription->billing_interval);
        $this->assertEquals(1, $subscription->billing_interval_count);
        $this->assertNull($subscription->total_cycles);
        $this->assertEquals(0, $subscription->current_cycle);

        // Swap to yearly contract plan
        $subscription->swap($yearlyContractPlan->id, false);
        $subscription->refresh();

        // Verify billing interval and contract fields are updated
        $this->assertEquals('year', $subscription->billing_interval, 'Billing interval should be updated to year');
        $this->assertEquals(1, $subscription->billing_interval_count, 'Billing interval count should be 1');
        $this->assertEquals(12, $subscription->total_cycles, 'Total cycles should be set from plan contract_cycles');
        $this->assertEquals(0, $subscription->current_cycle, 'Current cycle should be reset to 0');

        // Record some cycles
        $subscription->current_cycle = 5;
        $subscription->save();

        // Swap to quarterly plan
        $subscription->swap($quarterlyPlan->id, false);
        $subscription->refresh();

        // Verify all fields are updated again
        $this->assertEquals('month', $subscription->billing_interval, 'Billing interval should be month');
        $this->assertEquals(3, $subscription->billing_interval_count, 'Billing interval count should be 3');
        $this->assertNull($subscription->total_cycles, 'Total cycles should be null (no contract)');
        $this->assertEquals(0, $subscription->current_cycle, 'Current cycle should be reset to 0 on swap');
    }

    public function test_force_swap_updates_billing_interval_and_contract_fields()
    {
        // Create user
        $user = User::factory()->create();

        // Create monthly plan
        $monthlyPlan = Plan::create([
            'label' => 'Monthly Plan',
            'price' => 1000,
            'interval' => 'month',
            'interval_count' => 1,
            'is_contract' => false,
            'contract_cycles' => null,
        ]);

        // Create contract plan
        $contractPlan = Plan::create([
            'label' => 'Contract Plan',
            'price' => 5000,
            'interval' => 'month',
            'interval_count' => 1,
            'is_contract' => true,
            'contract_cycles' => 6, // 6 month contract
        ]);

        // Create subscription
        $subscription = $user->newSubscription('default', $monthlyPlan->id)
            ->saveWithoutInvoice();

        // Simulate some progress
        $subscription->current_cycle = 3;
        $subscription->save();

        // Admin force swap to contract plan
        $subscription->forceSwap($contractPlan->id, false);
        $subscription->refresh();

        // Verify all fields are updated
        $this->assertEquals('month', $subscription->billing_interval);
        $this->assertEquals(1, $subscription->billing_interval_count);
        $this->assertEquals(6, $subscription->total_cycles, 'Total cycles should be set from contract plan');
        $this->assertEquals(0, $subscription->current_cycle, 'Current cycle should be reset to 0');
        $this->assertEquals($contractPlan->id, $subscription->plan_id);
    }
}
