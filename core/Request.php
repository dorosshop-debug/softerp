<?php

namespace SoftNova\Core;

/**
 * Manejar solicitudes HTTP
 */

class Request
{
    /**
     * Obtener método HTTP
     */
    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    /**
     * Obtener URI de la solicitud
     */
    public function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remover query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        // Remover prefijo /public si existe
        $uri = str_replace('/public', '', $uri);
        
        // Remover prefijo /SoftNova si existe
        $uri = str_replace('/SoftNova', '', $uri);
        
        // Asegurar que siempre empiece con /
        if ($uri !== '' && $uri[0] !== '/') {
            $uri = '/' . $uri;
        }
        
        return $uri ?: '/';
    }
    
    /**
     * Obtener parámetro GET
     */
    public function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }
    
    /**
     * Obtener parámetro POST
     */
    public function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }
    
    /**
     * Obtener todos los datos POST
     */
    public function all(): array
    {
        return $_POST;
    }
    
    /**
     * Verificar si es solicitud AJAX
     */
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Obtener IP del cliente
     */
    public function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        
        return $ip;
    }
}
