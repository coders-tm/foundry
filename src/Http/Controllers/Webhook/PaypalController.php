<?php

namespace Foundry\Http\Controllers\Webhook;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaypalController extends Controller
{
    public function handleWebhook(Request $request)
    {
        return response()->json([], 200);
    }
}
