<?php

namespace SoftNova\Core;

/**
 * Caché simple: archivo local + Redis opcional (REDIS_HOST / REDIS_PORT).
 */
class SimpleCache
{
    private static ?self $instance = null;
    private string $dir;
    private $redis = null;
    private bool $redisTried = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function __construct(?string $dir = null)
    {
        $base = defined('STORAGE_PATH') ? STORAGE_PATH : (dirname(__DIR__) . '/storage');
        $this->dir = rtrim($dir ?? ($base . '/cache'), '/\\');
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $redis = $this->redis();
        if ($redis) {
            try {
                $raw = $redis->get($this->prefix($key));
                if ($raw !== false && $raw !== null) {
                    $decoded = json_decode($raw, true);
                    return array_key_exists('v', $decoded ?? []) ? $decoded['v'] : $default;
                }
            } catch (\Throwable $e) {
                // fallback file
            }
        }

        $path = $this->path($key);
        if (!is_file($path)) {
            return $default;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return $default;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !array_key_exists('v', $data)) {
            return $default;
        }
        if (!empty($data['e']) && time() > (int)$data['e']) {
            @unlink($path);
            return $default;
        }
        return $data['v'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 300): void
    {
        $payload = json_encode(['v' => $value, 'e' => time() + max(1, $ttlSeconds)], JSON_UNESCAPED_UNICODE);
        $redis = $this->redis();
        if ($redis) {
            try {
                $redis->setex($this->prefix($key), max(1, $ttlSeconds), $payload);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        @file_put_contents($this->path($key), $payload, LOCK_EX);
    }

    public function forget(string $key): void
    {
        $redis = $this->redis();
        if ($redis) {
            try {
                $redis->del($this->prefix($key));
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $hit = $this->get($key, new \stdClass());
        if (!$hit instanceof \stdClass) {
            return $hit;
        }
        $value = $callback();
        $this->set($key, $value, $ttlSeconds);
        return $value;
    }

    private function path(string $key): string
    {
        return $this->dir . '/' . sha1($key) . '.cache';
    }

    private function prefix(string $key): string
    {
        return 'softnova:' . $key;
    }

    private function redis()
    {
        if ($this->redisTried) {
            return $this->redis;
        }
        $this->redisTried = true;
        $host = getenv('REDIS_HOST') ?: ($_ENV['REDIS_HOST'] ?? '');
        if ($host === '' || !class_exists(\Redis::class)) {
            return null;
        }
        try {
            $r = new \Redis();
            $port = (int)(getenv('REDIS_PORT') ?: ($_ENV['REDIS_PORT'] ?? 6379));
            if (!$r->connect($host, $port, 0.4)) {
                return null;
            }
            $pass = getenv('REDIS_PASSWORD') ?: ($_ENV['REDIS_PASSWORD'] ?? '');
            if ($pass !== '') {
                $r->auth($pass);
            }
            $this->redis = $r;
        } catch (\Throwable $e) {
            $this->redis = null;
        }
        return $this->redis;
    }
}
