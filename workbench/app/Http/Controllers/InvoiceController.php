<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Models\User;
use Foundry\Traits\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InvoiceController extends Controller
{
    use Helpers;

    public function invoices(Request $request)
    {
        $query = $this->user($request)->invoices();

        if ($request->filled('status')) {
            $query->whereStatus($request->status);
        }

        $invoices = $query->orderBy($request->sortBy ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->rowsPerPage ?: 15);

        return new ResourceCollection($invoices);
    }

    /**
     * Download invoice PDF.
     */
    public function downloadInvoice(Request $request, $invoice)
    {
        /** @var Order $invoice */
        $invoice = Foundry::$orderModel::findOrFail($invoice);

        if (is_user() && $invoice->customer_id !== user('id')) {
            abort(403, __('You are not authorized to access this invoice.'));
        }

        return $invoice->load('line_items')->download();
    }

    /**
     * Get the requesting user.
     *
     * @return User
     */
    protected function user(Request $request)
    {
        if (is_admin()) {
            return Foundry::$userModel::findOrFail($request->user);
        }

        return user();
    }
}
