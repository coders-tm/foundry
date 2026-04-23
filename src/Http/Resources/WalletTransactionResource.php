<?php

namespace Foundry\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'source' => $this->source,
            'amount' => (float) $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'balance_before' => (float) $this->balance_before,
            'balance_after' => (float) $this->balance_after,
            'currency' => strtoupper(config('app.currency', 'USD')),
            'description' => $this->description,
            'metadata' => $this->metadata,
            'transactionable_type' => $this->transactionable_type,
            'transactionable_id' => $this->transactionable_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
