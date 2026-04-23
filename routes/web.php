<?php

use Foundry\Http\Controllers as Foundry;
use Foundry\Http\Controllers\Payment;
use Foundry\Http\Controllers\Webhook;
use Illuminate\Support\Facades\Route;

// Payment callbacks
Route::group(['prefix' => 'payment', 'as' => 'payment.'], function () {
    Route::get('gocardless/success', [Payment\GoCardlessController::class, 'success'])->name('gocardless.success');
    Route::get('{provider}/success', [Foundry\PaymentController::class, 'handleSuccess'])->name('success');
    Route::get('{provider}/cancel', [Foundry\PaymentController::class, 'handleCancel'])->name('cancel');
});

// Webhooks callbacks
Route::post('stripe/webhook', [Webhook\StripeController::class, 'handleWebhook'])->name('stripe.webhook');
Route::post('paypal/webhook', [Webhook\PaypalController::class, 'handleWebhook'])->name('paypal.webhook');
Route::post('razorpay/webhook', [Webhook\RazorpayController::class, 'handleWebhook'])->name('razorpay.webhook');
Route::post('gocardless/webhook', [Webhook\GoCardlessController::class, 'handleWebhook'])->name('gocardless.webhook');
