<?php

namespace Foundry\Actions\Subscription;

use Carbon\Carbon;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Enum\LogType;
use Foundry\Models\Subscription;

class FreezeSubscription
{
    /**
     * Freeze the subscription immediately.
     *
     * @param  Carbon|null  $releaseAt  When to automatically unfreeze
     * @param  string|null  $reason  Reason for freezing
     * @param  float|null  $fee  Override freeze fee
     *
     * @throws \LogicException
     */
    public function execute(Subscription $subscription, ?Carbon $releaseAt = null, ?string $reason = null, ?float $fee = null): Subscription
    {
        $freezeDays = $releaseAt ? now()->diffInDays($releaseAt) : 0;

        if (! $subscription->canFreeze($freezeDays)) {
            throw new \LogicException(__('Subscription cannot be frozen at this time.'));
        }

        // Calculate freeze fee (use plan-specific or override)
        $freezeFee = $fee ?? $subscription->plan->getFreezeFee();

        // Update subscription to frozen state
        $subscription->fill([
            'status' => SubscriptionStatus::PAUSED,
            'frozen_at' => now(),
            'release_at' => $releaseAt,
        ])->save();

        // Generate invoice for freeze fee if applicable
        if ($freezeFee > 0) {
            $this->generateFreezeInvoice($subscription, $freezeFee, $releaseAt);
        }

        $logMessage = __('Subscription frozen');
        if ($reason) {
            $logMessage .= ": {$reason}";
        }
        if ($releaseAt) {
            $logMessage .= ' ('.__('until :date', ['date' => $releaseAt->format('Y-m-d')]).')';
        }

        $subscription->logs()->create([
            'type' => LogType::UPDATED,
            'message' => $logMessage,
        ]);

        return $subscription;
    }

    /**
     * Generate invoice for freeze fee.
     *
     * @return mixed
     */
    protected function generateFreezeInvoice(Subscription $subscription, float $fee, ?Carbon $releaseAt = null)
    {
        $period = $releaseAt ? now()->diffInDays($releaseAt).' days' : 'indefinite';

        return $subscription->invoices()->create([
            'user_id' => $subscription->user_id,
            'status' => 'open',
            'description' => __('Subscription freeze fee (:period)', ['period' => $period]),
            'sub_total' => $fee,
            'tax' => 0,
            'total' => $fee,
            'grand_total' => $fee,
            'lines' => [
                [
                    'description' => __('Freeze Fee - :plan', ['plan' => $subscription->plan->label]),
                    'quantity' => 1,
                    'unit_amount' => $fee,
                    'amount' => $fee,
                ],
            ],
        ]);
    }
}
