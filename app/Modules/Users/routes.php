<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\RoleController;
use Modules\Users\Http\Controllers\UserController;

Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index'])->middleware('permission:users.manage');
    Route::post('/', [UserController::class, 'store'])->middleware('permission:users.manage');
    Route::get('/{user}', [UserController::class, 'show'])->middleware('permission:users.manage');
    Route::put('/{user}', [UserController::class, 'update'])->middleware('permission:users.manage');
    Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage');
});

Route::middleware('auth:sanctum')->prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::get('/permissions-catalog', [RoleController::class, 'permissionsCatalog']);
});
