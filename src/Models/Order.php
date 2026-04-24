<?php

namespace Foundry\Models;

use Barryvdh\DomPDF\Facade\Pdf;
use Foundry\Actions\Order\UpdateOrCreate;
use Foundry\Concerns\Core;
use Foundry\Concerns\HasRefunds;
use Foundry\Concerns\OrderStatus;
use Foundry\Contracts\Currencyable;
use Foundry\Contracts\PaymentInterface;
use Foundry\Database\Factories\OrderFactory;
use Foundry\Facades\Currency;
use Foundry\Models\Order\Contact;
use Foundry\Models\Order\Customer;
use Foundry\Models\Order\DiscountLine;
use Foundry\Models\Order\LineItem;
use Foundry\Models\Order\TaxLine;
use Foundry\Enum\OrderStatus as OrderStatusEnum;
use Foundry\Enum\PaymentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Order extends Model implements Currencyable
{
    use Core, HasRefunds, OrderStatus;

    // Cancellation reasons constants
    const REASON_CUSTOMER = 'Customer changed/cancelled order';

    const REASON_FRAUD = 'Fraudulent order';

    const REASON_DECLINED = 'Payment declined';

    const REASON_UNKNOWN = 'Unknown';

    // TODO: Fix this issue of array to string converstion
    protected $logIgnore = ['metadata'];

    protected $fillable = [
        'number',
        'customer_id',
        'orderable_id',
        'orderable_type',
        'billing_address',
        'note',
        'collect_tax',
        'source',
        'sub_total',
        'tax_total',
        'discount_total',
        'grand_total',
        'paid_total',
        'refund_total',
        'line_items_quantity',
        'due_date',
        'status',
        'payment_status',
        'cancelled_at',
        'cancel_reason',
        'metadata',
    ];

    protected $casts = [
        'collect_tax' => 'boolean',
        'billing_address' => 'array',
        'due_date' => 'datetime',
        'metadata' => 'array',
        'cancelled_at' => 'datetime',
        'sub_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'refund_total' => 'decimal:2',
        'line_items_quantity' => 'integer',
        'status' => OrderStatusEnum::class,
        'payment_status' => PaymentStatus::class,
    ];

    protected $hidden = [
        'customer_id',
        'orderable_id',
        'orderable_type',
    ];

    protected $with = [
        'customer',
        'contact',
    ];

    protected $appends = [
        'total_line_items',
        'amount',
        'due_amount',
        'refundable_amount',
        'has_due',
        'has_payment',
        'is_paid',
        'is_cancelled',
        'is_completed',
        'can_edit',
        'can_refund',
        'reference',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Alias for customer relationship (for export compatibility)
     */
    public function user()
    {
        return $this->customer();
    }

    public function line_items()
    {
        return $this->morphMany(LineItem::class, 'itemable')->where('quantity', '>', 0);
    }

    public function tax_lines()
    {
        return $this->morphMany(TaxLine::class, 'taxable');
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'paymentable')
            ->whereIn('status', [
                Payment::STATUS_COMPLETED,
                Payment::STATUS_REFUNDED,
                Payment::STATUS_PARTIALLY_REFUNDED,
            ]);
    }

    public function discount()
    {
        return $this->morphOne(DiscountLine::class, 'discountable');
    }

    public function contact()
    {
        return $this->morphOne(Contact::class, 'contactable');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function orderable()
    {
        return $this->morphTo()->withOnly([]);
    }

    public function hasDiscount(): bool
    {
        return ! is_null($this->discount) ?: false;
    }

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->total(),
        );
    }

    protected function dueAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->grand_total - $this->paid_total, 2),
        );
    }

    protected function refundableAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->paid_total - $this->refund_total, 2),
        );
    }

    protected function hasDue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->due_amount > 0,
        );
    }

    protected function hasPayment(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->paid_total > 0,
        );
    }

    protected function totalLineItems(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->line_items_quantity) {
                    return '0 Items';
                }

                return "{$this->line_items_quantity} Item".($this->line_items_quantity > 1 ? 's' : '');
            },
        );
    }

    protected function isCompleted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === OrderStatusEnum::COMPLETED,
        );
    }

    protected function isCancelled(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === OrderStatusEnum::CANCELLED,
        );
    }

    protected function isPaid(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payment_status === PaymentStatus::PAID,
        );
    }

    protected function canEdit(): Attribute
    {
        return Attribute::make(
            get: fn () => ! in_array($this->status, [OrderStatusEnum::CANCELLED, OrderStatusEnum::COMPLETED]),
        );
    }

    protected function canRefund(): Attribute
    {
        return Attribute::make(
            get: fn () => in_array($this->payment_status, [PaymentStatus::PAID]) &&
                ! in_array($this->payment_status, [PaymentStatus::REFUNDED]),
        );
    }

    /**
     * Sync line items with the order. If $detach is true, it will remove any line items that are not in the provided collection.
     *
     * @param  Collection|array  $line_items  The line items to sync
     * @param  bool  $detach  Whether to detach line items that are not in the provided collection
     * @return void
     */
    public function syncLineItems($line_items, $detach = true)
    {
        if (is_array($line_items)) {
            $line_items = collect($line_items);
        } elseif (! $line_items instanceof Collection) {
            throw new \InvalidArgumentException('Line items must be an array or a Collection.');
        }

        if ($detach) {
            // delete removed line_items
            $this->line_items()
                ->whereNotIn('id', $line_items->pluck('id')->filter())
                ->get()
                ->each(function ($item) {
                    $item->delete();
                });
        }

        // update or create line_items
        foreach ($line_items as $item) {
            // update or create the product
            $product = $this->line_items()->updateOrCreate([
                'id' => has($item)->id,
            ], Arr::only((array) $item, (new LineItem)->getFillable()));

            // update the discount
            if (! empty(has($item)->discount)) {
                $product->discount()->updateOrCreate([
                    'id' => has($item['discount'])->id,
                ], (new DiscountLine($item['discount']))->toArray());
            } else {
                $product->discount()->delete();
            }
        }
    }

    public function syncLineItemsWithoutDetach($line_items)
    {
        $this->syncLineItems($line_items, false);
    }

    public function duplicate()
    {
        $replicate = $this->replicate([
            'number',
            'created_at',
            'updated_at',
            'due_date',
        ])->toArray();

        return static::create($replicate);
    }

    /**
     * Create an order based on provided attributes with related data handling (line items, discounts, payments).
     *
     * @return Order
     */
    public static function create(array $attributes = [])
    {
        return app(UpdateOrCreate::class)->execute($attributes);
    }

    /**
     * Update the order with provided attributes and handle related data updates (line items, discounts, payments).
     *
     * @return bool
     */
    public function update(array $attributes = [], array $options = [])
    {
        return app(UpdateOrCreate::class)->execute($attributes, $options, $this);
    }

    /**
     * Mark order as paid
     */
    public function markAsPaid($payment = null, array $transaction = [])
    {
        // Update payment and order statuses using centralized handler
        $order = $this->updatePaymentStatus(
            $payment,
            $transaction,
            PaymentStatus::COMPLETED,
            PaymentStatus::PAID,
            OrderStatusEnum::PROCESSING
        );

        // Notify linked model (e.g., Subscription) of successful payment
        /** @var Subscription $orderable */
        $orderable = $order->orderable;
        if ($orderable && method_exists($orderable, 'paymentConfirmation')) {
            $orderable->paymentConfirmation($order);
        }

        return $order;
    }

    /**
     * Mark order as paid using wallet
     */
    public function markAsPaidUsingWallet(array $transaction = [])
    {
        return $this->markAsPaid(PaymentMethod::walletId(), $transaction);
    }

    /**
     * Mark order as payment pending
     */
    public function markAsPaymentPending($payment = null, array $transaction = [])
    {
        $order = $this->updatePaymentStatus(
            $payment,
            $transaction,
            PaymentStatus::PENDING,
            PaymentStatus::PAYMENT_PENDING,
            OrderStatusEnum::PENDING_PAYMENT
        );

        // Notify linked model (e.g., Subscription) of pending payment
        $orderable = $order->orderable;
        if ($orderable && method_exists($orderable, 'paymentPending')) {
            $orderable->paymentPending($order);
        }

        return $order;
    }

    /**
     * Mark order as payment failed
     */
    public function markAsPaymentFailed($payment = null, array $transaction = [])
    {
        $order = $this->updatePaymentStatus(
            $payment,
            $transaction,
            PaymentStatus::FAILED,
            PaymentStatus::PAYMENT_FAILED,
            OrderStatusEnum::PENDING_PAYMENT
        );

        // Notify linked model (e.g., Subscription) of failed payment
        $orderable = $order->orderable;
        if ($orderable && method_exists($orderable, 'paymentFailed')) {
            $orderable->paymentFailed($order);
        }

        return $order;
    }

    /**
     * Update payment and order status in a centralized way
     */
    private function updatePaymentStatus(
        $payment,
        array $transaction,
        PaymentStatus $paymentStatus,
        PaymentStatus $orderPaymentStatus,
        OrderStatusEnum $orderStatus
    ) {
        $this->handlePaymentStatusChange($payment, $transaction, $paymentStatus);

        parent::update([
            'payment_status' => $orderPaymentStatus,
            'status' => $orderStatus,
        ]);

        return $this->fresh(['payments']);
    }

    /**
     * Handle payment creation logic for different payment statuses
     */
    private function handlePaymentStatusChange($payment, array $transaction, PaymentStatus $paymentStatus)
    {
        if ($payment instanceof PaymentInterface) {
            $this->createPayment($payment, $transaction);
        } elseif ($payment) {
            // Robustly handle if an object/array was passed instead of just an ID/slug
            $paymentMethodId = is_array($payment) ? ($payment['id'] ?? null) : $payment;

            if ($paymentMethodId) {
                $transactionAmount = $transaction['amount'] ?? $this->grand_total;

                // Guard against under-payment: reject if paid amount does not match grand_total
                if ($paymentStatus === PaymentStatus::COMPLETED && (float) $transactionAmount < (float) $this->grand_total) {
                    throw new \InvalidArgumentException(
                        "Payment amount mismatch: received {$transactionAmount}, expected {$this->grand_total} for order #{$this->number}."
                    );
                }

                $this->createPayment([
                    'payment_method_id' => $paymentMethodId,
                    'transaction_id' => $transaction['id'] ?? null,
                    'amount' => $transactionAmount,
                    'status' => $paymentStatus,
                    'note' => $transaction['note'] ?? null,
                    'gateway_response' => $transaction['gateway_response'] ?? null,
                    'processed_at' => $transaction['processed_at'] ?? now(),
                    'metadata' => $transaction['metadata'] ?? null,
                ]);
            }
        }
    }

    protected function reference(): Attribute
    {
        return Attribute::make(
            get: fn () => 'ORD-'.date('y').$this->number,
        );
    }

    public function scopeSortBy($query, $column = 'CREATED_AT_ASC', $direction = 'asc')
    {
        switch ($column) {
            case 'CUSTOMER_NAME_ASC':
                $query->orderByRaw('(SELECT CONCAT(`first_name`, `first_name`) AS name FROM users WHERE users.id = orders.customer_id) ASC');
                break;

            case 'CUSTOMER_NAME_DESC':
                $query->orderByRaw('(SELECT CONCAT(`first_name`, `first_name`) AS name FROM users WHERE users.id = orders.customer_id) DESC');
                break;

            case 'CREATED_AT_DESC':
                $query->orderBy('created_at', 'desc');
                break;

            case 'CREATED_AT_ASC':
            default:
                $allowedColumns = ['created_at', 'number', 'grand_total', 'status', 'payment_status'];
                $column = in_array($column, $allowedColumns) ? $column : 'created_at';
                $direction = in_array(strtolower((string) $direction), ['asc', 'desc']) ? $direction : 'asc';
                $query->orderBy($column, $direction);
                break;
        }

        return $query;
    }

    public function scopeOnlyOwner($query)
    {
        return $query->where('customer_id', user('id'));
    }

    public function scopeWhereStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeWhereInStatus($query, array $status = [])
    {
        return $query->whereIn('status', $status);
    }

    protected function formatAmount($amount)
    {
        return format_amount($amount);
    }

    protected function billingAddress()
    {
        return (new Address($this->billing_address ?? []))->label;
    }

    public function posPdf()
    {
        return Pdf::loadView('pdfs.order-pos', $this->getShortCodes())->setPaper([0, 0, 260.00, 600.80]);
    }

    public function receiptPdf()
    {
        return Pdf::loadView('pdfs.order-receipt', $this->getShortCodes());
    }

    public function download()
    {
        return $this->receiptPdf()->download("Invoice-{$this->id}.pdf");
    }

    public function total()
    {
        return $this->formatAmount($this->grand_total);
    }

    public function rawAmount()
    {
        return (int) ($this->grand_total * 100);
    }

    protected static function generateNumber()
    {
        do {
            $number = date('y').date('m').str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('number', $number)->exists());

        return $number;
    }

    protected function label(): string
    {
        return $this->options?->title ?? "#{$this->id}";
    }

    public function guardInvalidPayment()
    {
        if ($this->is_paid) {
            throw new \InvalidArgumentException('This invoice has already been paid.', 422);
        }

        if ($this->grand_total <= 0) {
            throw new \InvalidArgumentException('The invoice amount must be greater than zero.', 422);
        }
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', PaymentStatus::PAID);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', OrderStatusEnum::PENDING_PAYMENT);
    }

    public function scopeByPaymentStatus($query, $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    public function isPendingPayment(): bool
    {
        return $this->payment_status === PaymentStatus::PAYMENT_PENDING;
    }

    public function cancel($reason = null): bool
    {
        return (bool) $this->update([
            'status' => OrderStatusEnum::CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
    }

    /**
     * Get gross sales (before discounts and refunds)
     */
    protected function grossSales(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->sub_total + $this->tax_total, 2),
        );
    }

    /**
     * Get net sales (after discounts and refunds)
     */
    protected function netSales(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->gross_sales - $this->discount_total - $this->refund_total, 2),
        );
    }

    /**
     * Get discount rate as percentage
     */
    protected function discountRate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->gross_sales > 0
                ? round(($this->discount_total / $this->gross_sales) * 100, 2)
                : 0.0,
        );
    }

    /**
     * Get refund rate as percentage
     */
    protected function refundRate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->gross_sales > 0
                ? round(($this->refund_total / $this->gross_sales) * 100, 2)
                : 0.0,
        );
    }

    /**
     * Check if order is a first-time purchase for customer
     */
    public function isFirstPurchase(): bool
    {
        if (! $this->customer_id) {
            return true;
        }

        return static::where('customer_id', $this->customer_id)
            ->where('payment_status', self::STATUS_PAID)
            ->where('id', '<', $this->id)
            ->doesntExist();
    }

    /**
     * Check if order is overdue (unpaid past due date)
     */
    public function isOverdue(): bool
    {
        return $this->payment_status !== self::STATUS_PAID && $this->due_date && $this->due_date->isPast();
    }

    /**
     * Create payment for this order
     */
    public function createPayment($attributes = [], array $transaction = [])
    {
        if ($attributes instanceof PaymentInterface) {
            // Convert PaymentInterface to array
            $attributes = $attributes->toArray();
        }

        $attributes = array_merge($attributes, $transaction);

        return Payment::createForOrder($this, $attributes);
    }

    protected static function newFactory()
    {
        return OrderFactory::new();
    }

    /**
     * Save the model, retrying with a new order number on unique violations.
     * This gracefully handles the race window between generateNumber() and INSERT.
     */
    public function save(array $options = []): bool
    {
        try {
            return parent::save($options);
        } catch (UniqueConstraintViolationException $e) {
            // Only retry when the collision is on the order number column
            if (! $this->exists && str_contains($e->getMessage(), 'number')) {
                $this->number = static::generateNumber();

                return parent::save($options);
            }

            throw $e;
        }
    }

    /**
     * Structured data for notification templates (Blade variables supported).
     * Only include safe, formatted values intended for templates.
     */
    public function getShortCodes(): array
    {
        return [
            'app_name' => config('app.name'),
            'logo' => config('app.logo', asset('images/logo.png')),
            'id' => "#{$this->number}",
            'number' => "#{$this->number}",
            'name' => "Order #{$this->number}",
            'date' => optional($this->created_at)->format('d-M-Y'),
            'created_at' => optional($this->created_at)->format('d-m-Y h:i a'),
            'payment_status' => ucfirst($this->payment_status->value),
            'status' => ucfirst($this->status->value),

            // Formatted monetary amounts for display
            'sub_total' => $this->formatAmount($this->sub_total ?? 0),
            'tax_total' => $this->formatAmount($this->tax_total ?? 0),
            'discount_total' => $this->formatAmount($this->discount_total ?? 0),
            'grand_total' => $this->formatAmount($this->grand_total ?? 0),
            'total' => $this->formatAmount($this->grand_total ?? 0),
            'raw_total' => $this->grand_total ?? 0,
            'paid_total' => $this->formatAmount($this->paid_total ?? 0),
            'due_amount' => $this->formatAmount($this->due_amount ?? 0),

            // Convenience flags and links
            'has_due' => (bool) $this->has_due,
            'billing_address' => $this->billingAddress(),
            'phone_number' => optional($this->contact)->phone_number,
            'url' => app_url("/orders/{$this->id}"),
            'payment_url' => app_url("payment/{$this->id}", [
                'redirect' => user_route('/billing'),
            ]),

            // Refund information
            'refund_total' => $this->formatAmount($this->refund_total ?? 0),
            'refundable_amount' => $this->formatAmount($this->refundable_amount ?? 0),
            'can_refund' => (bool) $this->can_refund,

            // Customer info subset - provide fallback if no customer
            'customer' => $this->customer?->getShortCodes() ?? [
                'id' => null,
                'first_name' => 'there',
                'last_name' => '',
                'name' => 'there',
                'email' => '',
                'phone' => '',
            ],
            'customer_name' => optional($this->customer)->name ?? 'NA',

            // Payment information - map once and reuse
            'payments' => $this->payments->sortByDesc('created_at')->map(fn ($payment) => $payment->getShortCodes())->values()->toArray(),

            // Line items for template loops
            'line_items' => $this->line_items,
            'items' => $this->line_items->map(fn ($item) => [
                'title' => $item->title ?? 'Unknown Item',
                'quantity' => $item->quantity,
                'price' => $this->formatAmount($item->price ?? 0),
                'total' => $this->formatAmount($item->total ?? 0),
                'thumbnail' => $item->thumbnail,
            ])->toArray(),
        ];
    }

    /**
     * Get the list of currency fields to be converted.
     *
     * @return array Field names that contain currency amounts
     */
    public function getCurrencyFields(): array
    {
        return [
            'sub_total',
            'tax_total',
            'discount_total',
            'grand_total',
            'paid_total',
            'refund_total',
            'amount',
            'due_amount',
            'refundable_amount',
        ];
    }

    /**
     * Transform the order and its relations for payment processing.
     * Applies currency conversion using the Currency service.
     */
    public function transformForPayment(): array
    {
        // Transform the order itself
        $orderData = Currency::transform($this);

        // Transform relations
        if ($this->relationLoaded('line_items')) {
            $orderData['line_items'] = Currency::transform($this->line_items);
        }

        if ($this->relationLoaded('tax_lines')) {
            $orderData['tax_lines'] = Currency::transform($this->tax_lines);
        }

        if ($this->relationLoaded('discount') && $this->discount) {
            $orderData['discount'] = Currency::transform($this->discount);
        }

        return $orderData;
    }

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {
            if (empty($model->status)) {
                $model->status = self::STATUS_PENDING;
            }

            if (empty($model->number)) {
                $model->number = static::generateNumber();
            }
        });
    }
}
