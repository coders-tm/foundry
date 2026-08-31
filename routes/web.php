<?php

use Foundry\Http\Controllers\Webhook;
use Illuminate\Support\Facades\Route;

// Webhooks callbacks
Route::post('stripe/webhook', [Webhook\StripeController::class, 'handleWebhook'])->name('stripe.webhook');
Route::post('paypal/webhook', [Webhook\PaypalController::class, 'handleWebhook'])->name('paypal.webhook');
Route::post('razorpay/webhook', [Webhook\RazorpayController::class, 'handleWebhook'])->name('razorpay.webhook');
Route::post('gocardless/webhook', [Webhook\GoCardlessController::class, 'handleWebhook'])->name('gocardless.webhook');
Route::post('paddle/webhook', [Webhook\PaddleController::class, 'handleWebhook'])->name('paddle.webhook');
