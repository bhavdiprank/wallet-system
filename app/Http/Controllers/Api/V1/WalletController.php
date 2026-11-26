<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

use App\Http\Controllers\Controller;
use App\Models\{User, Wallet, WalletTransaction};

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $wallet = $request->user()->wallet;

        return response()->json([
            'status' => true,
            'balance_paise' => $wallet->balance,
            'balance_rupees' => number_format(
                $wallet->balance / 100,
                2,
                '.',
                ''
            ),
        ]);
    }

    public function deposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount_paise' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $transaction = DB::transaction(function () use ($request) {

            $wallet = $request->user()
                ->wallet()
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (int) $request->amount_paise;

            $wallet->increment('balance', $amount);

            return $wallet->transactions()->create([
                'type' => 'deposit',
                'amount' => $amount,
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Money deposited successfully',
            'transaction' => $transaction,
            'balance_paise' => $request->user()->wallet->fresh()->balance,
        ], 201);
    }

    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'amount_paise' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $sender = $request->user();
        $receiver = User::where('email', $request->email)->firstOrFail();

        if ($sender->id === $receiver->id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot transfer money to yourself'
            ], 422);
        }

        $amount = (int) $request->amount_paise;

        $result = DB::transaction(function () use (
            $sender,
            $receiver,
            $amount
        ) {

            // Lock wallets in consistent ID order
            $wallets = Wallet::whereIn('user_id', [
                $sender->id,
                $receiver->id
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('user_id');

            $senderWallet = $wallets->get($sender->id);
            $receiverWallet = $wallets->get($receiver->id);

            if (!$senderWallet || !$receiverWallet) {
                throw ValidationException::withMessages([
                    'wallet' => ['Wallet not found.']
                ]);
            }

            if ($senderWallet->balance < $amount) {
                throw ValidationException::withMessages([
                    'amount_paise' => ['Insufficient wallet balance.']
                ]);
            }

            $senderWallet->decrement('balance', $amount);
            $receiverWallet->increment('balance', $amount);

            $senderWallet->transactions()->create([
                'type' => 'transfer_out',
                'amount' => $amount,
                'counterparty_user_id' => $receiver->id,
            ]);

            $receiverWallet->transactions()->create([
                'type' => 'transfer_in',
                'amount' => $amount,
                'counterparty_user_id' => $sender->id,
            ]);

            return $senderWallet->fresh()->balance;
        });

        return response()->json([
            'status' => true,
            'message' => 'Money transferred successfully',
            'amount_paise' => $amount,
            'balance_paise' => $result,
        ]);
    }

    public function transactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:deposit,transfer_in,transfer_out',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = $request->user()
            ->wallet
            ->transactions()
            ->with('counterparty:id,name,email')
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $transactions = $query->paginate(
            $request->input('per_page', 10)
        );

        return response()->json([
            'status' => true,
            'transactions' => $transactions,
        ]);
    }
}
