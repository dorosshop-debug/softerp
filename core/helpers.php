<?php

namespace SoftNova\Core;

/**
 * Funciones helper globales
 */

/**
 * Obtener valor de configuración
 */
function config(string $key, $default = null)
{
    static $config = null;
    
    if ($config === null) {
        $config = [];
        
        // Cargar archivos de configuración
        $configFiles = [
            CONFIG_PATH . '/app.php',
            CONFIG_PATH . '/database.php',
            CONFIG_PATH . '/security.php',
        ];
        
        foreach ($configFiles as $file) {
            if (file_exists($file)) {
                $config = array_merge($config, require $file);
            }
        }
    }
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

/**
 * Obtener URL base
 */
function base_url(string $path = ''): string
{
    $baseUrl = config('app.url', 'http://localhost/SoftNova');
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

/**
 * Obtener URL de ruta
 */
function route(string $path): string
{
    return base_url('public/' . ltrim($path, '/'));
}

/**
 * Obtener URL de assets
 */
function asset(string $path): string
{
    return base_url('public/assets/' . ltrim($path, '/'));
}

/**
 * Redireccionar
 */
function redirect(string $url): void
{
    $baseUrl = config('app.url', 'http://localhost/SoftNova/public');
    if (!str_starts_with($url, 'http')) {
        $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }
    header("Location: {$url}");
    exit;
}

/**
 * Obtener valor de sesión
 */
function session(string $key, $default = null)
{
    return $_SESSION[$key] ?? $default;
}

/**
 * Establecer valor de sesión
 */
function set_session(string $key, $value): void
{
    $_SESSION[$key] = $value;
}

/**
 * Verificar si hay sesión activa
 */
function has_session(string $key): bool
{
    return isset($_SESSION[$key]);
}

/**
 * Obtener token CSRF
 */
function csrf_token(): string
{
    return Security::generateCsrfToken();
}

/**
 * Generar campo input hidden con token CSRF
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . Security::generateCsrfToken() . '">';
}
