<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\DashboardController;

Route::middleware(['auth:sanctum', 'permission:dashboard.view'])->prefix('dashboard')->group(function () {
    Route::get('/summary', [DashboardController::class, 'summary']);
    Route::get('/recent-payments', [DashboardController::class, 'recentPayments']);
    Route::get('/top-debtors', [DashboardController::class, 'topDebtors']);
    Route::get('/statistics', [DashboardController::class, 'statistics']);
});
