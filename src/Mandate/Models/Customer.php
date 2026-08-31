<?php

namespace Foundry\Mandate\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer model for storing user provider-specific customer references.
 */
class Customer extends Model
{
    use HasUuids;

    protected $table = 'payment_provider_customers';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'options',
    ];

    protected $casts = [
        'options' => 'json',
    ];

    /**
     * Get a specific option key or return all options.
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        return data_get($this->options, $key, $default);
    }

    /**
     * Set a specific option key in options array.
     */
    public function setOption(string $key, mixed $value): static
    {
        $options = $this->options ?? [];
        data_set($options, $key, $value);
        $this->options = $options;

        return $this;
    }

    /**
     * Get the associated subscription ID for Paddle metered / recurring billing.
     */
    public function getSubscriptionId(): ?string
    {
        return $this->getOption('subscription_id');
    }

    /**
     * Set the associated subscription ID for Paddle metered / recurring billing.
     */
    public function setSubscriptionId(string $subscriptionId): static
    {
        return $this->setOption('subscription_id', $subscriptionId);
    }
}
