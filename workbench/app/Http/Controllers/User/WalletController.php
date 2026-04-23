<?php

namespace Workbench\App\Http\Controllers\User;

use Foundry\Http\Resources\WalletTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Workbench\App\Http\Controllers\Controller;

class WalletController extends Controller
{
    /**
     * Get user's wallet balance.
     *
     * @return JsonResponse
     */
    public function balance(Request $request)
    {
        $user = $request->user();
        $wallet = $user->getOrCreateWallet();

        return response()->json([
            'balance' => (float) $wallet->balance,
            'formatted_balance' => format_amount($wallet->balance, config('app.currency', 'USD')),
            'currency' => strtoupper(config('app.currency', 'USD')),
        ]);
    }

    /**
     * Get user's wallet transaction history.
     *
     * @return AnonymousResourceCollection
     */
    public function transactions(Request $request)
    {
        $user = $request->user();

        $transactions = $user->walletTransactions()
            ->with('transactionable')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return WalletTransactionResource::collection($transactions);
    }
}
