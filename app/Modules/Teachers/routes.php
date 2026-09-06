<?php

use Illuminate\Support\Facades\Route;
use Modules\Teachers\Http\Controllers\TeacherController;
use Modules\Teachers\Http\Controllers\SubjectController;
use Modules\Teachers\Http\Controllers\TeacherAssignmentController;
use Modules\Teachers\Http\Controllers\ScheduleController;
use Modules\Teachers\Http\Controllers\AttendanceController;

Route::middleware(['auth:sanctum', 'permission:teachers.manage'])->prefix('teachers')->group(function () {
    Route::get('/', [TeacherController::class, 'index']);
    Route::post('/', [TeacherController::class, 'store']);
    Route::put('/{teacher}', [TeacherController::class, 'update']);
    Route::delete('/{teacher}', [TeacherController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'permission:teachers.manage'])->prefix('subjects')->group(function () {
    Route::get('/', [SubjectController::class, 'index']);
    Route::post('/', [SubjectController::class, 'store']);
    Route::put('/{subject}', [SubjectController::class, 'update']);
    Route::delete('/{subject}', [SubjectController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'permission:teachers.manage'])->prefix('teacher-assignments')->group(function () {
    Route::get('/', [TeacherAssignmentController::class, 'index']);
    Route::post('/', [TeacherAssignmentController::class, 'store']);
    Route::put('/{teacherAssignment}', [TeacherAssignmentController::class, 'update']);
    Route::delete('/{teacherAssignment}', [TeacherAssignmentController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'permission:teachers.manage'])->prefix('schedules')->group(function () {
    Route::get('/', [ScheduleController::class, 'index']);
    Route::post('/', [ScheduleController::class, 'store']);
    Route::put('/{schedule}', [ScheduleController::class, 'update']);
    Route::delete('/{schedule}', [ScheduleController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'permission:teachers.manage'])->prefix('teacher-attendances')->group(function () {
    Route::get('/', [AttendanceController::class, 'index']);
    Route::post('/', [AttendanceController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'permission:teachers.manage'])->prefix('teaching-sessions')->group(function () {
    Route::get('/', [AttendanceController::class, 'sessions']);
    Route::post('/', [AttendanceController::class, 'createSession']);
    Route::post('/generate', [AttendanceController::class, 'generate']);
});
