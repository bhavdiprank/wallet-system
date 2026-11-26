<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'balance_paise' => $this->balance,
            'balance_rupees' => number_format(
                $this->balance / 100,
                2,
                '.',
                ''
            ),
        ];
    }
}