<?php

namespace Foundry\Services\Admin;

use App\Models\User;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Foundry;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Services\Period;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    /**
     * Create or update a subscription with the given parameters.
     *
     * @param  User  $user  The user for whom the subscription is being created or updated.
     * @param  array  $data  The subscription data, including plan, status, dates, etc.
     * @param  Subscription|null  $subscription  An existing subscription to update, or null to create a new one.
     * @return Subscription The created or updated subscription instance.
     */
    public function createOrUpdate($user, array $data, $subscription = null)
    {
        $plan = Plan::findOrFail($data['plan']);

        if (! $subscription) {
            $subscription = $user->newSubscription($data['type'] ?? 'default', $plan)
                ->withCoupon($data['promotion_code'] ?? null);
        } else {
            $planChanged = $subscription->plan_id != $plan->id;
            $subscription->plan_id = $plan->id;

            // Sync billing interval from plan if changed
            if ($planChanged) {
                $subscription->fill([
                    'billing_interval' => $plan->interval->value,
                    'billing_interval_count' => $plan->interval_count,
                ]);
            }
        }

        // Apply basic fields
        $subscription->is_free_forever = $data['is_free_forever'] ?? false;

        if (isset($data['status'])) {
            $subscription->status = $data['status'];
        }

        if (! empty($data['starts_at'])) {
            $subscription->setStartsAt($data['starts_at']);
        }

        if (! empty($data['expires_at'])) {
            $subscription->setExpiresAt($data['expires_at']);
        } elseif (! empty($data['starts_at']) || ($subscription->wasRecentlyCreated || ($planChanged ?? false))) {
            // Auto calculate expires_at based on starts_at + plan interval
            // Only if expires_at is not explicitly provided
            $period = new Period($plan->interval->value, $plan->interval_count, $subscription->starts_at);
            $subscription->setExpiresAt($period->getEndDate());
        }

        if (! empty($data['trial_days'])) {
            $subscription->trialDays($data['trial_days']);
        }

        if (! empty($data['trial_ends_at'])) {
            $subscription->trialUntil($data['trial_ends_at']);
        }

        if ($data['generate_invoice'] ?? false) {
            $subscription->status = SubscriptionStatus::INCOMPLETE;
            $pendingInvoice = $subscription->invoices()
                ->where('status', Foundry::$orderModel::STATUS_OPEN)
                ->latest()
                ->first();

            if ($pendingInvoice) {
                $invoiceData = $subscription->upcomingInvoice(false)->toArray();
                $invoiceData['id'] = $pendingInvoice->id;
                Foundry::$orderModel::create($invoiceData);
            } else {
                $subscription->saveAndInvoice();
            }
        } else {
            if (! isset($data['status'])) {
                $subscription->status = SubscriptionStatus::ACTIVE;
            }
            $subscription->save();
        }

        // Optional: Reset features
        if ($data['reset_feature'] ?? false) {
            $subscription->syncFeaturesFromPlan();
        }

        // Optional: Mark as paid
        if ($data['mark_as_paid'] ?? false) {
            if (empty($data['payment_method'])) {
                throw ValidationException::withMessages([
                    'payment_method' => __('Payment method is required when marking as paid.'),
                ]);
            }
            $subscription->pay($data['payment_method']);
        }

        return $subscription->fresh();
    }
}
