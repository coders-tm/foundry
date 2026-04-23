<?php

namespace Foundry\AutoRenewal\Models;

use Foundry\Models\PaymentMethod as PaymentProvider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Customer model for storing payment provider customer references.
 *
 * This model maintains the mapping between application users and their
 * customer IDs with external payment providers (Stripe, GoCardless, etc.).
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
     * Get the payment provider gateway associated with this customer.
     *
     * @return BelongsTo
     */
    public function gateway()
    {
        return $this->belongsTo(PaymentProvider::class, 'provider', 'provider');
    }
}
