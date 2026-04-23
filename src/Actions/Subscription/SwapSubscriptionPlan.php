<?php

namespace Foundry\Actions\Subscription;

use Foundry\Events\SubscriptionPlanChanged;
use Foundry\Foundry;
use Foundry\Models\Subscription;

class SwapSubscriptionPlan
{
    /**
     * Change subscription to a new plan.
     *
     * @param  string|int  $planId
     * @param  bool  $force  Bypass same plan validation
     *
     * @throws \InvalidArgumentException
     */
    public function execute(Subscription $subscription, $planId, bool $invoiceNow = true, bool $force = false): Subscription
    {
        if (empty($planId)) {
            throw new \InvalidArgumentException('Please provide a plan when swapping.');
        }

        if (! $force) {
            $subscription->guardAgainstIncomplete();
        }

        $subscription->loadMissing(['plan']);

        $oldPlan = $subscription->plan;
        $newPlan = Foundry::$planModel::findOrFail($planId);

        // Prevent swapping to the same plan unless forced
        if (! $force && $oldPlan && $oldPlan->id == $newPlan->id && $subscription->valid()) {
            throw new \InvalidArgumentException("Cannot swap to the same plan ({$oldPlan->label}).");
        }

        // Set new period based on the new plan
        $subscription->setPeriod($newPlan->interval->value, $newPlan->interval_count);

        // Attach new plan to subscription
        $subscription->plan()->associate($newPlan);

        $subscription->fill([
            'canceled_at' => null,
            'billing_interval' => $newPlan->interval->value,
            'billing_interval_count' => $newPlan->interval_count,
            'total_cycles' => $newPlan->contract_cycles,
            'current_cycle' => 0, // Reset cycle counter when swapping plans
        ])->save();

        // Sync features from the new plan (this will also reset usage)
        $subscription->syncFeaturesFromPlan();

        if ($invoiceNow) {
            // Cancel all open invoices for this subscription
            $openInvoices = $subscription->invoices()->where('status', Foundry::$orderModel::STATUS_OPEN);
            foreach ($openInvoices->cursor() as $order) {
                $order->markAsCancelled();
            }

            // Subscription is expired or near expiry, generate invoice immediately
            app(GenerateSubscriptionInvoice::class)->execute($subscription, true);
        }

        // Fire the plan changed event
        event(new SubscriptionPlanChanged($subscription, $oldPlan, $newPlan));

        return $subscription;
    }
}
