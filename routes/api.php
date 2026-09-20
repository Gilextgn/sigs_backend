<?php

use App\Support\SchoolAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Les routes métier vivent dans app/Modules/{Domaine}/routes.php
// et sont chargées automatiquement par App\Providers\ModuleServiceProvider.
// Ce fichier ne garde que les routes transverses (santé, utilisateur courant).

Route::get('/ping', fn () => response()->json(['status' => 'ok', 'app' => 'SIGS Admin API']));

Route::middleware(['auth:sanctum', 'school'])->get('/me', function (Request $request) {
    $user = $request->user()->load('role');

    return response()->json(SchoolAccess::userPayload($user));
});
