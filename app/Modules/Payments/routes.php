<?php

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\PaymentController;

Route::middleware('auth:sanctum')->prefix('payments')->group(function () {
    Route::get('/', [PaymentController::class, 'index'])->middleware('permission:payments.view');
    Route::get('/daily-summary', [PaymentController::class, 'dailySummary'])->middleware('permission:payments.view');
    Route::post('/', [PaymentController::class, 'store'])->middleware('permission:payments.create');
    Route::get('/{payment}', [PaymentController::class, 'show'])->middleware('permission:payments.view');
    Route::delete('/{payment}', [PaymentController::class, 'destroy'])->middleware('permission:payments.delete');
});
