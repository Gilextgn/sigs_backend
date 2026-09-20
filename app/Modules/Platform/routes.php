<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\Controllers\PlatformSchoolController;

// Console du propriétaire de la plateforme. Ce module est chargé sans le
// middleware « school » : il pilote les établissements de l'extérieur.
Route::middleware(['auth:sanctum', 'platform'])->prefix('platform')->group(function () {
    Route::get('/schools', [PlatformSchoolController::class, 'index']);
    Route::post('/schools', [PlatformSchoolController::class, 'store']);
    Route::get('/schools/{school}', [PlatformSchoolController::class, 'show']);
    Route::put('/schools/{school}', [PlatformSchoolController::class, 'update']);
    Route::post('/schools/{school}/suspend', [PlatformSchoolController::class, 'suspend']);
    Route::post('/schools/{school}/reactivate', [PlatformSchoolController::class, 'reactivate']);
    Route::post('/schools/{school}/reset-admin-password', [PlatformSchoolController::class, 'resetAdminPassword']);
});
