<?php

use Illuminate\Support\Facades\Route;
use Modules\Fees\Http\Controllers\FeeTypeController;

Route::middleware('auth:sanctum')->prefix('fees')->group(function () {
    Route::get('/', [FeeTypeController::class, 'index']);
    Route::post('/', [FeeTypeController::class, 'store'])->middleware('permission:fees.manage');
    Route::put('/{feeType}', [FeeTypeController::class, 'update'])->middleware('permission:fees.manage');
    Route::delete('/{feeType}', [FeeTypeController::class, 'destroy'])->middleware('permission:fees.manage');
});
