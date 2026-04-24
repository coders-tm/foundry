<?php

namespace Foundry\Models;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Foundry\Contracts\ManagesSubscriptions;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Database\Factories\SubscriptionFactory;
use Foundry\Events\SubscriptionCreated;
use Foundry\Events\SubscriptionUpdated;
use Foundry\Exceptions\SubscriptionUpdateFailure;
use Foundry\Foundry;
use Foundry\Models\Order\DiscountLine;
use Foundry\Services\Period;
use Foundry\Services\Subscription\SubscriptionStatusManager;
use Foundry\Concerns;
use Foundry\Concerns\HasFeature;
use Foundry\Concerns\Logable;
use Foundry\Concerns\SerializeDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model implements ManagesSubscriptions, SubscriptionStatus
{
    use HasFactory, HasFeature, HasUuids, Logable, SerializeDate;
    use Concerns\Actionable;
    use Concerns\Subscription\ForwardsSubscriptionActions;
    use Concerns\Subscription\ManagesInvoices;

    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'plan_id',
        'coupon_id',
        'next_plan',
        'trial_ends_at',
        'expires_at',
        'ends_at',
        'starts_at',
        'canceled_at',
        'frozen_at',
        'release_at',
        'provider',
        'metadata',
        'is_downgrade',
        'is_free_forever',
        'billing_interval',
        'billing_interval_count',
        'total_cycles',
        'current_cycle',
        'auto_renewal_enabled',
        'latest_order_id',
    ];

    protected $with = [
        'features',
    ];

    protected $dispatchesEvents = [
        'created' => SubscriptionCreated::class,
        'updated' => SubscriptionUpdated::class,
    ];

    protected $casts = [
        'is_downgrade' => 'boolean',
        'trial_ends_at' => 'datetime',
        'expires_at' => 'datetime',
        'ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'canceled_at' => 'datetime',
        'frozen_at' => 'datetime',
        'release_at' => 'datetime',
        'metadata' => 'json',
        'is_free_forever' => 'boolean',
        'billing_interval_count' => 'integer',
        'total_cycles' => 'integer',
        'current_cycle' => 'integer',
        'auto_renewal_enabled' => 'boolean',
    ];

    /**
     * Track if custom dates have been explicitly set in the current request.
     * This is a transient flag that prevents setPeriod() from overriding custom dates.
     *
     * @var bool
     */
    protected $hasCustomDates = false;

    /**
     * Get the user foreign key.
     *
     * @return string
     */
    public function getUserForeignKey()
    {
        return (new Foundry::$subscriptionUserModel)->getForeignKey();
    }

    /**
     * Get the user relationship.
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Get the owner relationship.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Foundry::$subscriptionUserModel, $this->getUserForeignKey());
    }

    /**
     * Scope a query to only include subscriptions with users.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeHasUser($query)
    {
        return $query->whereNotNull($this->getUserForeignKey());
    }

    /**
     * Sync the subscription usages.
     *
     * @return void
     */
    public function syncUsages()
    {
        if ($this->wasRecentlyCreated) {
            $this->syncFeaturesFromPlan();
        } else {
            $this->syncOrResetUsages();
        }
    }

    /**
     * Set the payment provider for the subscription.
     *
     * @param  string  $provider
     * @return $this
     */
    public function setProvider($provider)
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Set the status for the subscription.
     *
     * @param  string  $status
     * @return $this
     */
    public function withStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Save the subscription without generating an invoice.
     *
     * @return self Returns the subscription instance without invoice generation
     */
    public function saveWithoutInvoice(array $options = []): self
    {
        $this->save($options);

        return $this;
    }

    /**
     * Save the subscription and generate an invoice.
     *
     * @param  bool  $force  Force invoice generation even if on trial
     * @return self Returns the subscription instance with invoice generated
     */
    public function saveAndInvoice(array $options = [], bool $force = false): self
    {
        // Save the subscription first
        $this->save($options);

        // Generate invoice (checks for free plans, trials, etc. internally)
        $this->generateInvoice(true, $force);

        return $this;
    }

    /**
     * Check if this subscription is contract-based.
     * A subscription is considered contract-based when it has a defined total_cycles.
     */
    public function isContractBased(): bool
    {
        return ! is_null($this->total_cycles) && $this->total_cycles > 0;
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return SubscriptionFactory::new();
    }

    /**
     * Bootstrap the model.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::created(function (self $model): void {
            $model->syncFeaturesFromPlan();
        });

        static::deleted(function (self $model): void {
            $model->features()->delete();
        });
    }

    public function formatBillingInterval(): string
    {
        if (! $this->billing_interval) {
            return '';
        }

        $interval = is_string($this->billing_interval) ? $this->billing_interval : $this->billing_interval->value;

        $count = $this->billing_interval_count ?? 1;

        if ($count > 1) {
            return "{$count} {$interval}s";
        }

        return $interval;
    }

    /**
     * Get the status manager for this subscription.
     */
    public function status(): SubscriptionStatusManager
    {
        return new SubscriptionStatusManager($this);
    }

    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->canceledOnGracePeriod() || $this->onGracePeriod();
    }

    public function pending(): bool
    {
        return $this->status === SubscriptionStatus::PENDING;
    }

    public function scopePending($query)
    {
        $query->where('status', SubscriptionStatus::PENDING);
    }

    public function incomplete(): bool
    {
        return $this->status === SubscriptionStatus::INCOMPLETE;
    }

    public function scopeIncomplete($query)
    {
        $query->where('status', SubscriptionStatus::INCOMPLETE);
    }

    public function expired(): bool
    {
        return $this->status === SubscriptionStatus::EXPIRED;
    }

    public function scopeExpired($query)
    {
        $query->where('status', SubscriptionStatus::EXPIRED);
    }

    public function active(): bool
    {
        return $this->is_free_forever || (! $this->ended() && $this->status === SubscriptionStatus::ACTIVE);
    }

    public function scopeActive($query)
    {
        $query->where(function ($query) {
            $query->whereNull('canceled_at')
                ->orWhere(function ($query) {
                    $query->canceledOnGracePeriod();
                });
        })->whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::TRIALING]);
    }

    public function scopeFree($query)
    {
        $query->whereHas('plan', function ($query) {
            $query->where('price', 0);
        });
    }

    public function recurring(): bool
    {
        return ! $this->onTrial() && ! $this->canceled();
    }

    public function scopeRecurring($query)
    {
        $query->notOnTrial()->notCanceled();
    }

    public function canceled(): bool
    {
        return ! is_null($this->canceled_at);
    }

    public function scopeCanceled($query)
    {
        $query->whereNotNull('canceled_at');
    }

    public function scopeNotCanceled($query)
    {
        $query->whereNull('canceled_at');
    }

    public function ended()
    {
        return $this->canceled() && ! $this->canceledOnGracePeriod();
    }

    public function scopeEnded($query)
    {
        $query->canceled()->canceledNotOnGracePeriod();
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function onTrialExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    public function hasDowngrade(): bool
    {
        return $this->is_downgrade && $this->next_plan;
    }

    public function scopeOnTrial($query)
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now());
    }

    public function scopeExpiredTrial($query)
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', now());
    }

    public function scopeNotOnTrial($query)
    {
        $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', now());
    }

    public function canceledOnGracePeriod(): bool
    {
        return $this->canceled_at && $this->expires_at && $this->expires_at->isFuture();
    }

    public function scopeCanceledOnGracePeriod($query)
    {
        $query->whereNotNull('canceled_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now());
    }

    public function scopeCanceledNotOnGracePeriod($query)
    {
        $query->whereNotNull('canceled_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function onGracePeriod(): bool
    {
        if ($this->status !== SubscriptionStatus::ACTIVE) {
            return false;
        }

        return $this->ends_at?->isFuture() ?? false;
    }

    public function notOnGracePeriod(): bool
    {
        return ! $this->onGracePeriod();
    }

    public function scopeOnGracePeriod($query)
    {
        $query->where('status', SubscriptionStatus::ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now());
    }

    public function scopeNotOnGracePeriod($query)
    {
        $query->where(function ($q) {
            $q->where('status', '<>', SubscriptionStatus::ACTIVE)
                ->orWhereNull('ends_at')
                ->orWhere('ends_at', '<=', now());
        });
    }

    public function hasIncompletePayment(): bool
    {
        return $this->expired() || $this->incomplete() || $this->pending();
    }

    public function guardAgainstIncomplete()
    {
        if ($this->incomplete()) {
            throw SubscriptionUpdateFailure::incompleteSubscription($this);
        }
    }

    protected function anchorActivationFromInvoice(): bool
    {
        return (bool) config('foundry.subscription.anchor_from_invoice', false) && $this->expires_at && $this->expires_at->isFuture();
    }

    public function setPeriod(string $interval = '', ?int $count = null, ?Carbon $dateFrom = null): self
    {
        if ($this->hasCustomDates) {
            return $this;
        }

        if ($this->anchorActivationFromInvoice()) {
            $dateFrom = $this->starts_at?->copy() ?? $dateFrom;
        }

        if (empty($interval)) {
            $interval = $this->plan->interval->value;
        }

        if (empty($count)) {
            $count = $this->plan->interval_count;
        }

        $period = new Period($interval, $count, $dateFrom ?? now());

        $this->fill([
            'starts_at' => $period->getStartDate(),
            'expires_at' => $period->getEndDate(),
            'billing_interval' => $this->plan->interval->value,
            'billing_interval_count' => $this->plan->interval_count,
        ]);

        if ($this->plan->isContract() && is_null($this->total_cycles)) {
            $this->total_cycles = $this->plan->contract_cycles;
            $this->current_cycle = 0;
        }

        return $this;
    }

    public function contractCycles(?int $cycles): self
    {
        $this->total_cycles = $cycles;
        $this->current_cycle = 0;

        return $this;
    }

    public function contractComplete(): bool
    {
        if (! $this->total_cycles) {
            return false;
        }

        return $this->current_cycle >= $this->total_cycles;
    }

    public function setPeriodFromDate(Carbon $dateFrom): self
    {
        return $this->setPeriod('', null, $dateFrom);
    }

    protected function dateFrom(): Carbon
    {
        $date = $this->starts_at ?? $this->created_at ?? now();

        if ($date instanceof CarbonImmutable) {
            return Carbon::instance($date);
        }

        return $date instanceof Carbon ? $date : Carbon::instance($date);
    }

    public function getBillingInterval(): string
    {
        if ($this->billing_interval) {
            return is_string($this->billing_interval) ? $this->billing_interval : $this->billing_interval->value;
        }

        return $this->plan->interval->value;
    }

    public function getBillingIntervalCount(): int
    {
        return $this->billing_interval_count ?? $this->plan->interval_count;
    }

    public function isContract(): bool
    {
        return $this->plan && $this->plan->isContract();
    }

    public function setStartsAt($date): self
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        } elseif ($date instanceof \DateTimeInterface && ! $date instanceof Carbon) {
            $date = Carbon::instance($date);
        }

        $this->starts_at = $date;
        $this->hasCustomDates = true;

        return $this;
    }

    public function setExpiresAt($date): self
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        } elseif ($date instanceof \DateTimeInterface && ! $date instanceof Carbon) {
            $date = Carbon::instance($date);
        }

        $this->expires_at = $date;
        $this->hasCustomDates = true;

        return $this;
    }

    public function hasPlan($plan): bool
    {
        return $this->plan_id === $plan;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Foundry::$planModel);
    }

    public function nextPlan(): BelongsTo
    {
        return $this->belongsTo(Foundry::$planModel, 'next_plan');
    }

    public function hasNexPlan(): bool
    {
        return ! is_null($this->next_plan);
    }

    public function assertRenewable(): void
    {
        if ($this->ended()) {
            throw new \LogicException('Unable to renew canceled ended subscription.');
        }

        if ($this->onGracePeriod()) {
            throw new \LogicException('Unable to renew subscription that is not within grace period.');
        }
    }

    public function assertChargeable(): void
    {
        if ($this->expired() || $this->hasIncompletePayment()) {
            return;
        }

        throw new \LogicException('Unable to charge subscription that is not expired.');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Foundry::$couponModel);
    }

    public function withCoupon($coupon): self
    {
        $couponModel = Foundry::$couponModel;
        if ($coupon = $couponModel::findByCode($coupon)) {
            $this->coupon()->associate($coupon);
        }

        return $this;
    }

    public function canApplyCoupon($coupon = null)
    {
        $coupon = $coupon ?? $this->coupon;
        $foreignKey = $this->getUserForeignKey();
        $userId = $this->{$foreignKey};

        if ($coupon && $coupon->canApplyToPlan($this->plan)) {
            if ($coupon->duration->value === 'once' && $coupon->redeems()->where($foreignKey, $userId)->exists()) {
                return null;
            }

            if ($coupon->duration->value === 'repeating' && $coupon->redeems()->where($foreignKey, $userId)->count() >= $coupon->duration_in_months) {
                return null;
            }

            return $coupon;
        }

        return null;
    }

    protected function discount(): ?array
    {
        if ($coupon = $this->canApplyCoupon()) {
            $discountType = match ($coupon->discount_type) {
                'percentage' => DiscountLine::TYPE_PERCENTAGE,
                'fixed' => DiscountLine::TYPE_FIXED_AMOUNT,
                'override' => DiscountLine::TYPE_PRICE_OVERRIDE,
                default => DiscountLine::TYPE_PERCENTAGE
            };

            return [
                'type' => $discountType,
                'value' => $coupon->value,
                'description' => $coupon->name,
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->promotion_code,
                'auto_applied' => false,
            ];
        }

        return null;
    }

    public function onFreeze(): bool
    {
        return ! is_null($this->frozen_at) &&
            $this->status === SubscriptionStatus::PAUSED &&
            (is_null($this->release_at) || $this->release_at->isFuture());
    }

    public function canFreeze(int $days = 0): bool
    {
        if (! $this->plan->allowsFreeze() || $this->onFreeze() || $this->canceled() || $this->expired()) {
            return false;
        }

        return true;
    }

    public function scopeFrozen($query)
    {
        return $query->where('status', SubscriptionStatus::PAUSED)
            ->whereNotNull('frozen_at');
    }

    public function scopeDueForUnfreeze($query)
    {
        return $query->frozen()
            ->whereNotNull('release_at')
            ->where('release_at', '<=', now());
    }
}
