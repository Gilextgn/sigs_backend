<?php

use Illuminate\Support\Facades\Route;
use Modules\Debtors\Http\Controllers\DebtorController;
use Modules\Debtors\Http\Controllers\ReminderController;

Route::middleware(['auth:sanctum', 'permission:debtors.print'])->get('/debtors', [DebtorController::class, 'index']);

Route::middleware('auth:sanctum')->prefix('reminders')->group(function () {
    Route::get('/', [ReminderController::class, 'index'])->middleware('permission:debtors.print');
    Route::put('/settings', [ReminderController::class, 'updateSettings'])->middleware('permission:settings.manage');
    Route::post('/send', [ReminderController::class, 'send'])->middleware('permission:debtors.print');
    Route::post('/{studentId}/manual', [ReminderController::class, 'logManual'])->middleware('permission:debtors.print')->whereNumber('studentId');
    // Relances automatiques du jour : déclenchées par n'importe quel utilisateur connecté de l'école.
    Route::post('/auto', [ReminderController::class, 'auto']);
});
