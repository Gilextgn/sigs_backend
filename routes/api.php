<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Les routes métier vivent dans app/Modules/{Domaine}/routes.php
// et sont chargées automatiquement par App\Providers\ModuleServiceProvider.
// Ce fichier ne garde que les routes transverses (santé, utilisateur courant).

Route::get('/ping', fn () => response()->json(['status' => 'ok', 'app' => 'SIGS Admin API']));

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    $user = $request->user()->load('role');

    return response()->json([
        'id' => $user->id,
        'full_name' => $user->full_name,
        'email' => $user->email,
        'role' => $user->role?->code,
        'permissions' => $user->permissions(),
    ]);
});
