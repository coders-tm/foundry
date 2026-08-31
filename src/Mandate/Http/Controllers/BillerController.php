<?php

namespace Foundry\Mandate\Http\Controllers;

use Foundry\Mandate\Responses\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse as HttpRedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for managing off-session payment method setup.
 */
class BillerController extends Controller
{
    /**
     * Create / set up a new payment method for the authenticated user.
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string',
        ]);

        $user = $request->user();
        $user->billable($request->provider)->validate($request);

        $result = $user->addPaymentMethod($request->payment_method ?? null, $request->provider);

        if ($result instanceof RedirectResponse) {
            return response()->json([
                'action' => 'redirect',
                'redirect_url' => $result->getUrl(),
                'message' => __('Please complete the Direct Debit setup process.'),
                'data' => $result->getData(),
            ]);
        }

        return response()->json([
            'message' => __('Payment method has been added successfully.'),
            'data' => $result,
        ]);
    }

    /**
     * Handle provider redirect setup callback.
     */
    public function callback(Request $request, string $provider): HttpRedirectResponse
    {
        $redirectRoute = config('foundry.mandate.redirect_route', 'payment-methods.index');

        try {
            $request->user()->handlePaymentCallback($provider, $request);

            return redirect()->route($redirectRoute, [
                'setup' => 'success',
            ]);
        } catch (\Exception $e) {
            report($e);

            return redirect()->route($redirectRoute, [
                'setup' => 'failed',
                'error' => urlencode($e->getMessage()),
            ]);
        }
    }

    /**
     * Confirm a payment method setup.
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string',
            'options' => 'required|array',
            'options.payment_method' => 'required|string',
        ]);

        try {
            $pm = $request->user()->confirmPaymentMethod($request->provider, $request->options);

            return response()->json([
                'message' => __('Payment method has been confirmed successfully.'),
                'data' => $pm,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => __('Failed to confirm payment method.'),
            ], 400);
        }
    }

    /**
     * Remove the authenticated user's saved payment method.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'nullable|string',
        ]);

        $request->user()->removePaymentMethod($request->provider ?? null);

        return response()->json([
            'message' => __('Payment method has been removed successfully.'),
        ]);
    }

    /**
     * Get all saved payment methods and status of the authenticated user.
     */
    public function status(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->paymentMethods()
        );
    }
}
