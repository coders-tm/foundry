<?php

namespace Foundry\Concerns\Subscription;

use Carbon\Carbon;
use Foundry\Foundry;
use Foundry\Models\Subscription;
use Foundry\Repository\InvoiceRepository;
use Foundry\Services\Period;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait ManagesInvoices
{
    /**
     * Get the latest invoice associated with the Subscription
     *
     * @return MorphOne
     */
    public function latestInvoice()
    {
        return $this->morphOne(Foundry::$orderModel, 'orderable')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get all invoices associated with the Subscription
     *
     * @return MorphMany
     */
    public function invoices()
    {
        return $this->morphMany(Foundry::$orderModel, 'orderable');
    }

    /**
     * Get the upcoming invoice for the subscription.
     *
     * @param  bool  $start  Whether to use the start date as due date
     * @param  Carbon|null  $dateFrom  The date from which to calculate the period
     */
    public function upcomingInvoice(bool $start = false, ?Carbon $dateFrom = null)
    {
        $plan = $this->nextPlan ?? $this->plan;

        if (! $plan) {
            return null;
        }

        $period = new Period(
            $plan->interval->value,
            (int) $plan->interval_count,
            $dateFrom ?? $this->dateFrom()
        );

        $dueDate = $start ? $this->dateFrom() : $this->expires_at;
        $dueDate = $dueDate && $dueDate->gt(now()) ? $dueDate : $period->getEndDate();

        return new InvoiceRepository([
            'source' => 'Membership',
            'customer_id' => $this->user?->id,
            'orderable_id' => $this->id,
            'orderable_type' => $this->getMorphClass(),
            'due_date' => $dueDate,
            'billing_address' => $this->user?->address?->toArray(),
            'collect_tax' => true,
            'line_items' => $this->generateLineItems($plan, $period),
        ]);
    }

    /**
     * Generate invoice items for a plan and period.
     *
     * @param  mixed  $plan  The subscription plan
     * @param  Period  $period  The billing period
     * @return array
     */
    protected function generateLineItems($plan, $period)
    {
        $fromDate = Carbon::parse($period->getStartDate())->format('M d, Y');
        $toDate = Carbon::parse($period->getEndDate())->format('M d, Y');
        $interval = $plan->interval->value;
        $amount = $plan->formatPrice();

        $title = "$plan->label (at $amount / $interval)";

        $lineItems = [
            [
                'title' => $title,
                'metadata' => [
                    'title' => $title,
                    'description' => "$fromDate - $toDate",
                    'plan_id' => $plan->id,
                ],
                'price' => $plan->price,
                'quantity' => 1,
                'taxable' => true,
                'discount' => $this->discount(),
            ],
        ];

        // Add setup fee if applicable
        if ($this->shouldChargeSetupFee($plan)) {
            $setupFee = $this->getSetupFee($plan);
            if ($setupFee > 0) {
                $lineItems[] = [
                    'title' => __('Admission Fee'),
                    'price' => $setupFee,
                    'quantity' => 1,
                    'metadata' => [
                        'type' => 'setup_fee',
                    ],
                ];
            }
        }

        return $lineItems;
    }

    /**
     * Determine if the setup fee should be charged.
     */
    protected function shouldChargeSetupFee($plan): bool
    {
        // If setup fee is explicitly disabled (0.0) on the plan
        if ($plan->setup_fee === 0.0) {
            return false;
        }

        // Admission fee is once per member for life
        // Check if there are any other subscriptions for this user
        $hasOtherSubscriptions = Subscription::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->exists();

        if ($hasOtherSubscriptions) {
            return false;
        }

        // Only charge on the very first invoice of the first subscription
        return $this->invoices()->count() === 0;
    }

    /**
     * Get the setup fee for the plan, falling back to global config.
     */
    protected function getSetupFee($plan): float
    {
        return $plan->setup_fee ?? config('foundry.subscription.setup_fee', 0.00);
    }
}
