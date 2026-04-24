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
        // Process line items and their discounts
        $line_items = collect($request->line_items ?? [])->map(function ($product) {
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

        $order->fill($request->only([
            'note',
            'collect_tax',
            'attributes',
            'billing_address',
            'source',
        ]));

        // Ensure collect_tax is set to true by default if not provided
        if (! $request->filled('collect_tax')) {
            $order->collect_tax = true;
        }

        $order->setRelation('line_items', $line_items);

        // Set order discount
        $order->setRelation('discount', $request->filled('discount') ? new DiscountLine($request->discount) : null);

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
        } else {
            $order->setRelation('customer', null);
        }

        // Set contact
        $order->setRelation('contact', $request->filled('contact') ? new Contact($request->contact) : null);

        // Calculate using CartRepository
        // Note: passing relations explicitly helps BaseRepository if it expects them
        $repository = new self(array_merge($order->attributesToArray(), [
            'line_items' => $order->line_items,
            'discount' => $order->discount,
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
