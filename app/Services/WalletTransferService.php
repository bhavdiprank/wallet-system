<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Exceptions\WalletException;
use App\Models\{User, Wallet, WalletTransaction};

class WalletTransferService
{
    public function transfer(
        User $sender,
        string $email,
        int $amount,
        string $key
    ): array {

        if ($amount <= 0 ||
            $amount > config('wallet.max_transfer_paise')) {
            throw new WalletException('Invalid transfer amount');
        }

        $receiver = User::where('email', $email)->first();

        if (!$receiver) {
            throw new WalletException('Receiver not found', 404);
        }

        if ($sender->id === $receiver->id) {
            throw new WalletException(
                'You cannot transfer money to yourself'
            );
        }

        $requestHash = hash('sha256', json_encode([
            'receiver_id' => $receiver->id,
            'amount' => $amount,
        ]));

        try {
            return DB::transaction(function () use (
                $sender,
                $receiver,
                $amount,
                $key,
                $requestHash
            ) {

                // Reserve this idempotency key.
                $idempotency = DB::table('transfer_idempotencies')
                    ->insertGetId([
                        'user_id' => $sender->id,
                        'idempotency_key' => $key,
                        'request_hash' => $requestHash,
                        'response_json' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                // Lock both wallets in consistent order.
                $wallets = Wallet::whereIn('user_id', [
                    $sender->id,
                    $receiver->id,
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

                $senderWallet = $wallets->get($sender->id);
                $receiverWallet = $wallets->get($receiver->id);

                if (!$senderWallet || !$receiverWallet) {
                    throw new WalletException('Wallet not found', 404);
                }

                if ($senderWallet->balance < $amount) {
                    throw new WalletException(
                        'Insufficient wallet balance'
                    );
                }

                $referenceId = (string) Str::uuid();

                $senderWallet->balance -= $amount;
                $senderWallet->save();

                $receiverWallet->balance += $amount;
                $receiverWallet->save();

                WalletTransaction::create([
                    'wallet_id' => $senderWallet->id,
                    'counterparty_user_id' => $receiver->id,
                    'type' => 'transfer_out',
                    'amount' => $amount,
                    'balance_after' => $senderWallet->balance,
                    'reference_id' => $referenceId,
                ]);

                WalletTransaction::create([
                    'wallet_id' => $receiverWallet->id,
                    'counterparty_user_id' => $sender->id,
                    'type' => 'transfer_in',
                    'amount' => $amount,
                    'balance_after' => $receiverWallet->balance,
                    'reference_id' => $referenceId,
                ]);

                $result = [
                    'reference_id' => $referenceId,
                    'amount_paise' => $amount,
                    'sender_balance_paise' => $senderWallet->balance,
                    'receiver_email' => $receiver->email,
                    'status' => 'success',
                ];

                DB::table('transfer_idempotencies')
                    ->where('id', $idempotency)
                    ->update([
                        'response_json' => json_encode($result),
                        'updated_at' => now(),
                    ]);

                return $result;
            }, 3);

        } catch (QueryException $exception) {

            // A duplicate key means this request may already have completed.
            $existing = DB::table('transfer_idempotencies')
                ->where('user_id', $sender->id)
                ->where('idempotency_key', $key)
                ->first();

            if (!$existing) {
                throw $exception;
            }

            if (!hash_equals($existing->request_hash, $requestHash)) {
                throw new WalletException(
                    'Idempotency key already used with different data',
                    409
                );
            }

            if (!$existing->response_json) {
                throw new WalletException(
                    'Transfer is being processed. Please retry.',
                    409
                );
            }

            return json_decode($existing->response_json, true);
        }
    }
}
