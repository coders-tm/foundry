<?php

namespace Workbench\App\Http\Controllers\Payment;

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Events\GoCardless\FlowCompleted;
use Foundry\Foundry;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Subscription;
use Foundry\Services\GatewaySubscriptionFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Workbench\App\Http\Controllers\Controller;

class GoCardlessController extends Controller
{
    /**
     * Handle the redirect after successful GoCardless flow completion
     *
     * @return Response
     */
    public function success(Request $request)
    {
        try {
            // Step 1: Validate and extract state data
            $subscription = $this->validateAndGetSubscription($request);
            if (! $subscription) {
                Log::warning('GoCardless flow: Subscription not found');

                return $this->redirectWithError('The payment flow was interrupted. Please try again.');
            }

            $subscription->provider = PaymentMethod::GOCARDLESS;
            // $subscription->save();

            // Step 2: Process the redirect flow
            $flowId = $request->get('redirect_flow_id');
            if (! $flowId) {
                Log::warning('GoCardless flow: Missing redirect_flow_id in callback');

                return $this->redirectWithError('The payment flow was interrupted. Please try again.');
            }

            // Step 3: Complete the flow and set up payments
            $service = GatewaySubscriptionFactory::make($subscription);
            $flow = $service->completeSetup($flowId);

            // Mark the subscription as pending initially - it will be marked active when payment is confirmed
            $subscription->status = SubscriptionStatus::PENDING;
            $subscription->save();

            // Dispatch event with correct parameters - passing flowId as a string
            event(new FlowCompleted($subscription, $flow));

            // Step 4: Return success response
            return $this->redirectWithSuccess('Your Direct Debit mandate has been set up successfully. The payment will be processed shortly.');
        } catch (\Throwable $e) {
            Log::error('GoCardless flow error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return $this->redirectWithError('We encountered an error setting up your Direct Debit: '.$e->getMessage());
        }
    }

    /**
     * Validate state data and retrieve the subscription
     *
     * @return Subscription
     *
     * @throws \Exception
     */
    protected function validateAndGetSubscription(Request $request)
    {
        $state = $request->query('state');

        if (! $state) {
            throw new \Exception('Missing state parameter');
        }

        $subscription = Foundry::$subscriptionModel::find($state);

        if (! $subscription) {
            throw new \Exception('Subscription not found');
        }

        return $subscription;
    }

    /**
     * Redirect with success message
     *
     * @param  string  $message
     * @return RedirectResponse
     */
    protected function redirectWithSuccess($message)
    {
        return redirect(app_url('/billing', [
            'setup' => 'success',
            'message' => $message,
        ]));
    }

    /**
     * Redirect with error message
     *
     * @param  string  $message
     * @return RedirectResponse
     */
    protected function redirectWithError($message = 'An error occurred')
    {
        return redirect(app_url('/billing', [
            'setup' => 'failed',
            'message' => $message,
        ]));
    }
}
