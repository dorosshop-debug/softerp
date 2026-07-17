<?php

/**
 * Punto de entrada principal de la aplicación
 * Software de Gestión Active - ERP SaaS
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CORE_PATH', ROOT_PATH . '/core');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Cargar autoloader de Composer
require_once ROOT_PATH . '/vendor/autoload.php';

// Cargar helpers (antes de cualquier otra cosa)
require_once CORE_PATH . '/helpers.php';

// Configurar sesión segura antes de iniciarla
$sessionConfig = \SoftNova\Core\config('app.session', []);
if (!empty($sessionConfig['name'])) {
    session_name($sessionConfig['name']);
}
session_set_cookie_params([
    'lifetime' => $sessionConfig['lifetime'] ?? 7200,
    'path' => $sessionConfig['path'] ?? '/',
    'domain' => $sessionConfig['domain'] ?? '',
    'secure' => $sessionConfig['secure'] ?? false,
    'httponly' => $sessionConfig['httponly'] ?? true,
    'samesite' => $sessionConfig['samesite'] ?? 'Strict',
]);

// Iniciar sesión
session_start();

// Iniciar aplicación
try {
    $app = new SoftNova\Core\App();
    $app->run();
} catch (Exception $e) {
    \SoftNova\Core\Logger::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
    http_response_code(500);
    echo '<h1>Error interno del servidor</h1>';
    if (\SoftNova\Core\config('app.debug', false)) {
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
} catch (Error $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo '<h1>Error interno del servidor</h1>';
    if (\SoftNova\Core\config('app.debug', false)) {
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
}
