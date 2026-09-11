<?php

use Illuminate\Support\Facades\Route;
use Modules\Teachers\Http\Controllers\TeacherController;
use Modules\Teachers\Http\Controllers\SubjectController;
use Modules\Teachers\Http\Controllers\TeacherAssignmentController;
use Modules\Teachers\Http\Controllers\ScheduleController;
use Modules\Teachers\Http\Controllers\AttendanceController;

/*
 * Lecture et écriture sont séparées : consulter la liste des enseignants ne
 * doit pas exiger le droit de la modifier (un caissier, ou un compte de
 * démonstration, peut légitimement consulter sans pouvoir écrire).
 */
Route::middleware(['auth:sanctum', 'permission:teachers.view'])->group(function () {
    Route::get('teachers', [TeacherController::class, 'index']);
    Route::get('subjects', [SubjectController::class, 'index']);
    Route::get('teacher-assignments', [TeacherAssignmentController::class, 'index']);
    Route::get('schedules', [ScheduleController::class, 'index']);
    Route::get('teacher-attendances', [AttendanceController::class, 'index']);
    Route::get('teaching-sessions', [AttendanceController::class, 'sessions']);
});

Route::middleware(['auth:sanctum', 'permission:teachers.manage'])->group(function () {
    Route::post('teachers', [TeacherController::class, 'store']);
    Route::put('teachers/{teacher}', [TeacherController::class, 'update']);
    Route::delete('teachers/{teacher}', [TeacherController::class, 'destroy']);

    Route::post('subjects', [SubjectController::class, 'store']);
    Route::put('subjects/{subject}', [SubjectController::class, 'update']);
    Route::delete('subjects/{subject}', [SubjectController::class, 'destroy']);

    Route::post('teacher-assignments', [TeacherAssignmentController::class, 'store']);
    Route::put('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'update']);
    Route::delete('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'destroy']);

    Route::post('schedules', [ScheduleController::class, 'store']);
    Route::put('schedules/{schedule}', [ScheduleController::class, 'update']);
    Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy']);

    Route::post('teacher-attendances', [AttendanceController::class, 'store']);

    Route::post('teaching-sessions', [AttendanceController::class, 'createSession']);
    Route::post('teaching-sessions/generate', [AttendanceController::class, 'generate']);
});
