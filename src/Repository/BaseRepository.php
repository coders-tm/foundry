<?php

namespace Foundry\Repository;

use Foundry\Models\Order\DiscountLine;
use Foundry\Models\Order\LineItem;
use Foundry\Models\Order\TaxLine;
use Foundry\Models\Tax;
use Foundry\Rules\ArrayOrInstanceOf;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use League\ISO3166\ISO3166;
use Throwable;

abstract class BaseRepository extends Model
{
    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Indicates if the model exists in database.
     * For repository classes, this is typically false.
     */
    public $exists = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer',
        'line_items',
        'billing_address',
        'shipping_address',
        'discount',
        'tax_lines',
        'collect_tax',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     */
    protected $appends = [
        'sub_total',
        'tax_total',
        'tax_lines',
        'total_line_items',
        'line_items_quantity',
        'taxable_line_items',
        'taxable_sub_total',
        'discount_total',
        'discount_per_item',
        'taxable_discount',
        'has_compound_tax',
        'total_taxable',
        'default_tax_total',
        'shipping_total',
        'grand_total',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'collect_tax' => 'boolean',
    ];

    /**
     * Tax collection instance
     */
    protected Collection $taxes;

    /**
     * Create a new repository instance
     */
    public function __construct(array $attributes = [])
    {
        // Ensure collect_tax is set to true by default if not provided
        if (! isset($attributes['collect_tax'])) {
            $attributes['collect_tax'] = true;
        }

        // Initialize tax collection directly in constructor
        $taxLines = $attributes['tax_lines'] ?? [];

        // Set default tax lines if not provided or empty when tax collection is enabled
        if ($attributes['collect_tax'] && empty($taxLines)) {
            // Use tax lines based on billing address if provided
            if (! empty($attributes['billing_address'])) {
                $taxLines = $this->getBillingAddressTax($attributes['billing_address']);
            } else {
                // Use default tax configuration if no billing address is available
                $taxLines = $this->getDefaultTax();
            }

            // Ensure tax_lines is always set
            $attributes['tax_lines'] = $taxLines;
        }

        // Validate attributes against defined rules
        $this->validateAttributes($attributes);

        parent::__construct($attributes);

        // Trigger Attribute::make(...)->set() logic
        foreach (['discount', 'line_items', 'tax_lines'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $this->$key = $attributes[$key];
            }
        }
    }

    /**
     * Get tax configuration for billing address
     */
    public function getBillingAddressTax(array $address = []): array
    {
        if (isset($address['country']) && ! empty($address['country'])) {
            $stateCode = isset($address['state_code']) ? $address['state_code'] : null;
            $taxes = $this->countryTaxes($address['country'], $stateCode);
            if (count($taxes) > 0) {
                return $taxes;
            }
        }

        return $this->restOfWorldTax();
    }

    /**
     * Get default tax configuration
     */
    public function getDefaultTax(): array
    {
        $taxes = $this->countryTaxes(config('app.country'));
        if (count($taxes) > 0) {
            return $taxes;
        }

        return $this->restOfWorldTax();
    }

    /**
     * Get country code from country name
     */
    public static function getCountryCode(?string $country): string
    {
        if (empty($country)) {
            return '*';
        }

        try {
            $country = (new ISO3166)->name($country);

            return $country['alpha2'];
        } catch (Throwable $e) {
            return '*';
        }
    }

    /**
     * Get taxes for a specific country and state
     */
    public function countryTaxes(?string $countryCode = null, ?string $state = null): array
    {
        $taxQuery = Tax::where('code', self::getCountryCode($countryCode))
            ->orderBy('priority');

        if ($state) {
            $taxQuery->whereIn('state', ['*', $state]);
        } else {
            $taxQuery->whereIn('state', ['*']);
        }

        return $taxQuery->get()
            ->map(function ($item) {
                return array_merge($item->only(['label', 'rate']), [
                    'type' => $item->compounded ? 'compounded' : 'normal',
                ]);
            })->toArray();
    }

    /**
     * Get rest of world tax configuration
     */
    public function restOfWorldTax(): array
    {
        return $this->countryTaxes('*');
    }

    /**
     * Validation rules for the repository
     */
    public function rules(): array
    {
        return [
            'customer' => 'nullable|array',
            'billing_address' => 'nullable|array',
            'shipping_address' => 'nullable|array',
            'line_items' => 'nullable|array',
            'line_items.*' => [new ArrayOrInstanceOf(LineItem::class)],
            'discount' => ['nullable', new ArrayOrInstanceOf(DiscountLine::class)],
            'tax_lines' => 'nullable|array',
            'tax_lines.*' => [new ArrayOrInstanceOf(TaxLine::class)],
            'collect_tax' => 'boolean',
        ];
    }

    /**
     * Validate an attribute against its defined rules
     */
    public function validateAttributes(array $attributes): void
    {
        $validator = Validator::make($attributes, $this->rules());

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }
    }

    /**
     * Use default tax lines based on billing address or default configuration
     */
    public function useDefaultTax(): static
    {
        if (! empty($this->tax_lines)) {
            return $this;
        }

        if (! empty($this->billing_address)) {
            $this->tax_lines = $this->getBillingAddressTax($this->billing_address);
        } else {
            $this->tax_lines = $this->getDefaultTax();
        }

        return $this;
    }

    public function hasDiscount(): bool
    {
        return ! empty($this->discount);
    }

    /**
     * Normalize discount data to DiscountLine object
     */
    protected function setDiscount($value)
    {
        // If already a DiscountLine object, return it
        if ($value instanceof DiscountLine) {
            return $value;
        }

        // Handle null or non-array values
        if (! is_array($value) || empty($value)) {
            return null;
        }

        return new DiscountLine($value);
    }

    protected function discount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->setDiscount($value),
            set: fn ($value) => $this->setDiscount($value),
        );
    }

    /**
     * Normalize line items data to Collection of LineItem objects
     */
    protected function setLineItems($value)
    {
        if ($value instanceof Collection && $value->every(fn ($item) => $item instanceof LineItem)) {
            return $value;
        }

        return collect($value ?: [])->map(function ($item) {
            // If already a LineItem object, return it
            if ($item instanceof LineItem) {
                return $item;
            }

            $itemData = $item;
            $discountData = null;

            if (is_array($item)) {
                $discountData = $item['discount'] ?? null;
                unset($itemData['discount']);
            }

            // Ensure each item has taxable key set to true if missing
            if (is_array($itemData) && ! isset($itemData['taxable'])) {
                $itemData['taxable'] = true;
            }

            $lineItem = new LineItem($itemData);

            // Hydrate discount relation if present in the data
            if ($discountData) {
                $discountLine = $discountData instanceof DiscountLine
                    ? $discountData
                    : new DiscountLine($discountData);

                $lineItem->setRelation('discount', $discountLine);
            }

            return $lineItem;
        });
    }

    protected function lineItems(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->setLineItems($value),
            set: fn ($value) => $this->setLineItems($value),
        );
    }

    /**
     * Normalize tax lines data to Collection of TaxLine objects
     */
    protected function setTaxLines($value)
    {
        $taxes = collect($value ?: [])->map(function ($item) {
            if ($item instanceof TaxLine) {
                return $item;
            }

            if (! is_array($item)) {
                return null;
            }

            return TaxLine::firstOrNew([
                'id' => $item['id'] ?? null,
            ], $item);
        })->filter();

        return $this->taxes = $taxes;
    }

    protected function taxLines(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // For tax_lines, we need the calculated amounts, so use the existing logic
                return $this->taxes->map(function ($item) {
                    return $item->fill([
                        'amount' => $this->getTaxTotal($item),
                    ]);
                });
            },
            set: fn ($value) => $this->setTaxLines($value)
        );
    }

    protected function totalLineItems(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->line_items->sum('quantity');
            }
        );
    }

    protected function lineItemsQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->line_items->sum('quantity');
            }
        );
    }

    protected function taxableLineItems(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->line_items->where('taxable', true)->sum('quantity')
        );
    }

    protected function subTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (is_null($this->line_items)) {
                    return 0;
                }

                return round($this->line_items->sum('sub_total'), 2);
            }
        );
    }

    protected function taxableSubTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (is_null($this->line_items)) {
                    return 0;
                }

                return round($this->line_items->where('taxable', true)->sum('total'), 2);
            }
        );
    }

    protected function lineDiscountTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->line_items?->sum(fn ($item) => $item->discount_amount) ?? 0, 2)
        );
    }

    protected function orderDiscountTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->hasDiscount()) {
                    return 0;
                }

                $discount = $this->discount;
                if (is_array($discount)) {
                    $discount = new DiscountLine($discount);
                }

                if ($discount instanceof DiscountLine) {
                    if ($discount->isFixedAmount()) {
                        return round($discount->value, 2);
                    } else {
                        return round($this->sub_total * $discount->value / 100, 2);
                    }
                }

                return 0;
            }
        );
    }

    protected function discountTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->line_discount_total + $this->order_discount_total, 2)
        );
    }

    protected function discountPerItem(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->total_line_items) {
                    return 0;
                }

                return $this->discount_total / $this->total_line_items;
            }
        );
    }

    protected function taxableDiscount(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->hasDiscount()) {
                    // Ensure we have a DiscountLine object
                    $discount = $this->discount;
                    if (is_array($discount)) {
                        $discount = new DiscountLine($discount);
                    }

                    if ($discount instanceof DiscountLine) {
                        if ($discount->isFixedAmount()) {
                            // Pro-rate fixed order discount across taxable items
                            $discountPerItem = $discount->value / ($this->total_line_items ?: 1);

                            return round($discountPerItem * $this->taxable_line_items, 2);
                        } elseif ($discount->isPercentage()) {
                            // Percentage order discount on taxable sub-total
                            return round($this->taxable_sub_total * $discount->value / 100, 2);
                        }
                    }
                }

                return 0;
            }
        );
    }

    protected function hasCompoundTax(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->taxes->where('type', 'compounded')->count() > 0
        );
    }

    protected function taxTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->collect_tax) {
                    return 0;
                }

                return round(collect($this->tax_lines)->sum('amount'), 2);
            }
        );
    }

    protected function totalTaxable(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->taxable_sub_total - $this->taxable_discount, 2)
        );
    }

    protected function defaultTaxTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->taxes->whereNotIn('type', ['compounded'])->map(function ($tax) {
                    return round(($this->total_taxable * $tax->rate) / 100, 2);
                })->sum();
            }
        );
    }

    private function getTaxTotal($tax)
    {
        if ($this->has_compound_tax && $this->default_tax_total && $tax->type == 'compounded') {
            return round((($this->total_taxable + $this->default_tax_total) * $tax->rate) / 100, 2);
        } else {
            return round(($this->total_taxable * $tax->rate) / 100, 2);
        }
    }

    // TODO: Implement shipping logic if needed
    // For now, we assume shipping is not applicable or always zero
    protected function shippingTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => 0
        );
    }

    protected function grandTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                $total = ($this->sub_total - $this->discount_total) + $this->tax_total + ($this->shipping_total ?? 0);

                return round($total, 2);
            }
        );
    }
}
