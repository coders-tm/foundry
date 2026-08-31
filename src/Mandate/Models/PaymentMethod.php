<?php

namespace Foundry\Mandate\Models;

use Foundry\Services\PaymentProvider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * PaymentMethod model for storing user payment method references.
 *
 * This model maintains the mapping between application users and their
 * payment methods with external payment providers for auto-renewal purposes.
 */
class PaymentMethod extends Model
{
    use HasUuids;

    protected $table = 'users_payment_methods';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'options',
        'is_default',
    ];

    protected $casts = [
        'options' => 'json',
        'is_default' => 'boolean',
    ];

    /**
     * Scope query to only include default payment methods.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Mark this payment method as the default for the user.
     */
    public function markAsDefault(): self
    {
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);

        return $this;
    }

    /**
     * Get the provider configuration array for this payment method.
     */
    public function getGatewayAttribute(): ?array
    {
        return PaymentProvider::find($this->provider);
    }

    /**
     * Get the Paddle subscription ID for off-session billing.
     */
    public function getSubscriptionId(): ?string
    {
        return $this->options['subscription_id'] ?? null;
    }

    /**
     * Set the Paddle subscription ID for off-session billing.
     */
    public function setSubscriptionId(string $subscriptionId): static
    {
        $options = $this->options ?? [];
        $options['subscription_id'] = $subscriptionId;
        $this->options = $options;

        return $this;
    }

    /**
     * Get the payment method type (e.g. 'card', 'paypal').
     */
    public function getPaymentMethodType(): ?string
    {
        return $this->options['payment_method_type'] ?? null;
    }
}
