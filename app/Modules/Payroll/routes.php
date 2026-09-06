<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\PayrollController;

Route::middleware(['auth:sanctum', 'permission:teachers.manage'])->prefix('payroll')->group(function () {
    Route::get('/', [PayrollController::class, 'index']);
    Route::post('/', [PayrollController::class, 'store']);
    Route::get('/estimate', [PayrollController::class, 'estimate']);
    Route::get('/{payrollEntry}', [PayrollController::class, 'show']);
    Route::post('/{payrollEntry}/mark-paid', [PayrollController::class, 'markPaid']);
});
