<?php

use Illuminate\Support\Facades\Route;
use Modules\Students\Http\Controllers\StudentController;
use Modules\Students\Http\Controllers\StudentEnrollmentController;

Route::middleware('auth:sanctum')->prefix('students')->group(function () {
    Route::get('/', [StudentController::class, 'index'])->middleware('permission:students.view');
    Route::post('/', [StudentController::class, 'store'])->middleware('permission:students.create');
    Route::get('/re-enrollment-progress', [StudentEnrollmentController::class, 'progress'])->middleware('permission:students.view');
    Route::post('/re-enroll-bulk', [StudentEnrollmentController::class, 'bulkStore'])->middleware('permission:students.reenroll');
    Route::get('/{student}', [StudentController::class, 'show'])->middleware('permission:students.view');
    Route::put('/{student}', [StudentController::class, 'update'])->middleware('permission:students.update');
    Route::delete('/{student}', [StudentController::class, 'destroy'])->middleware('permission:students.delete');
    Route::get('/{student}/balance', [StudentEnrollmentController::class, 'balance'])->middleware('permission:students.view');
    Route::get('/{student}/enrollments', [StudentEnrollmentController::class, 'index'])->middleware('permission:students.view');
    Route::get('/{student}/re-enrollment-context', [StudentEnrollmentController::class, 'context'])->middleware('permission:students.reenroll');
    Route::post('/{student}/re-enroll', [StudentEnrollmentController::class, 'store'])->middleware('permission:students.reenroll');
});
