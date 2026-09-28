<?php

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\AdminNotificationController;
use Modules\Security\Http\Controllers\AuditLogController;

Route::middleware(['auth:sanctum', 'permission:audit.view'])->get('/audit-logs', [AuditLogController::class, 'index']);

Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [AdminNotificationController::class, 'index']);
    Route::post('/read-all', [AdminNotificationController::class, 'markAllRead']);
    Route::post('/{notification}/read', [AdminNotificationController::class, 'markRead']);
});
