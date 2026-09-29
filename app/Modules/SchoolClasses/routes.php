<?php

use Illuminate\Support\Facades\Route;
use Modules\SchoolClasses\Http\Controllers\ClassController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cycles', [ClassController::class, 'cycles'])->middleware('permission:classes.view');

    Route::prefix('classes')->group(function () {
        // La liste des classes sert aussi à ranger les élèves : consulter les élèves suffit.
        Route::get('/', [ClassController::class, 'index'])->middleware('permission:classes.view|students.view');
        Route::post('/', [ClassController::class, 'store'])->middleware('permission:classes.manage');
        Route::put('/reorder', [ClassController::class, 'reorder'])->middleware('permission:classes.manage');
        Route::get('/{class}', [ClassController::class, 'show'])->middleware('permission:classes.view');
        Route::put('/{class}', [ClassController::class, 'update'])->middleware('permission:classes.manage');
        Route::delete('/{class}', [ClassController::class, 'destroy'])->middleware('permission:classes.manage');
    });
});
