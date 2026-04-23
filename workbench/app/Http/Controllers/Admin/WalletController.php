<?php

namespace Workbench\App\Http\Controllers\Admin;

use Foundry\Foundry;
use Foundry\Http\Resources\WalletTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Workbench\App\Http\Controllers\Controller;

class WalletController extends Controller
{
    /**
     * Get user's wallet balance.
     *
     * @param  int  $user
     * @return JsonResponse
     */
    public function balance(Request $request, $user)
    {
        $userModel = Foundry::$userModel;
        $user = $userModel::findOrFail($user);

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
     * @param  int  $user
     * @return AnonymousResourceCollection
     */
    public function transactions(Request $request, $user)
    {
        $userModel = Foundry::$userModel;
        $user = $userModel::findOrFail($user);

        $transactions = $user->walletTransactions()
            ->with('transactionable')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return WalletTransactionResource::collection($transactions);
    }

    /**
     * Credit user's wallet.
     *
     * @param  int  $user
     * @return JsonResponse
     */
    public function credit(Request $request, $user)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $userModel = Foundry::$userModel;
        $user = $userModel::findOrFail($user);

        $wallet = $user->getOrCreateWallet();
        $transaction = $wallet->credit(
            $validated['amount'],
            $validated['description'] ?? 'Manual credit by admin'
        );

        return response()->json([
            'message' => 'Wallet credited successfully',
            'transaction' => new WalletTransactionResource($transaction),
            'balance' => (float) $wallet->fresh()->balance,
        ]);
    }

    /**
     * Debit user's wallet.
     *
     * @param  int  $user
     * @return JsonResponse
     */
    public function debit(Request $request, $user)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $userModel = Foundry::$userModel;
        $user = $userModel::findOrFail($user);

        $wallet = $user->getOrCreateWallet();

        // Check if wallet has sufficient balance
        if ($wallet->balance < $validated['amount']) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient wallet balance.'],
            ]);
        }

        $transaction = $wallet->debit(
            $validated['amount'],
            $validated['description'] ?? 'Manual debit by admin'
        );

        return response()->json([
            'message' => 'Wallet debited successfully',
            'transaction' => new WalletTransactionResource($transaction),
            'balance' => (float) $wallet->fresh()->balance,
        ]);
    }
}
