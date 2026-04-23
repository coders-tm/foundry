<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Foundry;
use Foundry\Mail\TestEmail;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ApplicationController extends Controller
{
    public function stats(Request $request)
    {
        // Get max year in a database-agnostic way
        $maxYear = Foundry::$subscriptionModel::query()
            ->selectRaw(
                DB::getDriverName() === 'sqlite'
                    ? "strftime('%Y', created_at) as year"
                    : 'YEAR(created_at) as year'
            )
            ->orderBy('created_at', 'desc')
            ->value('year');

        return response()->json([
            'total' => Foundry::$subscriptionModel::query()->count(),
            'rolling' => Foundry::$subscriptionModel::query()->active()->count(),
            'end_date' => Foundry::$subscriptionModel::query()->ended()->count(),
            'free' => Foundry::$subscriptionModel::query()->active()->free()->count(),
            'max_year' => $maxYear ?? date('Y'),
            'min_year' => 2000,
            'unread_support' => Foundry::$supportTicketModel::onlyActive()->count(),
        ], 200);
    }

    public function getSettings($key)
    {
        return response()->json(settings($key), 200);
    }

    public function config(Request $request)
    {
        $response = [];

        $config = array_merge(settings('config'), [
            'domain' => config('foundry.domain'),
            'app_url' => config('app.url'),
            'currency' => config('app.currency'),
            'currency_symbol' => currency_symbol(),
        ]);

        if ($request->filled('includes')) {
            foreach ($request->includes ?? [] as $item) {
                if ($item === 'payment-methods') {
                    $response[$item] = PaymentMethod::toPublic();
                } else {
                    $response[$item] = settings($item);
                }
            }

            $response['config'] = $config;

            return response()->json($response, 200);
        }

        return response()->json($config, 200);
    }

    public function paymentMethods()
    {
        return response()->json(PaymentMethod::toPublic(), 200);
    }

    public function updateSettings(Request $request)
    {
        $rules = [
            'key' => 'required',
            'options' => 'array',
        ];

        $this->validate($request, $rules);

        $options = Setting::updateValue($request->key, $request->options ?? [], true);

        // Clear the cache for the specific key
        $cacheKey = "app_config_{$request->key}";
        Cache::forget($cacheKey);

        return response()->json([
            'data' => $options,
            'message' => __('App settings has been updated successfully!'),
        ], 200);
    }

    public function testMailConfig(Request $request)
    {
        $rules = [
            'to' => 'required|email',
        ];

        $this->validate($request, $rules);

        try {
            foreach ($request->input() as $key => $value) {
                Config::set("mail.$key", $value);
            }
            Mail::to($request->to)->send(new TestEmail);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => __('Test email sent successfully!'),
        ], 200);
    }
}
