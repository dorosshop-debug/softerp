<?php

namespace SoftNova\Core;

/**
 * Sistema de caché simple basado en archivos
 */
class Cache
{
    private string $cacheDir;
    
    public function __construct()
    {
        $this->cacheDir = STORAGE_PATH . '/cache/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }
    
    /**
     * Obtener valor del caché
     */
    public function get(string $key, $default = null)
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) return $default;
        
        $data = json_decode(file_get_contents($file), true);
        if (!$data || ($data['expires_at'] ?? 0) < time()) {
            @unlink($file);
            return $default;
        }
        return $data['value'] ?? $default;
    }
    
    /**
     * Guardar en caché
     */
    public function set(string $key, $value, int $ttlSeconds = 300): void
    {
        $file = $this->getFilePath($key);
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttlSeconds,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    /**
     * Eliminar del caché
     */
    public function forget(string $key): void
    {
        $file = $this->getFilePath($key);
        @unlink($file);
    }
    
    /**
     * Recordar: get or set
     */
    public function remember(string $key, int $ttlSeconds, callable $callback)
    {
        $value = $this->get($key);
        if ($value !== null) return $value;
        
        $value = $callback();
        $this->set($key, $value, $ttlSeconds);
        return $value;
    }
    
    /**
     * Limpiar caché expirado
     */
    public function clean(): int
    {
        $count = 0;
        foreach (glob($this->cacheDir . '*.cache') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && ($data['expires_at'] ?? 0) < time()) {
                @unlink($file);
                $count++;
            }
        }
        return $count;
    }
    
    private function getFilePath(string $key): string
    {
        return $this->cacheDir . md5($key) . '.cache';
    }
}
