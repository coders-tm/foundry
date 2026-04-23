<?php

namespace Foundry\Payment\Processors;

use Foundry\Contracts\PaymentProcessorInterface;
use Foundry\Foundry;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\Mappers\MercadoPagoPayment;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Illuminate\Http\Request;

class MercadoPagoProcessor extends AbstractPaymentProcessor implements PaymentProcessorInterface
{
    private const SUPPORTED_CURRENCIES = ['ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'UYU'];

    public function getProvider(): string
    {
        return PaymentMethod::MERCADOPAGO;
    }

    public function supportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public function setupPaymentIntent(Request $request, Payable $payable): array
    {
        $mercadopago = Foundry::mercadopago();

        // Ensure the payable supports the required currencies
        $payable->setCurrencies($this->supportedCurrencies());

        // Validate currency
        $this->validateCurrency($payable);

        $billingAddress = $payable->getBillingAddress();

        // Create preference for checkout
        $preference = $mercadopago->createPaymentIntent([
            'items' => [
                [
                    'title' => "Order #{$payable->getReferenceId()}",
                    'description' => "Payment for order {$payable->getReferenceId()}",
                    'quantity' => 1,
                    'unit_price' => $payable->getGatewayAmount(),
                    'currency_id' => $payable->getCurrency(),
                ],
            ],
            'payer' => [
                'email' => $payable->getCustomerEmail(),
                'name' => $payable->getCustomerFirstName(),
                'surname' => $payable->getCustomerLastName(),
                'phone' => [
                    'number' => $payable->getCustomerPhone() ?? '',
                ],
                'address' => [
                    'street_name' => $billingAddress['line1'] ?? '',
                    'street_number' => '',
                    'zip_code' => $billingAddress['postal_code'] ?? '',
                ],
            ],
            'back_urls' => [
                'success' => $this->getSuccessUrl(),
                'failure' => $this->getCancelUrl(),
                'pending' => $this->getSuccessUrl(),
            ],
            'auto_return' => 'approved',
            'external_reference' => $payable->getReferenceId(),
            'notification_url' => $this->getWebhookUrl(),
            'statement_descriptor' => config('app.name', 'Purchase'),
        ]);

        return [
            'preference_id' => $preference->id,
            'init_point' => $preference->init_point,
            'sandbox_init_point' => $preference->sandbox_init_point,
            'amount' => $payable->getGatewayAmount(),
            'currency' => $payable->getCurrency(),
        ];
    }

    public function confirmPayment(Request $request, Payable $payable): PaymentResult
    {
        $request->validate([
            'payment_id' => 'required|string',
        ]);

        try {
            $mercadopago = Foundry::mercadopago();

            // Get payment details from MercadoPago
            $payment = $mercadopago->confirmPayment($request->payment_id);

            // Check if payment is approved
            if ($payment->status !== 'approved') {
                PaymentResult::failed("Payment not approved. Status: {$payment->status}");
            }

            $paymentData = new MercadoPagoPayment($payment, $this->paymentMethod);

            return PaymentResult::success(
                paymentData: $paymentData,
                transactionId: (string) $payment->id,
                status: 'success'
            );
        } catch (\Throwable $e) {
            PaymentResult::failed($e->getMessage());
        }
    }
}
