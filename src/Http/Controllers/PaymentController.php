<?php

namespace Foundry\Http\Controllers;

use Foundry\Enum\PaymentStatus;
use Foundry\Models\Order;
use Foundry\Payment\Payable;
use Foundry\Payment\Processor;
use \Foundry\Services\PaymentProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Display the payment page.
     */
    public function index(Request $request, Order $order): JsonResponse
    {
        if ($order->payment_status === PaymentStatus::PAID) {
            return response()->json([
                'order' => $order->loadMissing('payments'),
            ]);
        }

        $order->load(['line_items', 'tax_lines', 'discount', 'contact', 'customer']);

        $paymentMethods = PaymentProvider::toPublic();

        return response()->json([
            'order' => $order,
            'token' => $order->id,
            'paymentMethods' => $paymentMethods,
            'customerData' => $order->customer?->load('address') ?? $order->contact,
        ]);
    }

    /**
     * Download invoice PDF for the specified order.
     */
    public function downloadInvoice(Order $order)
    {
        return $order->download();
    }

    /**
     * Setup payment intent for the order.
     */
    public function setupPaymentIntent(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|exists:orders,id',
            'provider' => 'required|string',
            'line1' => 'nullable|string',
            'line2' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'country' => 'nullable|string',
        ]);

        try {
            $order = Order::findOrFail($request->token);

            if (! PaymentProvider::has($request->provider)) {
                return response()->json([
                    'message' => __('Payment provider not found or disabled'),
                    'provider' => $request->provider,
                ], 422);
            }

            // Update billing address if provided
            $address = $request->only(['line1', 'line2', 'city', 'state', 'postal_code', 'country']);
            if (! empty(array_filter($address))) {
                $order->fill(['billing_address' => $address])->saveQuietly();
                if ($user = $request->user()) {
                    $user->updateOrCreateAddress($address);
                }
            }

            if ($order->payment_status === 'paid') {
                return response()->json([
                    'message' => __('This order has already been paid'),
                    'order_number' => "#{$order->number}",
                ], 422);
            }

            $provider = $request->provider;

            if (! Processor::isSupported($provider)) {
                return response()->json([
                    'message' => __('Payment method not supported'),
                    'provider' => $provider,
                ], 422);
            }

            $processor = Processor::make($provider);
            $payable = Payable::fromOrder($order);

            $paymentIntent = $processor->setupPaymentIntent($request, $payable);

            return response()->json($paymentIntent);
        } catch (\Throwable $e) {
            Log::error('Payment setup error: '.$e->getMessage(), [
                'token' => $request->token,
                'provider' => $request->provider,
            ]);
            throw $e;
        }
    }

    /**
     * Confirm payment for the order.
     */
    public function confirmPayment(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|exists:orders,id',
            'provider' => 'required|string',
        ]);

        try {
            $order = Order::findOrFail($request->token);

            if (! PaymentProvider::has($request->provider)) {
                return response()->json([
                    'message' => __('Payment provider not found or disabled'),
                    'provider' => $request->provider,
                ], 422);
            }

            $provider = $request->provider;

            if ($order->payment_status === 'paid') {
                return response()->json([
                    'success' => true,
                    'message' => __('This order has already been paid'),
                    'order_number' => "#{$order->number}",
                    'order_id' => $order->id,
                ]);
            }

            if (! Processor::isSupported($provider)) {
                return response()->json([
                    'message' => __('Payment method not supported'),
                    'provider' => $provider,
                ], 422);
            }

            $processor = Processor::make($provider);
            $payable = Payable::fromOrder($order);

            $result = $processor->confirmPayment($request, $payable);

            if ($paymentData = $result->getPaymentData()) {
                $order->markAsPaid($paymentData, ['amount' => $order->grand_total]);
            }

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'transaction_id' => $result->getTransactionId(),
                'status' => $result->getStatus() ?? 'success',
            ]);
        } catch (\Throwable $e) {
            Log::error('Payment confirmation error: '.$e->getMessage(), [
                'token' => $request->token,
                'provider' => $request->provider,
            ]);
            throw $e;
        }
    }

    /**
     * Handle success callback from payment providers.
     */
    public function handleSuccess(Request $request, string $provider)
    {
        $redirectUrl = route('dashboard');

        try {
            $result = Processor::handleSuccessCallback($provider, $request);
            $payment = $result->payment;

            if ($order = $payment->paymentable) {
                $redirectUrl = route('payment.index', $order->id);

                if ($order->payment_status !== 'paid') {
                    $order->markAsPaid();
                }
            }

            return redirect($redirectUrl)->with($result->getMessageType(), $result->getMessage());
        } catch (\Throwable $e) {
            Log::error("Order payment success handler error for provider {$provider}: ".$e->getMessage());

            return redirect($redirectUrl)->with('info', __('Payment may have been completed. Please check your order status.'));
        }
    }

    /**
     * Handle cancel callback from payment providers.
     */
    public function handleCancel(Request $request, string $provider)
    {
        $redirectUrl = route('dashboard');

        try {
            $result = Processor::handleCancelCallback($provider, $request);
            $payment = $result->payment;

            if ($order = $payment->paymentable) {
                $redirectUrl = route('payment.index', $order->id);
            }

            return redirect($redirectUrl)->with($result->getMessageType(), $result->getMessage());
        } catch (\Throwable $e) {
            Log::error("Order payment cancel handler error for provider {$provider}: ".$e->getMessage());

            return redirect($redirectUrl)->with('info', __('Payment process was interrupted.'));
        }
    }
}
