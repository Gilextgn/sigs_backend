<?php

use Illuminate\Support\Facades\Route;
use Modules\Debtors\Http\Controllers\DebtorController;

Route::middleware(['auth:sanctum', 'permission:debtors.print'])->get('/debtors', [DebtorController::class, 'index']);
