<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount_paise' => $this->amount,
            'balance_after_paise' => $this->balance_after,
            'reference_id' => $this->reference_id,
            'counterparty' => $this->counterparty
                ? [
                    'name' => $this->counterparty->name,
                    'email' => $this->counterparty->email,
                ]
                : null,
            'created_at' => $this->created_at,
        ];
    }
}