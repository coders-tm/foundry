<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class ExchangeRateController extends Controller
{
    public function index()
    {
        return ExchangeRate::all();
    }

    public function store(Request $request)
    {
        // Find existing to handle unique validation for upsert by currency
        $existing = ExchangeRate::where('currency', $request->currency)->first();
        $id = $existing ? $existing->id : ($request->id ?? 'NULL');

        $request->validate([
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::unique('exchange_rates', 'currency')->ignore($id),
            ],
            'rate' => 'required|numeric',
        ]);

        $rate = ExchangeRate::updateOrCreate(
            ['currency' => $request->currency],
            ['rate' => $request->rate]
        );

        return response()->json([
            'data' => $rate,
            'message' => __('Exchange rate has been saved successfully!'),
        ], 200);
    }

    public function sync()
    {
        Artisan::call('foundry:update-exchange-rates');

        return response()->json([
            'data' => ExchangeRate::all(),
            'message' => __('Exchange rates has been synced successfully!'),
        ]);
    }

    public function estimate(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'country' => 'required|string|size:2',
        ]);

        $currency = ExchangeRate::getCurrencyFromCountryCode($request->country);
        $rate = ExchangeRate::where('currency', $currency)->first();

        if (! $rate) {
            return response()->json([
                'currency' => 'USD',
                'amount' => $request->amount,
                'formatted' => '$'.number_format($request->amount, 2),
            ]);
        }

        $exchangedAmount = $request->amount * $rate->rate;

        return response()->json([
            'currency' => $currency,
            'amount' => $exchangedAmount,
            'formatted' => $currency.' '.number_format($exchangedAmount, 2),
            'rate' => $rate->rate,
        ]);
    }

    public function destroy($id)
    {
        ExchangeRate::findOrFail($id)->delete();

        return response()->json([
            'message' => __('Exchange rate has been deleted successfully!'),
        ]);
    }
}
