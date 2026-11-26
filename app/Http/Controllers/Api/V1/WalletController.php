<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferRequest;
use App\Http\Resources\{TransactionResource, WalletResource};
use App\Services\WalletTransferService;
use App\Exceptions\WalletException;
use App\Models\WalletTransaction;

class WalletController extends Controller
{
    public function __construct(
        private WalletTransferService $transferService
    ) {}

    public function index(Request $request)
    {
        return new WalletResource(
            $request->user()->wallet
        );
    }

    public function deposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount_paise' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction = DB::transaction(function () use ($request) {

            $wallet = $request->user()
                ->wallet()
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (int) $request->amount_paise;

            $wallet->balance += $amount;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'deposit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference_id' => (string) Str::uuid(),
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Deposit successful',
            'data' => new TransactionResource($transaction),
        ], 201);
    }

    public function transfer(TransferRequest $request)
    {
        $key = $request->header('Idempotency-Key');

        if (!$key || strlen($key) > 255) {
            return response()->json([
                'status' => false,
                'message' => 'Valid Idempotency-Key header is required',
            ], 400);
        }

        $result = $this->transferService->transfer(
            $request->user(),
            $request->email,
            (int) $request->amount_paise,
            $key
        );

        return response()->json([
            'status' => true,
            'message' => 'Transfer successful',
            'data' => $result,
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
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = $request->user()->wallet
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

        return TransactionResource::collection(
            $query->paginate($request->integer('per_page', 10))
        );
    }
}
