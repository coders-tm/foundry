<?php

namespace Workbench\App\Http\Controllers\User;

use Foundry\Models\Order;
use Foundry\Models\Shop\Product\Variant;
use Foundry\Services\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function index(Request $request, Order $order)
    {
        $order = $order->onlyOwner();

        $order = $order->orderBy($request->sortBy ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->rowsPerPage ?? 15);

        return new ResourceCollection($order);
    }

    public function store(Request $request, Order $order)
    {
        $rules = [
            'line_items' => 'required|array',
        ];
        $customer = user()->toArray();
        $request->merge([
            'status' => Order::STATUS_DRAFT,
            'source' => 'Online',
            'customer' => $customer,
            'contact' => [
                'email' => $customer['email'],
                'phone_number' => $customer['phone_number'],
            ],
            'billing_address' => array_merge($customer['address'], [
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
                'phone_number' => $customer['phone_number'],
            ]),
            'line_items' => collect($request->line_items)->map(function ($item) {
                return array_merge($item, [
                    'variant_title' => optional(Variant::find($item['variant_id']))->title,
                ]);
            })->toArray(),
        ]);

        // Validate those rules
        $this->validate($request, $rules);

        // create the order
        $order = $order->modifyOrCreate(new Resource($request->input()));

        return response()->json([
            'data' => $order->fresh(['line_items']),
            'message' => __('Order has been created successfully!'),
        ], 200);
    }

    public function show(Order $order)
    {
        return response()->json($order, 200);
    }
}
