<?php

namespace Foundry\Billable\Http\Controllers;

use Foundry\Billable\BillableProcessor;
use Foundry\Billable\Responses\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for managing user off-session payment methods.
 */
class BillableController extends Controller
{
    /**
     * Create / set up a new payment method for the authenticated user.
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string',
        ]);

        $manager = $request->user()->billable($request->provider)
            ->setPaymentMethod($request->payment_method ?? null);

        $manager->validate($request);

        $result = $manager->setup();

        if ($result instanceof RedirectResponse) {
            return response()->json([
                'action'       => 'redirect',
                'redirect_url' => $result->getUrl(),
                'message'      => __('Please complete the Direct Debit setup process.'),
                'data'         => $result->getData(),
            ]);
        }

        return response()->json([
            'message' => __('Payment method has been added successfully.'),
            'data'    => $result,
        ]);
    }

    /**
     * Handle provider redirect setup callback.
     */
    public function callback(Request $request, string $provider)
    {
        try {
            BillableProcessor::make($provider)
                ->setUser($request->user())
                ->handleCallback($request);

            return redirect()->to('/billing/payment-method?setup=success');
        } catch (\Exception $e) {
            report($e);

            return redirect()->to('/billing/payment-method?setup=failed&error='.urlencode($e->getMessage()));
        }
    }

    /**
     * Confirm a payment method setup (e.g. 3DS confirmation for Stripe).
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'provider'               => 'required|string',
            'options'                => 'required|array',
            'options.payment_method' => 'required|string',
        ]);

        try {
            $pm = $request->user()->billable()
                ->confirm($request->provider, $request->options);

            return response()->json([
                'message' => __('Payment method has been confirmed successfully.'),
                'data'    => $pm,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => __('Failed to confirm payment method.'),
            ], 400);
        }
    }
}
