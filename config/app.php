<?php

/**
 * Configuración principal de la aplicación
 */

return [
    'app' => [
        'name' => 'Seri ERP',
        'version' => '1.0.0',
        'env' => 'development',
        'debug' => true,
        'url' => 'http://localhost/SoftNova/public',
        'timezone' => 'UTC',
    ],
    
    'session' => [
        'name' => 'SOFTNOVA_SESSION',
        'lifetime' => 7200,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ],
    
    'security' => [
        'csrf_token_name' => 'csrf_token',
        'csrf_header_name' => 'X-CSRF-TOKEN',
        'password_algorithm' => PASSWORD_ARGON2ID,
        'password_options' => [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ],
    ],
];
