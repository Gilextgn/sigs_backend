<?php

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\CashController;
use Modules\Payments\Http\Controllers\PaymentController;

Route::middleware('auth:sanctum')->prefix('payments')->group(function () {
    Route::get('/', [PaymentController::class, 'index'])->middleware('permission:payments.view');
    Route::get('/daily-summary', [PaymentController::class, 'dailySummary'])->middleware('permission:payments.view');
    Route::post('/', [PaymentController::class, 'store'])->middleware('permission:payments.create');
    Route::get('/{payment}', [PaymentController::class, 'show'])->middleware('permission:payments.view');
    Route::post('/{payment}/send-receipt', [PaymentController::class, 'sendReceipt'])->middleware('permission:payments.create');
    Route::delete('/{payment}', [PaymentController::class, 'destroy'])->middleware('permission:payments.delete');
});

Route::middleware('auth:sanctum')->prefix('cash')->group(function () {
    Route::get('/report', [CashController::class, 'report'])->middleware('permission:payments.view');
    Route::post('/closings', [CashController::class, 'close'])->middleware('permission:cash.close');
    Route::post('/closings/{closing}/reopen', [CashController::class, 'reopen'])->middleware('permission:cash.reopen');
});

// Public : QR code imprimé sur le reçu. Limité pour empêcher de deviner des jetons.
Route::get('/receipts/verify/{token}', [PaymentController::class, 'verify'])->middleware('throttle:30,1');
