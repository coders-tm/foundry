<?php

namespace Foundry\Payment\Processors;

use Foundry\Contracts\PaymentProcessorInterface;
use Foundry\Foundry;
use Foundry\Models\ExchangeRate;
use Foundry\Models\Payment;
use Foundry\Payment\CallbackResult;
use Foundry\Payment\Mappers\PayuPayment;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Services\PaymentProvider;
use Illuminate\Http\Request;

class PayuProcessor extends AbstractPaymentProcessor implements PaymentProcessorInterface
{
    private const SUPPORTED_CURRENCIES = [
        'AED',
        'AFN',
        'ALL',
        'AMD',
        'ANG',
        'AOA',
        'ARS',
        'AUD',
        'AWG',
        'AZN',
        'BAM',
        'BBD',
        'BGN',
        'BHD',
        'BIF',
        'BMD',
        'BND',
        'BOB',
        'BRL',
        'BSD',
        'BWP',
        'BYN',
        'BZD',
        'CAD',
        'CDF',
        'CHF',
        'CLP',
        'CNY',
        'COP',
        'CRC',
        'CVE',
        'CZK',
        'DJF',
        'DKK',
        'DOP',
        'DZD',
        'EGP',
        'ETB',
        'EUR',
        'FJD',
        'FKP',
        'GBP',
        'GEL',
        'GHS',
        'GIP',
        'GMD',
        'GNF',
        'GTQ',
        'GYD',
        'HKD',
        'HNL',
        'HRK',
        'HTG',
        'HUF',
        'IDR',
        'ILS',
        'IQD',
        'ISK',
        'JMD',
        'JOD',
        'JPY',
        'KGS',
        'KHR',
        'KMF',
        'KRW',
        'KWD',
        'KYD',
        'KZT',
        'LAK',
        'LBP',
        'LRD',
        'LSL',
        'MAD',
        'MDL',
        'MGA',
        'MKD',
        'MNT',
        'MOP',
        'MRU',
        'MVR',
        'MWK',
        'MXN',
        'MYR',
        'MZN',
        'NAD',
        'NGN',
        'NIO',
        'NOK',
        'NZD',
        'OMR',
        'PAB',
        'PEN',
        'PGK',
        'PLN',
        'PYG',
        'QAR',
        'RON',
        'RSD',
        'RWF',
        'SAR',
        'SBD',
        'SCR',
        'SEK',
        'SGD',
        'SHP',
        'SOS',
        'SRD',
        'SVC',
        'SZL',
        'THB',
        'TJS',
        'TMT',
        'TND',
        'TOP',
        'TRY',
        'TTD',
        'TWD',
        'TZS',
        'UGX',
        'USD',
        'UYU',
        'UZS',
        'VES',
        'VND',
        'VUV',
        'WST',
        'XAF',
        'XCD',
        'XOF',
        'XPF',
        'YER',
        'ZAR',
        'ZMW',
        'ZWL',
        'INR',
    ];

    public function getProvider(): string
    {
        return PaymentProvider::PAYU;
    }

    public function supportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public function setupPaymentIntent(Request $request, Payable $payable): array
    {
        $payu = Foundry::payu();

        $gatewayCurrency = $payable->getCurrency();
        $amount = $payable->getGatewayAmount();
        $baseCurrency = ExchangeRate::getBaseCurrency();

        // Convert to INR if the transaction currency is not supported
        if (! in_array(strtoupper($gatewayCurrency), $this->supportedCurrencies())) {
            $gatewayCurrency = 'INR';
            try {
                $amount = ExchangeRate::convertAmount($payable->getGrandTotal(), $baseCurrency, 'INR');
            } catch (\RuntimeException $e) {
                // Fallback to grand total on failure
                $amount = $payable->getGrandTotal();
            }
        }

        // Create a pending payment record to track the transaction
        $payment = Payment::create([
            'paymentable_type' => $payable->isOrder() ? Foundry::$orderModel : get_class($payable->getSource()),
            'paymentable_id' => $payable->getSourceId(),
            'provider' => $this->getProvider(),
            'transaction_id' => 'pending_'.uniqid(),
            'amount' => $payable->getGrandTotal(),
            'status' => Payment::STATUS_PENDING,
            'note' => 'PayU payment initiated',
            'metadata' => array_merge($payable->getMetadata(), [
                'gateway_amount' => number_format($amount, 2, '.', ''),
                'gateway_currency' => $gatewayCurrency,
                'created_at' => now()->toISOString(),
            ]),
        ]);

        $txnid = 'TXN'.substr(hash('sha256', $payment->uuid.time()), 0, 12);

        // Map required fields for PayU checkout post parameters
        $source = $payable->getSource();
        $user = $source && method_exists($source, 'getUser') ? $source->getUser() : ($request->user() ?? null);
        $firstname = $user ? ($user->first_name ?? $user->name ?? 'Guest') : 'Guest';
        $email = $user ? ($user->email ?? 'guest@example.com') : 'guest@example.com';
        $phone = $user ? ($user->phone ?? '') : '';

        $params = [
            'txnid' => $txnid,
            'amount' => number_format($amount, 2, '.', ''),
            'productinfo' => substr($payable->getDescription() ?? 'Subscription Payment', 0, 80),
            'firstname' => $firstname,
            'email' => $email,
            'phone' => $phone,
            'surl' => $this->getSuccessUrl(['state' => $payment->uuid]),
            'furl' => $this->getCancelUrl(['state' => $payment->uuid]),
            'udf1' => $payment->uuid,
            'currency' => $gatewayCurrency,
        ];

        $intent = $payu->createPaymentIntent($params);

        return array_merge($intent, [
            'payment_intent_id' => $txnid,
            'state_id' => $payment->uuid,
        ]);
    }

    public function confirmPayment(Request $request, Payable $payable): PaymentResult
    {
        try {
            $payu = Foundry::payu();

            $receivedHash = $request->input('hash');
            $calculatedHash = $payu->calculateResponseHash($request->all());

            if (empty($receivedHash) || ! hash_equals($calculatedHash, $receivedHash)) {
                return PaymentResult::failed('Invalid PayU transaction signature/hash.');
            }

            $response = $request->all();
            $paymentData = new PayuPayment($response);

            return PaymentResult::success(
                paymentData: $paymentData,
                transactionId: $response['mihpayid'] ?? $response['txnid'] ?? null,
                status: 'success'
            );
        } catch (\Throwable $e) {
            return PaymentResult::failed($e->getMessage());
        }
    }

    public function handleSuccessCallback(Request $request): CallbackResult
    {
        try {
            $stateId = $request->input('state') ?? $request->query('state') ?? $request->input('udf1');

            if (! $stateId) {
                return CallbackResult::failed('Invalid payment session.');
            }

            $payment = Payment::where('uuid', $stateId)->first();

            if (! $payment) {
                return CallbackResult::failed('Payment record not found.');
            }

            $payu = Foundry::payu();
            $receivedHash = $request->input('hash');
            $calculatedHash = $payu->calculateResponseHash($request->all());

            if (empty($receivedHash) || ! hash_equals($calculatedHash, $receivedHash)) {
                return CallbackResult::failed('Payment verification failed due to signature mismatch.');
            }

            if ($request->input('status') !== 'success') {
                return CallbackResult::failed('Payment was unsuccessful: '.($request->input('error_Message') ?? 'Failed'));
            }

            $paymentData = new PayuPayment($request->all());
            $payment->update($paymentData->toArray());

            return CallbackResult::success(
                message: 'PayU payment was successful.',
                payment: $payment->fresh()
            );
        } catch (\Throwable $e) {
            return CallbackResult::failed('Payment verification failed: '.$e->getMessage());
        }
    }

    public function handleCancelCallback(Request $request): CallbackResult
    {
        $payment = null;
        try {
            $stateId = $request->input('state') ?? $request->query('state') ?? $request->input('udf1');

            if ($stateId) {
                $payment = Payment::where('uuid', $stateId)->first();
                if ($payment) {
                    $errorMessage = $request->input('error_Message') ?? $request->input('unmappedstatus') ?? 'Payment cancelled by user';
                    $payment->markAsFailed($errorMessage);
                }
            }
        } catch (\Throwable $e) {
            // Log exception
        }

        return CallbackResult::success(
            message: 'PayU payment was cancelled.',
            payment: $payment
        );
    }
}
