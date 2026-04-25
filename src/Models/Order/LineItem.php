<?php

namespace Foundry\Models\Order;

use Foundry\Concerns\Core;
use Foundry\Contracts\Currencyable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineItem extends Model implements Currencyable
{
    use Core, HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'variant_title',
        'sku',
        'price',
        'quantity',
        'taxable',
        'metadata',
        'is_custom',
    ];

    protected $with = [
        'discount',
    ];

    protected $appends = [
        'discounted_price',
        'has_discount',
        'sub_total',
        'discount_amount',
        'total',
    ];

    protected $casts = [
        'metadata' => 'json',
        'taxable' => 'boolean',
        'is_custom' => 'boolean',
    ];

    /**
     * Get the list of currency fields to be converted.
     *
     * @return array Field names that contain currency amounts
     */
    public function getCurrencyFields(): array
    {
        return ['price', 'total', 'discounted_price'];
    }

    public function discount()
    {
        return $this->morphOne(DiscountLine::class, 'discountable');
    }

    public function hasDiscount(): bool
    {
        return ! is_null($this->discount) ?: false;
    }

    protected function total(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->discounted_price * $this->quantity, 2),
        );
    }

    protected function subTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->price * $this->quantity, 2),
        );
    }

    protected function discountAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => round(($this->price - $this->discounted_price) * $this->quantity, 2),
        );
    }

    protected function discountedPrice(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->hasDiscount()) {
                    return $this->discount->calculateFinalPrice($this->price);
                }

                return $this->price;
            },
        );
    }

    public function getHasDiscountAttribute()
    {
        return $this->hasDiscount();
    }
}
