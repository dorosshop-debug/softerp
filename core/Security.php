<?php

namespace SoftNova\Core;

/**
 * Clase de seguridad
 */

class Security
{
    /**
     * Generar token CSRF
     */
    public static function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verificar token CSRF
     */
    public static function verifyCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Hashear contraseña con Argon2ID
     */
    public static function hashPassword(string $password): string
    {
        $options = config('security.password_options', [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ]);
        
        return password_hash($password, PASSWORD_ARGON2ID, $options);
    }
    
    /**
     * Verificar contraseña
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Escapar salida HTML
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Sanitizar entrada
     */
    public static function sanitize(string $value): string
    {
        return strip_tags(trim($value));
    }
    
    /**
     * Generar cadena aleatoria
     */
    public static function randomString(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}
