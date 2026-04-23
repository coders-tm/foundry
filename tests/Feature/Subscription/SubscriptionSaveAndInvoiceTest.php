<?php

namespace Foundry\Tests\Feature\Subscription;

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Tests\TestCase;

class SubscriptionSaveAndInvoiceTest extends TestCase
{
    public function test_save_and_invoice_returns_subscription_instance()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000]);

        $subscription = new (Foundry::$subscriptionModel)([
            'user_id' => $user->id,
            'type' => 'default',
            'status' => SubscriptionStatus::ACTIVE,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        // Call saveAndInvoice and verify it returns the subscription instance
        $result = $subscription->saveAndInvoice();

        $this->assertInstanceOf(Foundry::$subscriptionModel, $result);
        $this->assertEquals($subscription->id, $result->id);
        $this->assertTrue($result->exists);
    }

    public function test_save_and_invoice_generates_invoice_when_not_on_trial()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000]);

        $subscription = new (Foundry::$subscriptionModel)([
            'user_id' => $user->id,
            'type' => 'default',
            'status' => SubscriptionStatus::ACTIVE,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        // Save and invoice
        $result = $subscription->saveAndInvoice()->refresh();

        // Verify subscription was saved
        $this->assertTrue($result->exists);

        // Verify invoice was generated
        $invoice = $result->latestInvoice;
        $this->assertNotNull($invoice);
        $this->assertInstanceOf(Order::class, $invoice);
    }

    public function test_save_and_invoice_skips_invoice_generation_when_on_trial()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000]);

        $subscription = new (Foundry::$subscriptionModel)([
            'user_id' => $user->id,
            'type' => 'default',
            'status' => SubscriptionStatus::TRIALING,
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addDays(14),
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        // Save and invoice
        $result = $subscription->saveAndInvoice()->refresh();

        // Verify subscription was saved
        $this->assertTrue($result->exists);
        $this->assertTrue($result->onTrial());

        // Verify invoice was NOT generated (because on trial)
        $invoice = $result->latestInvoice;
        $this->assertNull($invoice);
    }

    public function test_save_and_invoice_forces_invoice_generation_when_on_trial()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000]);

        $subscription = new (Foundry::$subscriptionModel)([
            'user_id' => $user->id,
            'type' => 'default',
            'status' => SubscriptionStatus::TRIALING,
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addDays(14),
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        // Save and invoice with force=true
        $result = $subscription->saveAndInvoice([], true)->refresh();

        // Verify subscription was saved
        $this->assertTrue($result->exists);
        $this->assertTrue($result->onTrial());

        // Verify invoice WAS generated (because forced)
        $invoice = $result->latestInvoice;
        $this->assertNotNull($invoice);
        $this->assertInstanceOf(Order::class, $invoice);
    }

    public function test_save_and_invoice_can_be_chained()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000]);

        $subscription = new (Foundry::$subscriptionModel)([
            'user_id' => $user->id,
            'type' => 'default',
            'status' => SubscriptionStatus::ACTIVE,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        // Test method chaining
        $result = $subscription
            ->saveAndInvoice()
            ->refresh();

        $this->assertInstanceOf(Foundry::$subscriptionModel, $result);
        $this->assertTrue($result->exists);
    }

    public function test_save_and_invoice_preserves_trialing_status_from_new_subscription()
    {
        // 1. Setup User and Plan with trial days
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create([
            'trial_days' => 14,
            'interval' => 'month',
            'price' => 1000,
        ]);

        // 2. Create new subscription (initializes as TRIALING)
        $subscription = $user->newSubscription('default', $plan);
        $this->assertEquals(SubscriptionStatus::TRIALING, $subscription->status);
        $this->assertTrue($subscription->onTrial());

        // 3. Call saveAndInvoice
        // This should trigger generateInvoice, but due to our fix, it should return early
        $subscription->saveAndInvoice();

        // 4. Assert Status is still TRIALING
        $this->assertEquals(SubscriptionStatus::TRIALING, $subscription->status, 'Subscription status should remain TRIALING');

        // 5. Assert No Invoice Generated
        // Since generateInvoice returns null when on trial, no invoice should be created yet.
        $this->assertCount(0, $subscription->invoices()->get(), 'No invoice should be generated during trial');
    }
}
