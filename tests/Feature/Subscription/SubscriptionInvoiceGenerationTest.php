<?php

namespace Foundry\Tests\Feature\Subscription;

use App\Models\User;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;

class SubscriptionInvoiceGenerationTest extends TestCase
{
    public function test_does_not_generate_invoice_for_free_plan()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'price' => 0,
            'trial_days' => 0,
        ]);

        $subscription = $user->newSubscription('default', $plan);
        $subscription->saveAndInvoice();

        $this->assertCount(0, $subscription->invoices()->get());
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
    }

    public function test_does_not_generate_invoice_for_plan_with_negative_price()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'price' => -10,
            'trial_days' => 0,
        ]);

        $subscription = $user->newSubscription('default', $plan);
        $subscription->saveAndInvoice();

        $this->assertCount(0, $subscription->invoices()->get());
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
    }

    public function test_does_not_generate_invoice_for_free_forever()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'price' => 1000,
            'trial_days' => 0,
        ]);

        $subscription = $user->newSubscription('default', $plan);
        $subscription->is_free_forever = true;
        $subscription->saveAndInvoice();

        $this->assertCount(0, $subscription->invoices()->get());
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertTrue($subscription->active());
    }

    public function test_updates_existing_pending_invoice()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'price' => 1000,
            'trial_days' => 0,
        ]);

        $subscription = $user->newSubscription('default', $plan);
        $subscription->saveAndInvoice();

        $subscription->refresh();
        $this->assertCount(1, $subscription->invoices);
        $firstInvoice = $subscription->latestInvoice;
        $this->assertNotNull($firstInvoice, 'Latest invoice should not be null');
        $this->assertTrue($firstInvoice->isPendingPayment());

        // Update some metadata to simulate a change that should be reflected in the updated invoice
        $subscription->metadata = ['test' => 'updated'];
        $subscription->save();

        // Call generateInvoice again
        $subscription->generateInvoice();

        $this->assertCount(1, $subscription->invoices()->get(), 'Should not create a second invoice if the first one is pending');
        $this->assertEquals($firstInvoice->id, $subscription->latestInvoice->id);
    }

    public function test_subscribed_recognizes_free_forever()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'price' => 1000,
            'trial_days' => 0,
        ]);

        $subscription = $user->newSubscription('default', $plan);
        $subscription->is_free_forever = true;
        $subscription->save();

        $this->assertTrue($user->subscribed('default'));
    }
}
