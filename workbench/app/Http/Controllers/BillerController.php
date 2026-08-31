<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Mandate\Http\Controllers\BillerController as BaseBillerController;
use Foundry\Services\PaymentProvider;

class BillerController extends BaseBillerController
{
    /**
     * Display payment methods management page for workbench testing.
     */
    public function index()
    {
        $user = auth()->user();
        $paymentMethods = $user ? $user->paymentMethods() : collect();
        $providers = PaymentProvider::toPublicMandateable();

        return view('payment-methods.index', compact('paymentMethods', 'providers'));
    }
}
