<?php

namespace Foundry\Repository;

use Foundry\Models\Address;
use Foundry\Models\Order;
use Foundry\Models\Order\Contact;
use Foundry\Models\Order\Customer;
use Foundry\Models\Order\DiscountLine;
use Foundry\Models\Order\LineItem;
use Illuminate\Http\Request;

class OrderRepository extends BaseRepository
{
    /**
     * Create repository from request data and calculate order totals
     */
    public static function fromRequest(Request $request, Order $order): Order
    {
        // Hydrate line items from request or fallback to existing items
        $line_items = collect($request->input('line_items', $order->line_items ? $order->line_items->all() : []))
            ->map(function ($product) {
                $item = LineItem::firstOrNew([
                    'id' => $product['id'] ?? null,
                ], $product)->fill($product);

                // Manually hydrate line-item level discount relation
                if (isset($product['discount'])) {
                    $item->setRelation('discount', DiscountLine::firstOrNew([
                        'id' => $product['discount']['id'] ?? null,
                    ], $product['discount'])->fill($product['discount']));
                }

                return $item;
            });

        $order->created_at = $order->created_at ?? now();

        // Fill basic attributes, specifically excluding relations like tax_lines and discount
        $order->fill($request->only([
            'note',
            'collect_tax',
            'attributes',
            'billing_address',
            'source',
        ]));

        // Ensure collect_tax defaults correctly if missing from request and model
        if (! $request->has('collect_tax') && is_null($order->collect_tax)) {
            $order->collect_tax = true;
        }

        $order->setRelation('line_items', $line_items);

        // Hydrate order-level discount from request or fallback to existing discount
        $discount = $request->filled('discount')
            ? new DiscountLine($request->discount)
            : ($request->has('discount') ? null : $order->discount);
        $order->setRelation('discount', $discount);

        // Process customer data
        if ($request->filled('customer')) {
            $customer = new Customer($request->customer);
            if ($request->filled('customer.address')) {
                $customer->setRelation('address', new Address($request->input('customer.address')));
            }
            $order->setRelation('customer', $customer);
            $order->customer->created_at = $order->customer->created_at ?? now();
            if ($request->filled('customer.id')) {
                $order->customer->id = $request->input('customer.id');
            }
        } elseif (! $request->has('customer')) {
            // Keep existing customer if not provided in request
        } else {
            $order->setRelation('customer', null);
        }

        // Process contact data
        if ($request->filled('contact')) {
            $order->setRelation('contact', new Contact($request->contact));
        } elseif ($request->has('contact')) {
            $order->setRelation('contact', null);
        }

        // Create repository instance for financial calculations
        $repository = new self(array_merge($order->attributesToArray(), [
            'line_items' => $order->line_items ? $order->line_items->all() : [],
            'discount' => $order->discount,
            'tax_lines' => $request->input('tax_lines'), // Let BaseRepository handle default taxes if null/missing
        ]));

        // Apply calculated values back to order
        $order->setRelation('tax_lines', $repository->tax_lines);
        $order->fill([
            'sub_total' => $repository->sub_total,
            'tax_total' => $repository->tax_total,
            'discount_total' => $repository->discount_total,
            'grand_total' => $repository->grand_total,
            'line_items_quantity' => $repository->line_items_quantity,
        ]);

        return $order;
    }
}
