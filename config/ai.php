<?php

/**
 * Configuración del asistente IA (OpenRouter)
 * La API key se lee desde .env (OPENROUTER_API_KEY) o desde esta config.
 */

return [
    'ai' => [
        'enabled' => true,
        'provider' => 'openrouter',
        'base_url' => 'https://openrouter.ai/api/v1',
        // NVIDIA Nemotron 3 Ultra (free) via OpenRouter
        'model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        'api_key' => getenv('OPENROUTER_API_KEY') ?: '',
        'timeout' => 60,
        'max_tokens' => 1200,
        'temperature' => 0.4,
        'site_url' => 'http://localhost/SoftNova/public',
        'site_name' => 'Seri ERP',
        // Si OpenRouter falla, usar respuestas locales por reglas
        'fallback_local' => true,
    ],
];
