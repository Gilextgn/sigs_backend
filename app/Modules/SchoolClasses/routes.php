<?php

use Illuminate\Support\Facades\Route;
use Modules\SchoolClasses\Http\Controllers\ClassController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cycles', [ClassController::class, 'cycles']);

    Route::prefix('classes')->group(function () {
        Route::get('/', [ClassController::class, 'index']);
        Route::post('/', [ClassController::class, 'store'])->middleware('permission:classes.manage');
        Route::get('/{class}', [ClassController::class, 'show']);
        Route::put('/{class}', [ClassController::class, 'update'])->middleware('permission:classes.manage');
        Route::delete('/{class}', [ClassController::class, 'destroy'])->middleware('permission:classes.manage');
    });
});
