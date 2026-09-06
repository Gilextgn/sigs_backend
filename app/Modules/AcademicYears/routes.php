<?php

use Illuminate\Support\Facades\Route;
use Modules\AcademicYears\Http\Controllers\AcademicYearController;
use Modules\AcademicYears\Http\Controllers\SchoolSettingController;

Route::middleware('auth:sanctum')->prefix('settings')->group(function () {
    Route::get('/', [SchoolSettingController::class, 'index']);
    Route::get('/letterhead', [SchoolSettingController::class, 'letterhead']);
    Route::put('/', [SchoolSettingController::class, 'update'])->middleware('permission:settings.manage');
    Route::post('/letterhead', [SchoolSettingController::class, 'uploadLetterhead'])->middleware('permission:settings.manage');
    Route::delete('/letterhead', [SchoolSettingController::class, 'deleteLetterhead'])->middleware('permission:settings.manage');
});

Route::middleware('auth:sanctum')->prefix('academic-years')->group(function () {
    Route::get('/', [AcademicYearController::class, 'index']);
    Route::post('/', [AcademicYearController::class, 'store'])->middleware('permission:settings.manage');
    Route::post('/{academicYear}/activate', [AcademicYearController::class, 'activate'])->middleware('permission:settings.manage');
});
