<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentMethodController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $paymentMethod = PaymentMethod::query();

        if ($request->boolean('enabled') ?: false) {
            $paymentMethod = $paymentMethod->enabled();
        }

        if ($request->boolean('disabled') ?: false) {
            $paymentMethod = $paymentMethod->disabled();
        }

        if ($request->boolean('manual') ?: false) {
            $paymentMethod = $paymentMethod->manual();
        }

        $paymentMethod = $paymentMethod->orderBy('order', 'asc')->get();

        return response()->json($paymentMethod, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string',
            'provider' => 'required|string',
        ];

        $request->validate($rules);

        $paymentMethod = PaymentMethod::create($request->input());

        return response()->json([
            'data' => $paymentMethod,
            'message' => __('Payment method has been created successfully!'),
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  PaymentMethod  $paymentMethod
     * @return Response
     */
    public function show($paymentMethod)
    {
        $paymentMethod = PaymentMethod::findOrFail($paymentMethod);

        // Gate::authorize('view', $paymentMethod);

        return response()->json($paymentMethod, 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     */
    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $rules = [
            'name' => 'required|string',
            'provider' => 'required|string',
        ];

        $request->validate($rules);

        // Check if the payment method integration_via is enabled
        if ($paymentMethod->integration_via && ! config("{$paymentMethod->integration_via}.enabled", false)) {
            return response()->json([
                'message' => "This payment method is not enabled for integration. Please enable  {$paymentMethod->integration_via} first, then try again.",
            ], 422);
        }

        $paymentMethod->update($request->input());

        return response()->json([
            'data' => $paymentMethod->fresh(),
            'message' => __('Payment method has been updated successfully!'),
        ], 200);
    }

    /**
     * Disable the specified resource in storage.
     *
     * @return Response
     */
    public function disable(Request $request, PaymentMethod $paymentMethod)
    {
        $paymentMethod->update([
            'active' => false,
        ]);

        return response()->json([
            'message' => __('Payment method has been disabled.'),
        ], 200);
    }

    /**
     * Enable the specified resource in storage.
     *
     * @return Response
     */
    public function enable(Request $request, PaymentMethod $paymentMethod)
    {
        $paymentMethod->update([
            'active' => true,
        ]);

        return response()->json([
            'message' => __('Payment method has been enabled.'),
        ], 200);
    }
}
