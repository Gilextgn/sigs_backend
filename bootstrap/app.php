<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        App\Providers\ModuleServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Faire confiance aux proxys de Render pour HTTPS / Cookies
        $middleware->trustProxies(at: '*');

        // SPA stateful auth (Sanctum)
        $middleware->statefulApi();

        $middleware->alias([
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'school' => \App\Http\Middleware\ResolveActiveSchool::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
