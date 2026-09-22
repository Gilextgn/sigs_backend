<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\Controllers\PlatformAccountController;
use Modules\Platform\Http\Controllers\PlatformBillingController;
use Modules\Platform\Http\Controllers\PlatformSchoolController;

// Console du propriétaire de la plateforme. Ce module est chargé sans le
// middleware « school » : il pilote les établissements de l'extérieur.
Route::middleware(['auth:sanctum', 'platform'])->prefix('platform')->group(function () {
    Route::put('/account', [PlatformAccountController::class, 'update']);

    Route::get('/schools', [PlatformSchoolController::class, 'index']);
    Route::post('/schools', [PlatformSchoolController::class, 'store']);
    Route::get('/schools/{school}', [PlatformSchoolController::class, 'show']);
    Route::put('/schools/{school}', [PlatformSchoolController::class, 'update']);
    Route::post('/schools/{school}/suspend', [PlatformSchoolController::class, 'suspend']);
    Route::post('/schools/{school}/reactivate', [PlatformSchoolController::class, 'reactivate']);
    Route::post('/schools/{school}/reset-admin-password', [PlatformSchoolController::class, 'resetAdminPassword']);
    Route::put('/schools/{school}/admins/{userId}', [PlatformSchoolController::class, 'updateAdmin'])->whereNumber('userId');

    Route::post('/schools/{school}/payments', [PlatformBillingController::class, 'storePayment']);
    Route::delete('/schools/{school}/payments/{paymentId}', [PlatformBillingController::class, 'destroyPayment'])->whereNumber('paymentId');
    Route::post('/schools/{school}/reminders', [PlatformBillingController::class, 'storeReminder']);
});
