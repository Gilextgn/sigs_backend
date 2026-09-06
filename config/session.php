<?php

return [
    'driver' => env('SESSION_DRIVER', 'database'),
    // Timeout d'inactivité repris tel quel de auth-middleware.php (600s = 10 min)
    'lifetime' => (int) env('SESSION_IDLE_TIMEOUT_SECONDS', 600) / 60,
    'expire_on_close' => false,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => 'sessions_web',
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'sigs_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
];
