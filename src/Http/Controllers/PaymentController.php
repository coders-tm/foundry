<?php

namespace Foundry\Http\Controllers;

use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\Payable;
use Foundry\Payment\Processor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaymentController extends Controller
{
    /**
     * Unified Setup Payment Intent for Orders
     * Handles payment setup for order payments using the factory pattern
     */
    public function setupPaymentIntent(Request $request)
    {
        $request->validate([
            'token' => 'required|string|exists:'.Foundry::$orderModel.',id',
            'provider' => 'required|integer|exists:'.PaymentMethod::class.',id',
        ]);

        try {
            $order = Foundry::$orderModel::where('id', $request->token)->firstOrFail();
            $paymentMethod = PaymentMethod::findOrFail($request->provider);

            // Check if order is already paid
            if ($order->payment_status === 'paid') {
                return response()->json([
                    'message' => 'This order has already been paid',
                    'order_number' => "#{$order->number}",
                ], 422);
            }

            $provider = $paymentMethod->integration_via ?? $paymentMethod->provider;

            // Check if provider is supported
            if (! Processor::isSupported($provider)) {
                return response()->json([
                    'message' => 'Payment method not supported',
                    'provider' => $provider,
                ], 422);
            }

            // Create processor using factory
            $processor = Processor::make($provider);

            // Set the payment method on the processor
            $processor->setPaymentMethod($paymentMethod);

            // Create Payable from order
            $payable = Payable::fromOrder($order);

            $paymentIntent = $processor->setupPaymentIntent($request, $payable);

            return response()->json($paymentIntent);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Unified Confirm Payment for Orders
     * Handles payment confirmation for order payments using the factory pattern
     */
    public function confirmPayment(Request $request)
    {
        $request->validate([
            'token' => 'required|string|exists:'.Foundry::$orderModel.',id',
            'provider' => 'required|integer|exists:'.PaymentMethod::class.',id',
        ]);

        try {
            /** @var Order $order */
            $order = Foundry::$orderModel::where('id', $request->token)->firstOrFail();
            $paymentMethod = PaymentMethod::findOrFail($request->provider);
            $provider = $paymentMethod->integration_via ?? $paymentMethod->provider;

            // Check if order is already paid
            if ($order->payment_status === 'paid') {
                return response()->json([
                    'success' => true,
                    'message' => 'This order has already been paid',
                    'order_number' => "#{$order->number}",
                    'order_id' => $order->id,
                ]);
            }

            // Check if provider is supported
            if (! Processor::isSupported($provider)) {
                return response()->json([
                    'message' => 'Payment method not supported',
                    'provider' => $provider,
                ], 422);
            }

            // Create processor using factory
            $processor = Processor::make($provider);

            // Set the payment method on the processor
            $processor->setPaymentMethod($paymentMethod);

            // Create Payable from order
            $payable = Payable::fromOrder($order);

            // Confirm payment and get payment result
            $result = $processor->confirmPayment($request, $payable);

            // Mark order as paid with payment data (if available)
            if ($paymentData = $result->getPaymentData()) {
                $order->markAsPaid($paymentData, ['amount' => $order->grand_total]);
            }

            return response()->json([
                'success' => true,
                'order_id' => $order->key,
                'transaction_id' => $result->getTransactionId(),
                'status' => $result->getStatus() ?? 'success',
            ]);
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
