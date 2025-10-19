<?php

return [

    // Apply CORS to API and session-based web endpoints
    'paths' => ['api/*', 'web/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Allow current dev server origins (5173/5174/5175) for localhost and 127.0.0.1
    'allowed_origins' => [
        'http://localhost:8000', 'http://127.0.0.1:8000',
        'http://localhost:8001', 'http://127.0.0.1:8001',
        'http://localhost:5173', 'http://127.0.0.1:5173',
        'http://localhost:5174', 'http://127.0.0.1:5174',
        'http://localhost:5175', 'http://127.0.0.1:5175',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
