<?php

namespace Foundry\Http\Controllers\Webhook;

use Foundry\Events\GoCardless\Mandate;
use Foundry\Events\GoCardless\Payment;
use Foundry\Events\GoCardless\Subscription as SubscriptionEvent;
use Foundry\Events\GoCardless\WebhookHandled;
use Foundry\Events\GoCardless\WebhookReceived;
use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Models\Payment as ModelsPayment;
use Foundry\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GoCardlessController extends Controller
{
    /**
     * Handle incoming GoCardless webhook
     *
     * @return Response
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $webhookSignature = $request->header('Webhook-Signature');

        // Verify webhook signature
        if (! $this->verifyWebhookSignature($payload, $webhookSignature)) {
            return response('Webhook signature verification failed', 401);
        }

        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response('Invalid payload', 400);
        }

        try {
            // Process events
            $events = $data['events'] ?? [];

            if (empty($events)) {
                return $this->missingMethod();
            }

            WebhookReceived::dispatch($data);

            foreach ($events as $event) {
                $this->processEvent($event);
            }

            WebhookHandled::dispatch($data);

            return $this->successMethod();
        } catch (\Throwable $e) {
            return response('Webhook processing failed: '.$e->getMessage(), 500);
        }
    }

    /**
     * Verify webhook signature
     *
     * @param  string  $payload
     * @param  string  $signature
     * @return bool
     */
    protected function verifyWebhookSignature($payload, $signature)
    {
        if (empty($signature)) {
            return false;
        }

        // Get webhook secret from config
        $webhookSecret = config('gocardless.webhook_secret');

        if (empty($webhookSecret)) {
            return false;
        }

        // GoCardless sends the raw signature as a hex string
        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process GoCardless event using dynamic method dispatch
     *
     * @param  array  $event
     * @return void
     */
    protected function processEvent($event)
    {
        $resourceType = $event['resource_type'] ?? '';
        $action = $event['action'] ?? '';

        if (empty($resourceType) || empty($action)) {
            return;
        }

        // Create method name like handlePaymentsConfirmed
        $methodName = 'handle'.Str::studly($resourceType).Str::studly($action);

        if (method_exists($this, $methodName)) {
            $this->{$methodName}($event);
        }
    }

    /**
     * Handle successful calls on the controller.
     *
     * @param  array  $parameters
     * @return Response
     */
    protected function successMethod($parameters = [])
    {
        return new Response('Webhook Handled', 200);
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array  $parameters
     * @return Response
     */
    protected function missingMethod($parameters = [])
    {
        return new Response;
    }

    /**
     * Find order by ID and load its relationships
     *
     * @param  string  $orderId
     * @return Order|null
     */
    protected function findOrder($orderId)
    {
        if (! $orderId) {
            return null;
        }

        return Order::find($orderId)?->load('orderable') ?? null;
    }

    /**
     * Find subscription by GoCardless mandate ID
     *
     * @param  string  $mandateId
     * @return Subscription|null
     */
    protected function findSubscriptionByMandateId($mandateId)
    {
        if (! $mandateId) {
            return null;
        }

        return Subscription::where('options->gocardless_provider_id', $mandateId)->first();
    }

    /**
     * Check if payment has already been processed
     */
    protected function isPaymentAlreadyProcessed(string $paymentId): bool
    {
        return ModelsPayment::where('payment_method_id', config('gocardless.id'))
            ->where('transaction_id', $paymentId)
            ->exists();
    }

    /**
     * Handle payment confirmed events
     *
     * @param  array  $event
     * @return void
     */
    protected function handlePaymentsConfirmed($event)
    {
        Payment\PaymentConfirmed::dispatch($event);

        $paymentId = $event['links']['payment'] ?? null;
        $orderId = $event['resource_metadata']['order_id'] ?? null;

        if (! $paymentId) {
            return;
        }

        try {
            // Get the payment details from GoCardless
            $payment = Foundry::gocardless()->payments()->get($paymentId);
            $mandateId = $payment->links->mandate ?? null;

            // Process order if exists
            if ($order = $this->findOrder($orderId)) {
                // Update order payment status
                $order->markAsPaid(config('gocardless.id'), [
                    'id' => $payment->id,
                    'amount' => $payment->amount / 100,
                    'status' => $payment->status,
                ]);
            }
            // Update subscription if exists
            elseif ($mandateId && ($subscription = $this->findSubscriptionByMandateId($mandateId))) {
                // Skip if payment already processed
                if ($this->isPaymentAlreadyProcessed($payment->id)) {
                    return;
                }

                $paymentData = [
                    'id' => $payment->id,
                    'amount' => $payment->amount / 100,
                    'status' => $payment->status,
                    'note' => 'Payment confirmed via GoCardless',
                ];

                // Handle based on subscription payment state
                if ($subscription->hasIncompletePayment()) {
                    $subscription->pay(config('gocardless.id'), $paymentData);
                } else {
                    $subscription->renew();
                    $subscription = $subscription->refresh(['latestInvoice']);
                    $subscription->pay(config('gocardless.id'), $paymentData);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error processing payment confirmed event', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'mandate_id' => $mandateId ?? null,
            ]);
        }
    }

    /**
     * Handle payment failed events
     *
     * @param  array  $event
     * @return void
     */
    protected function handlePaymentsFailed($event)
    {
        Payment\PaymentFailed::dispatch($event);

        $paymentId = $event['links']['payment'] ?? null;
        $orderId = $event['resource_metadata']['order_id'] ?? null;

        if (! $paymentId) {
            return;
        }

        try {
            // Get the payment details from GoCardless
            $payment = Foundry::gocardless()->payments()->get($paymentId);
            $mandateId = $payment->links->mandate ?? null;

            $paymentData = [
                'id' => $payment->id,
                'amount' => $payment->amount / 100,
                'status' => $payment->status,
            ];

            // Process order if exists
            if ($order = $this->findOrder($orderId)) {
                $order->markAsPaymentFailed(config('gocardless.id'), $paymentData);
            }
            // Update subscription if exists
            elseif ($mandateId && ($subscription = $this->findSubscriptionByMandateId($mandateId))) {
                // Skip if payment already processed
                if ($this->isPaymentAlreadyProcessed($payment->id)) {
                    return;
                }

                // Skip if subscription already has incomplete payment
                if ($subscription->hasIncompletePayment()) {
                    return;
                }

                // Create a new invoice and mark it as failed
                $subscription->renew();
                $subscription = $subscription->refresh(['latestInvoice']);

                if ($order = $subscription->latestInvoice) {
                    $order->markAsPaymentFailed(config('gocardless.id'), [
                        'id' => $payment->id,
                        'amount' => $payment->amount / 100,
                        'status' => $payment->status,
                        'note' => 'Payment failed via GoCardless',
                    ]);

                    // Call paymentFailed method on subscription
                    $subscription->paymentFailed($order);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error processing payment failed event', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'mandate_id' => $mandateId ?? null,
            ]);
        }
    }

    /**
     * Handle subscription created events
     *
     * @param  array  $event
     * @return void
     */
    protected function handleSubscriptionsCreated($event)
    {
        SubscriptionEvent\SubscriptionCreated::dispatch($event);
    }

    /**
     * Handle subscription cancelled events
     *
     * @param  array  $event
     * @return void
     */
    protected function handleSubscriptionsCancelled($event)
    {
        SubscriptionEvent\SubscriptionCancelled::dispatch($event);
    }

    /**
     * Handle mandate active events
     *
     * @param  array  $event
     * @return void
     */
    protected function handleMandatesActive($event)
    {
        Mandate\MandateActive::dispatch($event);
    }

    /**
     * Handle mandate failed events
     *
     * @param  array  $event
     * @return void
     */
    protected function handleMandatesFailed($event)
    {
        Mandate\MandateFailed::dispatch($event);

        $mandateId = $event['links']['mandate'] ?? null;

        if (! $mandateId) {
            return;
        }

        try {
            // Find subscription directly by mandate ID first
            $subscription = $this->findSubscriptionByMandateId($mandateId);

            // If not found, try to get from mandate metadata
            if (! $subscription) {
                // Get the mandate details from GoCardless
                $mandate = Foundry::gocardless()->mandates()->get($mandateId);
                $subscriptionId = $mandate->metadata->subscription_id ?? null;

                if ($subscriptionId) {
                    $subscription = Subscription::find($subscriptionId);
                }
            }

            if ($subscription) {
                // Mark the subscription as incomplete
                $subscription->paymentFailed();
            }
        } catch (\Throwable $e) {
            Log::error('Error processing mandate failed event', [
                'error' => $e->getMessage(),
                'mandate_id' => $mandateId,
            ]);
        }
    }

    /**
     * Handle mandate cancelled events
     *
     * @param  array  $event
     * @return void
     */
    protected function handleMandatesCancelled($event)
    {
        Mandate\MandateCancelled::dispatch($event);
    }
}
