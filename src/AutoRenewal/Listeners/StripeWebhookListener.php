<?php

namespace Foundry\AutoRenewal\Listeners;

use Foundry\Events\Stripe\WebhookReceived;

/**
 * Listener for processing Stripe webhook events related to auto-renewal.
 *
 * Handles Stripe webhook events that are relevant to subscription auto-renewal,
 * such as payment intent confirmations, setup intent successes, and card failures.
 */
class StripeWebhookListener
{
    /**
     * Handle the Stripe webhook received event.
     *
     * @return void
     */
    public function handle(WebhookReceived $event)
    {
        $payload = $event->payload;
        $eventType = $payload['type'] ?? null;

        // Handle different Stripe event types
        switch ($eventType) {
            case 'charge.succeeded':
                $this->handleChargeSucceeded($payload['data']['object']);
                break;

            case 'charge.failed':
                $this->handleChargeFailed($payload['data']['object']);
                break;

            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($payload['data']['object']);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($payload['data']['object']);
                break;

            case 'setup_intent.succeeded':
                $this->handleSetupIntentSucceeded($payload['data']['object']);
                break;

            default:
                // Ignore other event types
                break;
        }
    }

    /**
     * Handle charge succeeded webhook.
     *
     * @return void
     */
    protected function handleChargeSucceeded(array $charge)
    {
        logger()->info('Stripe charge succeeded', [
            'charge_id' => $charge['id'],
            'amount' => $charge['amount'],
            'currency' => $charge['currency'],
        ]);

        // Update subscription payment record if needed
        $this->updatePaymentStatus($charge, 'succeeded');
    }

    /**
     * Handle charge failed webhook.
     *
     * @return void
     */
    protected function handleChargeFailed(array $charge)
    {
        logger()->warning('Stripe charge failed', [
            'charge_id' => $charge['id'],
            'amount' => $charge['amount'],
            'failure_code' => $charge['failure_code'],
            'failure_message' => $charge['failure_message'],
        ]);

        // Update subscription payment record
        $this->updatePaymentStatus($charge, 'failed');
    }

    /**
     * Handle payment intent succeeded webhook.
     *
     * @return void
     */
    protected function handlePaymentIntentSucceeded(array $paymentIntent)
    {
        logger()->info('Stripe payment intent succeeded', [
            'payment_intent_id' => $paymentIntent['id'],
            'amount' => $paymentIntent['amount'],
            'status' => $paymentIntent['status'],
        ]);

        $this->updatePaymentStatus($paymentIntent, 'succeeded');
    }

    /**
     * Handle payment intent payment failed webhook.
     *
     * @return void
     */
    protected function handlePaymentIntentFailed(array $paymentIntent)
    {
        logger()->warning('Stripe payment intent failed', [
            'payment_intent_id' => $paymentIntent['id'],
            'amount' => $paymentIntent['amount'],
            'status' => $paymentIntent['status'],
            'last_payment_error' => $paymentIntent['last_payment_error'],
        ]);

        $this->updatePaymentStatus($paymentIntent, 'failed');
    }

    /**
     * Handle setup intent succeeded webhook.
     *
     * @return void
     */
    protected function handleSetupIntentSucceeded(array $setupIntent)
    {
        logger()->info('Stripe setup intent succeeded', [
            'setup_intent_id' => $setupIntent['id'],
            'payment_method' => $setupIntent['payment_method'],
        ]);
    }

    /**
     * Update payment status in the system.
     *
     * This is a placeholder method that should be integrated with your
     * order/payment logging system.
     *
     * @return void
     */
    protected function updatePaymentStatus(array $data, string $status)
    {
        // Extract subscription ID from metadata if available
        $subscriptionId = $data['metadata']['subscription_id'] ?? null;
        $orderId = $data['metadata']['order_id'] ?? null;

        if ($subscriptionId) {
            logger()->debug('Payment status update', [
                'subscription_id' => $subscriptionId,
                'order_id' => $orderId,
                'status' => $status,
            ]);
        }
    }
}
