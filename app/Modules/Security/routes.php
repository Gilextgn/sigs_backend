<?php

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\AuditLogController;

Route::middleware(['auth:sanctum', 'permission:audit.view'])->get('/audit-logs', [AuditLogController::class, 'index']);
