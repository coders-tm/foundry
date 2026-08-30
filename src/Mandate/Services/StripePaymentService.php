<?php

namespace Foundry\Mandate\Services;

use Foundry\Foundry;
use Foundry\Mandate\Exceptions\PaymentIncomplete;
use Foundry\Mandate\Models\PaymentMethod;
use Foundry\Mandate\Models\PaymentMethod as PaymentMethodModel;
use Foundry\Mandate\Payments\StripePayment as StripePaymentWrapper;
use Foundry\Payment\Mappers\StripePayment as StripePaymentMapper;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Services\PaymentProvider;
use Illuminate\Http\Request;
use Stripe\Customer;
use Stripe\Exception\CardException;

/**
 * Stripe payment service implementation.
 */
class StripePaymentService extends PaymentService
{
    /**
     * Provider identifier.
     */
    public const PROVIDER = PaymentProvider::STRIPE;

    /**
     * Validate setup request parameters for Stripe.
     */
    public function validate(Request $request): array
    {
        return $request->validate([
            'payment_method' => 'required|string',
        ]);
    }

    /**
     * Confirm Stripe payment method setup.
     *
     *
     * @throws \Exception
     */
    public function confirm(array $options): PaymentMethod
    {
        $pmId = $options['payment_method'] ?? null;
        if (! $pmId) {
            throw new \InvalidArgumentException('Missing payment_method in options.');
        }

        $paymentMethod = PaymentMethod::where([
            'user_id' => $this->getUserId(),
            'provider' => self::PROVIDER,
            'provider_id' => $pmId,
        ])->firstOrFail();

        $setupIntentId = $options['setup_intent'] ?? null;
        if ($setupIntentId) {
            $setupIntent = Foundry::stripe()->setupIntents->retrieve($setupIntentId);
            if ($setupIntent->status === 'succeeded') {
                $paymentMethod->update([
                    'options' => array_merge((array) $paymentMethod->options, $options, [
                        'requires_action' => false,
                    ]),
                ]);

                return $paymentMethod;
            }
        }

        throw new \Exception('Failed to confirm Stripe payment method setup.');
    }

    /**
     * Set up a saved Stripe payment method.
     *
     *
     * @throws \Exception
     */
    public function setup(): ?PaymentMethod
    {
        if (! $this->getUserId()) {
            throw new \Exception('User model key is required for Stripe setup.');
        }

        $this->getOrCreateCustomer(
            $this->getUserId(),
            self::PROVIDER
        );

        if ($this->paymentMethod) {
            return $this->addOrUpdatePaymentMethod($this->paymentMethod);
        }

        return $this->getPaymentMethod($this->getUserId(), self::PROVIDER);
    }

    /**
     * Remove the saved Stripe payment method.
     *
     *
     * @throws \Exception
     */
    public function remove(): bool
    {
        if (! $this->getUserId()) {
            throw new \Exception('User model key is required for Stripe removal.');
        }

        $this->deletePaymentMethod(
            $this->getUserId(),
            self::PROVIDER
        );

        return true;
    }

    /**
     * Charge a Payable entity using a saved Stripe payment method.
     *
     * @param  PaymentMethodModel|string|null  $paymentMethod
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
            throw new \Exception('No payment method found for Stripe charging.');
        }

        $customer = $this->getOrCreateStripeCustomer();

        if (! $customer->id) {
            throw new \Exception('No Stripe customer ID found.');
        }

        $amountInCents = (int) round($payable->getGatewayAmount() * 100);
        $currency = strtolower($payable->getCurrency());

        $chargeParams = array_merge([
            'amount' => $amountInCents,
            'currency' => $currency,
            'customer' => $customer->id,
            'payment_method' => $pmId,
            'off_session' => true,
            'confirm' => true,
            'description' => $payable->getDescription(),
            'metadata' => array_merge($payable->getMetadata(), [
                'user_id' => $this->getUserId(),
            ]),
        ], $options);

        try {
            $stripe = Foundry::stripe();
            $paymentIntent = $stripe->paymentIntents->create($chargeParams);

            if ($paymentIntent->status === 'requires_action') {
                $wrapper = new StripePaymentWrapper($paymentIntent->toArray());
                throw new PaymentIncomplete($wrapper);
            }

            return PaymentResult::success(
                paymentData: new StripePaymentMapper($paymentIntent),
                transactionId: $paymentIntent->id,
                status: $paymentIntent->status
            );
        } catch (CardException $e) {
            if ($e->getStripeCode() === 'authentication_required') {
                $paymentIntent = $e->getError()->payment_intent ?? null;
                if ($paymentIntent) {
                    $wrapper = new StripePaymentWrapper(is_array($paymentIntent) ? $paymentIntent : $paymentIntent->toArray());
                    throw new PaymentIncomplete($wrapper);
                }
            }

            logger()->error('Stripe charge failed', [
                'user_id' => $this->getUserId(),
                'error' => $e->getMessage(),
            ]);

            return PaymentResult::failed("Charge failed: {$e->getMessage()}");
        } catch (PaymentIncomplete $incompleteEx) {
            throw $incompleteEx;
        } catch (\Throwable $e) {
            logger()->error('Stripe charge error', [
                'user_id' => $this->getUserId(),
                'error' => $e->getMessage(),
            ]);

            return PaymentResult::failed("Charge failed: {$e->getMessage()}");
        }
    }

    /**
     * Create or retrieve the Stripe customer instance.
     *
     * @return Customer
     */
    protected function getOrCreateStripeCustomer()
    {
        $customer = $this->getOrCreateCustomer(
            $this->getUserId(),
            self::PROVIDER
        );

        if ($customer->provider_id) {
            return Foundry::stripe()->customers->retrieve($customer->provider_id);
        }

        $stripeCustomer = $this->createStripeCustomer();

        $customer->update([
            'provider_id' => $stripeCustomer->id,
        ]);

        return $stripeCustomer;
    }

    /**
     * Create a new Stripe customer.
     *
     * @return Customer
     */
    protected function createStripeCustomer()
    {
        $user = $this->user;

        $params = [
            'metadata' => [
                'user_id' => $this->getUserId(),
            ],
        ];

        if ($user) {
            $params['email'] = $user->email ?? null;
            $params['name'] = $user->name ?? null;

            if ($user->phone_number ?? false) {
                $params['phone'] = $user->phone_number;
            }

            if ($address = $user->address ?? null) {
                $params['address'] = [
                    'line1' => $address->line1,
                    'line2' => $address->line2 ?? '',
                    'city' => $address->city,
                    'state' => $address->state,
                    'postal_code' => $address->postal_code,
                    'country' => $address->country_code ?? '',
                ];
            }
        }

        return Foundry::stripe()->customers->create(array_filter($params));
    }

    /**
     * Add or update a payment method for the customer.
     */
    protected function addOrUpdatePaymentMethod(string $paymentMethodId): PaymentMethod
    {
        $stripeCustomer = $this->getOrCreateStripeCustomer();
        $stripe = Foundry::stripe();

        $paymentMethod = $stripe->paymentMethods->retrieve($paymentMethodId);

        if (! $paymentMethod) {
            throw new \Exception('Payment method not found.');
        }

        $paymentMethodId = $paymentMethod->id;

        if ($paymentMethod->customer !== $stripeCustomer->id) {
            $paymentMethod = $stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $stripeCustomer->id,
            ]);
        }

        $stripe->customers->update($stripeCustomer->id, [
            'invoice_settings' => [
                'default_payment_method' => $paymentMethodId,
            ],
        ]);

        $pm = $this->createOrUpdatePaymentMethod(
            $this->getUserId(),
            self::PROVIDER,
            $paymentMethodId,
            [
                'card_brand' => $paymentMethod->card->brand ?? '',
                'card_last_four' => $paymentMethod->card->last4 ?? '',
                'card_exp_month' => $paymentMethod->card->exp_month ?? '',
                'card_exp_year' => $paymentMethod->card->exp_year ?? '',
            ]
        );

        $pm->markAsDefault();

        return $pm;
    }
}
