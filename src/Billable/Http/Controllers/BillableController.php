<?php

namespace Foundry\Billable\Http\Controllers;

use Foundry\Billable\BillableManager;
use Foundry\Billable\Services\GoCardlessPayment;
use Foundry\Models\Subscription;
use Illuminate\Auth\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * API Controller for managing user auto-renewal payment settings.
 */
class BillableController extends Controller
{
    /**
     * Get the auto-renewal status for a subscription.
     *
     * @throws AuthorizationException
     */
    public function status(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('view', $subscription);

        $manager = new BillableManager($subscription->user);

        return response()->json([
            'status' => $manager->status(),
            'subscription' => [
                'id' => $subscription->id,
                'name' => $subscription->name,
                'provider' => $subscription->provider,
                'auto_renewal_enabled' => $subscription->auto_renewal_enabled,
            ],
        ]);
    }

    /**
     * Setup auto-renewal for a subscription.
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
            $provider = $validated['provider'] ?? $subscription->provider;
            $manager = new BillableManager($subscription->user);

            if ($provider) {
                $manager->setProvider($provider);
            }

            if ($validated['payment_method'] ?? null) {
                $manager->setPaymentMethod($validated['payment_method']);
            }

            if ($provider === 'gocardless' && ! ($validated['payment_method'] ?? null)) {
                $goCardless = new GoCardlessPayment($subscription->user);
                $redirectFlow = $goCardless->createRedirectFlow();

                return response()->json([
                    'status' => 'redirect_required',
                    'redirect_url' => $redirectFlow['redirect_url'],
                    'flow_id' => $redirectFlow['flow_id'],
                ]);
            }

            $result = $manager->setup();

            if ($provider) {
                $subscription->provider = $provider;
            }
            $subscription->auto_renewal_enabled = true;
            $subscription->save();

            return response()->json([
                'status' => 'setup_complete',
                'subscription' => $subscription->fresh()->toArray(),
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
            $manager = new BillableManager($subscription->user);
            $manager->setProvider('gocardless');
            $result = $manager->handleCallback($request);

            $subscription->provider = 'gocardless';
            $subscription->auto_renewal_enabled = true;
            $subscription->save();

            return response()->json([
                'status' => 'callback_processed',
                'subscription' => $subscription->fresh()->toArray(),
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
     * @throws AuthorizationException
     */
    public function remove(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        try {
            $manager = new BillableManager($subscription->user);
            if ($subscription->provider) {
                $manager->setProvider($subscription->provider);
            }

            $manager->remove();

            $subscription->auto_renewal_enabled = false;
            $subscription->save();

            return response()->json([
                'status' => 'removal_complete',
                'subscription' => $subscription->fresh()->toArray(),
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
