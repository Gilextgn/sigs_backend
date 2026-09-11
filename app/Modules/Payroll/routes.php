<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\PayrollController;

Route::middleware('auth:sanctum')->prefix('payroll')->group(function () {
    Route::get('/', [PayrollController::class, 'index'])->middleware('permission:teachers.view');
    Route::get('/estimate', [PayrollController::class, 'estimate'])->middleware('permission:teachers.view');
    Route::get('/{payrollEntry}', [PayrollController::class, 'show'])->middleware('permission:teachers.view');

    Route::post('/', [PayrollController::class, 'store'])->middleware('permission:teachers.manage');
    Route::post('/{payrollEntry}/mark-paid', [PayrollController::class, 'markPaid'])->middleware('permission:teachers.manage');
});
