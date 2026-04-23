<?php

namespace Foundry\Tests\Feature\Subscription;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;

class PropagatesOnOrderPaidTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure app.currency is set for these tests
        config(['app.currency' => 'USD']);
    }

    protected function createPlan(string $interval = 'month', int $count = 1): Plan
    {
        $plan = new Plan([
            'label' => 'Test Plan',
            'slug' => 'test-plan',
            'description' => 'A test plan',
            'is_active' => true,
            'default_interval' => $interval,
            'interval' => $interval,
            'interval_count' => $count,
            'price' => 1000,
            'trial_days' => 0,
            'options' => null,
        ]);
        $plan->save();

        return $plan;
    }

    protected function createSubscriptionWithOpenInvoice(bool $pastDue = true): array
    {
        $plan = $this->createPlan('month', 1);
        $subscription = Subscription::factory()->create([
            'plan_id' => $plan->id,
            'status' => $pastDue ? Subscription::EXPIRED : Subscription::PENDING,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        // Create an open invoice linked to the subscription with a clear period description
        $start = Carbon::now()->subMonth()->startOfDay();
        $end = (clone $start)->addMonth();
        $description = $start->format('M d, Y').' - '.$end->format('M d, Y');

        $order = Order::create([
            'customer_id' => $subscription->user_id,
            'orderable_id' => $subscription->id,
            'orderable_type' => Subscription::class,
            'collect_tax' => false,
            'source' => 'Membership',
            'sub_total' => 1000,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => 1000,
            'due_date' => $end,
        ]);

        $order->line_items()->create([
            'title' => 'Plan line',
            'description' => $description,
            'price' => 1000,
            'total' => 1000,
            'quantity' => 1,
            'options' => ['title' => 'Plan'],
        ]);

        return [$subscription->fresh(), $order->fresh()];
    }

    public function test_mark_as_paid_activates_subscription_and_sets_period_based_on_subscription_when_paid()
    {
        config()->set('foundry.subscription.anchor_from_invoice', true);

        [$subscription, $order] = $this->createSubscriptionWithOpenInvoice(true);

        // Act: mark invoice as paid, which should propagate to subscription
        $order->markAsPaid(1, ['note' => 'manual']);

        $subscription = $subscription->fresh();

        $this->assertEquals(Subscription::ACTIVE, $subscription->status);

        // Starts at should be set based on the invoice start (derived from description)
        // With invoice anchoring removed, starts_at should be set relative to now or existing starts_at
        $this->assertNotNull($subscription->starts_at);
        $this->assertTrue($subscription->starts_at->isSameDay(now()));
    }

    public function test_mark_as_paid_activates_subscription_and_sets_period_from_today_when_anchoring_disabled()
    {
        config()->set('foundry.subscription.anchor_from_invoice', false);

        [$subscription, $order] = $this->createSubscriptionWithOpenInvoice(true);

        // Act: mark invoice as paid with anchoring disabled
        $order->markAsPaid(1, ['note' => 'manual']);

        $subscription = $subscription->fresh();

        $this->assertEquals(Subscription::ACTIVE, $subscription->status);

        // Starts at should be around now (day-level)
        $this->assertTrue($subscription->starts_at->isSameDay(now()));
    }
}
