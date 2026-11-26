<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'counterparty_user_id',
        'type',
        'amount',
        'balance_after',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException(
                'Wallet transactions cannot be updated.'
            );
        });

        static::deleting(function () {
            throw new \LogicException(
                'Wallet transactions cannot be deleted.'
            );
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterparty_user_id');
    }
}

