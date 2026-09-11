<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

Route::get('/', function () {
    return response()->json(['message' => 'SIGS Admin API - voir /api/ping']);
});

// Force la route csrf-cookie pour éviter le 404 en production
Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);
