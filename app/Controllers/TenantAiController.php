<?php

namespace SoftNova\Controllers;

use SoftNova\Core\TenantController;
use SoftNova\Services\AiService;

/**
 * Asistente IA "Seri" — OpenRouter + NVIDIA Nemotron 3 Ultra
 */
class TenantAiController extends TenantController
{
    public function index(): void
    {
        $ai = new AiService($this->db);
        
        $this->view('tenant.ai', $this->tenantViewData([
            'aiConfigured' => $ai->isConfigured(),
            'aiModel' => \SoftNova\Core\config('ai.model', 'nvidia/nemotron-3-ultra-550b-a55b:free'),
            'aiPersonality' => \SoftNova\Core\config('ai_personality', []),
        ]));
    }
    
    public function chat(): void
    {
        if (!$this->validateCsrfOrFail('/app/ia')) {
            return;
        }
        
        $message = trim($this->request->post('message') ?? '');
        if ($message === '') {
            $this->json(['reply' => 'Por favor, escribe un mensaje para que pueda ayudarte.']);
            return;
        }
        
        // Limitar longitud para evitar abusos
        if (mb_strlen($message) > 2000) {
            $this->json(['reply' => 'El mensaje es demasiado largo. Maximo 2000 caracteres.']);
            return;
        }
        
        $ai = new AiService($this->db);
        $reply = $ai->chat(
            $message,
            $_SESSION['tenant_name'] ?? 'Mi Empresa',
            $_SESSION['tenant_user_name'] ?? 'Usuario'
        );
        
        $this->json(['reply' => $reply]);
    }
}
