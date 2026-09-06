<?php

use Illuminate\Support\Facades\Route;
use Modules\Students\Http\Controllers\StudentController;

Route::middleware('auth:sanctum')->prefix('students')->group(function () {
    Route::get('/', [StudentController::class, 'index'])->middleware('permission:students.view');
    Route::post('/', [StudentController::class, 'store'])->middleware('permission:students.create');
    Route::get('/{student}', [StudentController::class, 'show'])->middleware('permission:students.view');
    Route::put('/{student}', [StudentController::class, 'update'])->middleware('permission:students.update');
    Route::delete('/{student}', [StudentController::class, 'destroy'])->middleware('permission:students.delete');
});
