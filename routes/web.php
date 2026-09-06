<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'message' => 'SIGS Admin API - voir /api/ping',
]));
