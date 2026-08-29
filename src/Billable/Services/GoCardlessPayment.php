<?php

namespace Foundry\Billable\Services;

use Foundry\Billable\Payments\GoCardlessPayment as GoCardlessPaymentWrapper;
use Foundry\Foundry;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;

/**
 * GoCardless billable payment service.
 *
 * Manages GoCardless customer records, stores Direct Debit mandate references,
 * and charges Payable entities off-session against active mandates.
 */
class GoCardlessPayment extends BillablePayment
{
    /**
     * Provider identifier.
     */
    public const PROVIDER = 'gocardless';

    /**
     * Set up a saved GoCardless Mandate for the user.
     */
    public function setup()
    {
        if ($this->paymentMethod) {
            return $this->updateMandateAndSetup($this->paymentMethod);
        }

        throw new \Exception('GoCardless requires a redirect flow to be completed.');
    }

    /**
     * Complete mandate registration for GoCardless.
     */
    protected function updateMandateAndSetup(string $mandateId)
    {
        if (! $this->userId) {
            throw new \Exception('No user identified for GoCardless setup.');
        }

        $mandate = Foundry::gocardless()->mandates()->get($mandateId);

        if (! $mandate || $mandate->status !== 'active') {
            throw new \Exception('Mandate is not active or not found.');
        }

        $customer = $this->getOrCreateCustomer(
            $this->userId,
            self::PROVIDER
        );

        if ($mandate->links->customer) {
            $customer->update([
                'provider_id' => $mandate->links->customer,
            ]);
        }

        $this->createOrUpdatePaymentMethod(
            $this->userId,
            self::PROVIDER,
            $mandateId,
            [
                'status' => $mandate->status,
                'reference' => $mandate->reference,
                'scheme' => $mandate->scheme,
                'next_possible_charge_date' => $mandate->next_possible_charge_date,
            ]
        );

        return $customer;
    }

    /**
     * Remove saved GoCardless Mandate for the user.
     */
    public function remove()
    {
        if (! $this->userId) {
            throw new \Exception('No user identified for GoCardless removal.');
        }

        $this->deletePaymentMethod(
            $this->userId,
            self::PROVIDER
        );

        return true;
    }

    /**
     * Charge a Payable entity using a saved GoCardless Mandate.
     *
     * @param  Payable  $payable
     * @param  mixed  $paymentMethod
     * @param  array  $options
     * @return PaymentResult
     *
     * @throws \Exception
     */
    public function charge(Payable $payable, mixed $paymentMethod = null, array $options = []): PaymentResult
    {
        if (! $this->userId) {
            throw new \Exception('No user identified for charging.');
        }

        $pmRecord = $paymentMethod;
        if (! $pmRecord) {
            $pmRecord = $this->getPaymentMethod($this->userId, self::PROVIDER);
        }

        $pmId = is_object($pmRecord) ? ($pmRecord->provider_id ?? null) : $pmRecord;

        if (! $pmId) {
            throw new \Exception('No payment method (mandate) found for GoCardless charging.');
        }

        try {
            $client = Foundry::gocardless();
            $amountInCents = (int) round($payable->getGatewayAmount() * 100);

            $params = array_merge([
                'amount' => $amountInCents,
                'currency' => $payable->getCurrency(),
                'links' => [
                    'mandate' => $pmId,
                ],
                'metadata' => array_merge($payable->getMetadata(), [
                    'user_id' => $this->userId,
                ]),
            ], $options);

            $payment = $client->payments()->create(['params' => $params]);
            $wrapper = new GoCardlessPaymentWrapper([
                'id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'created_at' => $payment->created_at,
            ]);

            return PaymentResult::success(
                paymentData: null,
                transactionId: $payment->id,
                status: $payment->status,
                metadata: [
                    'wrapper' => $wrapper,
                    'payment' => (array) $payment,
                ]
            );
        } catch (\Exception $e) {
            logger()->error('GoCardless charge failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);

            return PaymentResult::failed("Charge failed: {$e->getMessage()}");
        }
    }
}
