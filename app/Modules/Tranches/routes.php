<?php

use Illuminate\Support\Facades\Route;
use Modules\Tranches\Http\Controllers\TrancheController;

Route::middleware('auth:sanctum')->prefix('tranches')->group(function () {
    Route::get('/', [TrancheController::class, 'index'])->middleware('permission:tranches.view');
    Route::post('/', [TrancheController::class, 'store'])->middleware('permission:tranches.manage');
    Route::put('/{tranche}', [TrancheController::class, 'update'])->middleware('permission:tranches.manage');
    Route::delete('/{tranche}', [TrancheController::class, 'destroy'])->middleware('permission:tranches.manage');
});
