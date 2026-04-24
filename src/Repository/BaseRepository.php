<?php

namespace Foundry\Repository;

use Foundry\Models\Order\DiscountLine;
use Foundry\Models\Order\LineItem;
use Foundry\Models\Order\TaxLine;
use Foundry\Rules\ArrayOrInstanceOf;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

abstract class BaseRepository extends Model
{
    /**
     * Cached calculated attributes for the current repository instance.
     */
    protected array $cache = [];

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
     * Attributes that affect calculated totals.
     */
    protected array $calculationDependencies = [
        'billing_address',
        'collect_tax',
        'discount',
        'line_items',
        'tax_lines',
    ];

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

        // Set default tax lines if not provided
        if (empty($taxLines)) {
            if (! empty($attributes['billing_address'])) {
                $taxLines = $this->getBillingAddressTax($attributes['billing_address']);
            }

            // Ensure tax_lines is always set (even if empty)
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
     * Set a model attribute and invalidate calculated values when dependencies change.
     */
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->calculationDependencies, true)) {
            $this->clearCalculatedCache();
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Get a calculated attribute from the instance cache.
     */
    protected function getCalculated(string $key, \Closure $callback)
    {
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $callback();
        }

        return $this->cache[$key];
    }

    /**
     * Clear cached calculated attributes.
     */
    protected function clearCalculatedCache(): void
    {
        $this->cache = [];
    }

    /**
     * Get tax configuration for billing address
     */
    protected function getBillingAddressTax($billingAddress): ?array
    {
        // Check if function exists, otherwise return default
        if (function_exists('billing_address_tax')) {
            return billing_address_tax($billingAddress);
        }

        // Default tax for testing
        return [];
    }

    /**
     * Get default tax configuration
     */
    protected function getDefaultTax(): ?array
    {
        // Check if function exists, otherwise return default
        if (function_exists('default_tax')) {
            return default_tax();
        }

        // Default tax for testing
        return [];
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
        return $this->discount !== null;
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
            get: fn ($value) => $this->getCalculated(
                'discount',
                fn () => $this->setDiscount($value)
            ),
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

            // Ensure each item has taxable key set to true if missing
            if (is_array($item) && ! isset($item['taxable'])) {
                $item['taxable'] = true;
            }

            $lineItem = new LineItem($item);

            // Hydrate discount relation if present in the data
            if (is_array($item) && isset($item['discount'])) {
                $lineItem->setRelation('discount', new DiscountLine($item['discount']));
            }

            return $lineItem;
        });
    }

    protected function lineItems(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->getCalculated(
                'line_items',
                fn () => $this->setLineItems($value)
            ),
            set: fn ($value) => $this->setLineItems($value),
        );
    }

    /**
     * Normalize tax lines data to Collection of TaxLine objects
     */
    protected function setTaxLines($value)
    {
        if ($value instanceof Collection && $value->every(fn ($item) => $item instanceof TaxLine)) {
            return $this->taxes = $value;
        }

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
                return $this->getCalculated('tax_lines', fn () => $this->taxes->map(function ($item) {
                    return $item->fill([
                        'amount' => $this->getTaxTotal($item),
                    ]);
                }));
            },
            set: fn ($value) => $this->setTaxLines($value)
        );
    }

    protected function totalLineItems(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->getCalculated('total_line_items', fn () => $this->line_items->sum('quantity'));
            }
        );
    }

    protected function lineItemsQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->getCalculated('line_items_quantity', fn () => $this->line_items->sum('quantity'));
            }
        );
    }

    protected function taxableLineItems(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getCalculated(
                'taxable_line_items',
                fn () => $this->line_items->where('taxable', true)->sum('quantity')
            )
        );
    }

    protected function subTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->getCalculated('sub_total', function () {
                    if (is_null($this->line_items)) {
                        return 0;
                    }

                    // sub_total represents the gross amount (price * quantity)
                    return round($this->line_items->sum(fn ($item) => $item->sub_total), 2);
                });
            }
        );
    }

    protected function taxableSubTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->getCalculated('taxable_sub_total', function () {
                    if (is_null($this->line_items)) {
                        return 0;
                    }

                    return $this->line_items->where('taxable', true)->sum(fn ($item) => $item->sub_total);
                });
            }
        );
    }

    protected function discountTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->getCalculated('discount_total', function () {
                    // Sum line-item discounts
                    $discountTotal = $this->line_items?->sum(fn ($item) => $item->discount_amount) ?? 0;

                    // Add order-level discount
                    $discount = $this->discount;
                    if ($discount && $discount instanceof DiscountLine) {
                        if ($discount->isFixedAmount()) {
                            $discountTotal += $discount->value;
                        } else {
                            // Apply percentage to net subtotal (gross - line discounts)
                            $netSubTotal = $this->sub_total - $this->line_items?->sum(fn ($item) => $item->discount_amount);
                            $discountTotal += round($netSubTotal * $discount->value / 100, 2);
                        }
                    }

                    return round($discountTotal, 2);
                });
            }
        );
    }

    protected function discountPerItem(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->getCalculated('discount_per_item', function () {
                    if (! $this->total_line_items) {
                        return 0;
                    }

                    return $this->discount_total / $this->total_line_items;
                });
            }
        );
    }

    protected function taxableDiscount(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->getCalculated('taxable_discount', function () {
                    // Start with line-item discounts for taxable items
                    $taxableDiscount = $this->line_items?->where('taxable', true)->sum(fn ($item) => $item->discount_amount) ?? 0;

                    // Add allocated order-level discount
                    if ($this->hasDiscount()) {
                        $orderDiscount = $this->discount;
                        $netSubTotal = max(0.01, $this->sub_total - $this->line_items?->sum(fn ($item) => $item->discount_amount));
                        $netTaxableSubTotal = $this->line_items?->where('taxable', true)->sum(fn ($item) => $item->total) ?? 0;

                        if ($orderDiscount->isFixedAmount()) {
                            // Proportional allocation of fixed discount
                            $taxableDiscount += round($orderDiscount->value * ($netTaxableSubTotal / $netSubTotal), 2);
                        } else {
                            // Percentage of net taxable items
                            $taxableDiscount += round($netTaxableSubTotal * $orderDiscount->value / 100, 2);
                        }
                    }

                    return round($taxableDiscount, 2);
                });
            }
        );
    }

    protected function hasCompoundTax(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getCalculated(
                'has_compound_tax',
                fn () => $this->taxes->where('type', 'compounded')->count() > 0
            )
        );
    }

    protected function taxTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->getCalculated('tax_total', function () {
                    // If tax lines already have calculated amounts, use them directly
                    $taxLinesWithAmounts = collect($this->attributes['tax_lines'] ?? [])->filter(function ($tax) {
                        return isset($tax['amount']) && $tax['amount'] > 0;
                    });

                    if ($taxLinesWithAmounts->isNotEmpty()) {
                        return round($taxLinesWithAmounts->sum('amount'), 2);
                    }

                    // Otherwise, use the existing calculation logic
                    if (! $this->collect_tax) {
                        return 0;
                    }

                    return round(collect($this->tax_lines)->sum('amount'), 2);
                });
            }
        );
    }

    protected function totalTaxable(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getCalculated(
                'total_taxable',
                fn () => round($this->taxable_sub_total - $this->taxable_discount, 2)
            )
        );
    }

    protected function defaultTaxTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->getCalculated(
                    'default_tax_total',
                    fn () => $this->taxes->whereNotIn('type', ['compounded'])->map(function ($tax) {
                        return round(($this->total_taxable * $tax->rate) / 100, 2);
                    })->sum()
                );
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
            get: fn () => $this->getCalculated('shipping_total', fn () => 0)
        );
    }

    protected function grandTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->getCalculated(
                    'grand_total',
                    fn () => round(($this->sub_total + $this->tax_total + $this->shipping_total - $this->discount_total) ?? 0, 2)
                );
            }
        );
    }
}
