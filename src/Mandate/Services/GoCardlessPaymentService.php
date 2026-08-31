<?php

namespace Foundry\Mandate\Services;

use Foundry\Foundry;
use Foundry\Mandate\Models\PaymentMethod;
use Foundry\Mandate\Responses\RedirectResponse;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Services\PaymentProvider;
use Illuminate\Http\Request;

/**
 * GoCardless payment service implementation.
 */
class GoCardlessPaymentService extends PaymentService
{
    /**
     * Provider identifier.
     */
    public const PROVIDER = PaymentProvider::GOCARDLESS;

    /**
     * Handle GoCardless redirect flow callback.
     *
     * @return PaymentMethod
     *
     * @throws \Exception
     */
    public function handleCallback(Request $request): mixed
    {
        $flowId = $request->get('flow_id');
        if (! $flowId) {
            throw new \Exception('Missing flow_id in callback.');
        }

        $client = Foundry::gocardless();
        $completedFlow = $client->redirectFlows()->complete($flowId, ['session_token' => $request->get('session_token')]);

        if (! $completedFlow || ! $completedFlow->links->mandate) {
            throw new \Exception('Failed to complete redirect flow.');
        }

        $this->paymentMethod = $completedFlow->links->mandate;

        return $this->setup();
    }

    /**
     * Set up a saved GoCardless Mandate.
     *
     * @return PaymentMethod|RedirectResponse
     */
    public function setup(): mixed
    {
        if ($this->paymentMethod) {
            return $this->updateMandateAndSetup($this->paymentMethod);
        }

        return $this->createRedirectFlow();
    }

    /**
     * Initiate a GoCardless redirect flow for Direct Debit setup.
     *
     *
     * @throws \Exception
     */
    public function createRedirectFlow(?string $successUrl = null): RedirectResponse
    {
        if (! $this->getUserId()) {
            throw new \Exception('User model key is required for GoCardless redirect flow.');
        }

        $user = $this->user;

        $client = Foundry::gocardless();
        $sessionToken = bin2hex(random_bytes(16));

        $redirectFlow = $client->redirectFlows()->create([
            'params' => [
                'description' => 'Direct Debit Mandate Setup',
                'session_token' => $sessionToken,
                'success_redirect_url' => $successUrl ?? route('payment.gocardless.success'),
                'prefilled_customer' => [
                    'given_name' => $user->first_name ?? $user->name ?? '',
                    'family_name' => $user->last_name ?? '',
                    'email' => $user->email ?? '',
                ],
            ],
        ]);

        return new RedirectResponse($redirectFlow->redirect_url, [
            'flow_id' => $redirectFlow->id,
            'session_token' => $sessionToken,
        ]);
    }

    /**
     * Complete mandate registration for GoCardless.
     *
     *
     * @throws \Exception
     */
    protected function updateMandateAndSetup(string $mandateId): PaymentMethod
    {
        if (! $this->getUserId()) {
            throw new \Exception('User model key is required for GoCardless setup.');
        }

        $mandate = Foundry::gocardless()->mandates()->get($mandateId);

        if (! $mandate || $mandate->status !== 'active') {
            throw new \Exception('Mandate is not active or not found.');
        }

        $customer = $this->getOrCreateCustomer(
            $this->getUserId(),
            self::PROVIDER
        );

        if ($mandate->links->customer) {
            $customer->update([
                'provider_id' => $mandate->links->customer,
            ]);
        }

        $pm = $this->createOrUpdatePaymentMethod(
            $this->getUserId(),
            self::PROVIDER,
            $mandateId,
            [
                'status' => $mandate->status,
                'reference' => $mandate->reference,
                'scheme' => $mandate->scheme,
                'next_possible_charge_date' => $mandate->next_possible_charge_date,
            ]
        );

        $pm->markAsDefault();

        return $pm;
    }

    /**
     * Remove saved GoCardless Mandate.
     *
     *
     * @throws \Exception
     */
    public function remove(): bool
    {
        if (! $this->getUserId()) {
            throw new \Exception('User model key is required for GoCardless removal.');
        }

        $this->deletePaymentMethod(
            $this->getUserId(),
            self::PROVIDER,
            $this->paymentMethod
        );

        return true;
    }

    /**
     * Charge a Payable entity using a saved GoCardless Mandate.
     *
     *
     * @throws \Exception
     */
    public function charge(Payable $payable, mixed $paymentMethod = null, array $options = []): PaymentResult
    {
        if (! $this->getUserId()) {
            throw new \Exception('User model key is required for charging.');
        }

        $pmRecord = $paymentMethod;
        if (! $pmRecord) {
            $pmRecord = $this->getPaymentMethod($this->getUserId(), self::PROVIDER);
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
                    'user_id' => $this->getUserId(),
                ]),
            ], $options);

            $payment = $client->payments()->create(['params' => $params]);

            return PaymentResult::success(
                paymentData: null,
                transactionId: $payment->id,
                status: $payment->status
            );
        } catch (\Exception $e) {
            logger()->error('GoCardless charge failed', [
                'user_id' => $this->getUserId(),
                'error' => $e->getMessage(),
            ]);

            return PaymentResult::failed("Charge failed: {$e->getMessage()}");
        }
    }
}
