<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\{AuthController, WalletController};

Route::prefix('v1')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/wallet', [WalletController::class, 'index']);

        Route::post('/wallet/deposit', [WalletController::class, 'deposit']);

        Route::post('/wallet/transfer', [WalletController::class, 'transfer'])
            ->middleware('throttle:wallet-transfer');

        Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
    });
});

