<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\Payable;
use Foundry\Payment\Processor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Get payment status for an order
     * Returns comprehensive order details for payment page
     *
     * @return JsonResponse
     */
    public function status(string $token)
    {
        $order = Foundry::$orderModel::where('id', $token)
            ->with(['line_items', 'tax_lines', 'discount', 'contact', 'customer'])
            ->firstOrFail();

        // Transform order data with currency conversion
        /** @var Order $order */
        $orderData = $order->toArray();

        // Get supported payment methods for the currency
        $paymentMethods = PaymentMethod::enabled()
            ->orderBy('order')
            ->get()
            ->pluck('provider')
            ->values();

        return response()->json(array_merge($orderData, [
            'payment_methods' => $paymentMethods,
        ]));
    }

    /**
     * Unified Setup Payment Intent for Orders
     * Handles payment setup for order payments using the factory pattern
     */
    public function setupPaymentIntent(Request $request)
    {
        $request->validate([
            'token' => 'required|string|exists:'.Foundry::$orderModel.',id',
            'provider' => 'required|string|exists:'.PaymentMethod::class.',id',
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
            'provider' => 'required|string|exists:'.PaymentMethod::class.',id',
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

    /**
     * Handle order payment success redirect from payment providers
     * Uses Processor for unified callback handling
     */
    public function handleSuccess(Request $request, string $provider)
    {
        $redirectUrl = '/billing';

        try {
            // Use the factory to handle the success callback
            $result = Processor::handleSuccessCallback($provider, $request);

            // Update redirect URL if payment information is available
            /** @var Payment $payment */
            $payment = $result->payment;
            if ($order = $payment->paymentable) {
                /** @var Order $paymentable */
                if (isset($order->id)) {
                    $redirectUrl = "/payment/{$order->id}";
                }

                // Check if order is already paid
                if ($order->payment_status === 'paid') {
                    return redirect($redirectUrl)
                        ->with('info', 'This order has already been paid');
                } else {
                    $order->markAsPaid();
                }
            }

            return redirect($redirectUrl)
                ->with($result->getMessageType(), $result->getMessage());
        } catch (\Throwable $e) {
            // Log the error but don't show it to user
            Log::error("Order payment success handler error for provider {$provider}: ".$e->getMessage(), [
                'request' => $request->all(),
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            // Fallback to orders redirect
            return redirect($redirectUrl)
                ->with('info', 'Payment may have been completed. Please check your order status or contact support if needed.');
        }
    }

    /**
     * Handle order payment cancellation from payment providers
     * Uses Processor for unified callback handling
     */
    public function handleCancel(Request $request, string $provider)
    {
        $redirectUrl = '/orders';

        try {
            // Use the factory to handle the cancel callback
            $result = Processor::handleCancelCallback($provider, $request);

            // Update redirect URL if payment information is available
            /** @var Payment $payment */
            $payment = $result->payment;
            if ($paymentable = $payment->paymentable) {
                /** @var Order $paymentable */
                if (isset($paymentable->id)) {
                    $redirectUrl = "/payment/{$paymentable->id}";
                }
            }

            return redirect($redirectUrl)
                ->with($result->getMessageType(), $result->getMessage());
        } catch (\Throwable $e) {
            // Log the error but don't show it to user
            Log::error("Order payment cancel handler error for provider {$provider}: ".$e->getMessage(), [
                'request' => $request->all(),
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            // Fallback to payment page or orders
            return redirect($redirectUrl)
                ->with('info', 'Payment process was interrupted. Please try again.');
        }
    }
}
