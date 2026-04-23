<?php

namespace Foundry\AutoRenewal\Http\Controllers;

use Foundry\AutoRenewal\AutoRenewalManager;
use Foundry\AutoRenewal\Services\GoCardlessSubscription;
use Foundry\Models\Subscription;
use Illuminate\Auth\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * API Controller for managing user auto-renewal settings.
 *
 * Provides endpoints for users to view, setup, and remove auto-renewal
 * from their subscriptions with different payment providers.
 */
class AutoRenewalController extends Controller
{
    /**
     * Get the auto-renewal status for a subscription.
     *
     *
     * @throws AuthorizationException
     */
    public function status(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('view', $subscription);

        $manager = new AutoRenewalManager($subscription);

        return response()->json([
            'status' => $manager->status(),
            'subscription' => [
                'id' => $subscription->id,
                'name' => $subscription->name,
                'provider' => $subscription->provider,
            ],
        ]);
    }

    /**
     * Setup auto-renewal for a subscription.
     *
     * For Stripe, accepts a payment_method_id.
     * For GoCardless, initiates a redirect flow.
     *
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function setup(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        $validated = $request->validate([
            'payment_method' => 'nullable|string',
            'provider' => 'required_if:payment_method,null|nullable|string',
        ]);

        try {
            $manager = new AutoRenewalManager($subscription);

            if ($validated['provider'] ?? null) {
                $manager->setProvider($validated['provider']);
            }

            if ($validated['payment_method'] ?? null) {
                $manager->setPaymentMethod($validated['payment_method']);
            }

            // For GoCardless, we might need to initiate a redirect flow
            if ($subscription->provider === 'gocardless' && ! $validated['payment_method']) {
                $goCardless = new GoCardlessSubscription($subscription);
                $redirectFlow = $goCardless->createRedirectFlow();

                return response()->json([
                    'status' => 'redirect_required',
                    'redirect_url' => $redirectFlow['redirect_url'],
                    'flow_id' => $redirectFlow['flow_id'],
                ]);
            }

            $result = $manager->setup();

            return response()->json([
                'status' => 'setup_complete',
                'subscription' => $result->toArray(),
                'auto_renewal' => $manager->status(),
            ]);
        } catch (\Exception $e) {
            logger()->error('Auto-renewal setup failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to setup auto-renewal',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Handle GoCardless redirect flow completion.
     *
     *
     * @throws AuthorizationException
     */
    public function handleCallback(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        $validated = $request->validate([
            'flow_id' => 'required|string',
            'session_token' => 'required|string',
        ]);

        try {
            $manager = new AutoRenewalManager($subscription);
            $result = $manager->handleCallback($request);

            return response()->json([
                'status' => 'callback_processed',
                'subscription' => $result->toArray(),
                'auto_renewal' => $manager->status(),
            ]);
        } catch (\Exception $e) {
            logger()->error('Auto-renewal callback failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to process callback',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove auto-renewal from a subscription.
     *
     *
     * @throws AuthorizationException
     */
    public function remove(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        try {
            $manager = new AutoRenewalManager($subscription);
            $result = $manager->remove();

            return response()->json([
                'status' => 'removal_complete',
                'subscription' => $result->toArray(),
                'auto_renewal' => $manager->status(),
            ]);
        } catch (\Exception $e) {
            logger()->error('Auto-renewal removal failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to remove auto-renewal',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
