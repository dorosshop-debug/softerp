<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

/**
 * Controlador del módulo Caja para tenants
 * Apertura, cierre y movimientos de caja diaria
 */
class TenantCajaController extends Controller
{
    private \PDO $db;
    
    public function __construct()
    {
        parent::__construct();
        TenantMiddleware::auth();
        $this->db = TenantMiddleware::getDb();
    }
    
    /**
     * Panel principal de Caja
     */
    public function index(): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'open' && $this->request->method() === 'POST') {
            $this->openCash();
            return;
        }
        if ($action === 'close' && $this->request->method() === 'POST') {
            $this->closeCash();
            return;
        }
        if ($action === 'movement' && $this->request->method() === 'POST') {
            $this->addMovement();
            return;
        }
        
        // Buscar sesión abierta hoy
        $openSession = $this->db->query(
            "SELECT cs.*, u.name as user_name 
             FROM cash_sessions cs 
             JOIN users u ON cs.user_id = u.id 
             WHERE cs.status = 'open' 
             ORDER BY cs.opening_date DESC LIMIT 1"
        )->fetch();
        
        // Movimientos del día
        $movements = [];
        $totals = ['incomes' => 0, 'expenses' => 0, 'balance' => 0];
        
        if ($openSession) {
            $movements = $this->db->query(
                "SELECT * FROM cash_movements 
                 WHERE cash_session_id = ? 
                 ORDER BY created_at DESC",
                [$openSession['id']]
            )->fetchAll();
            
            $totals = $this->db->query(
                "SELECT 
                    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as incomes,
                    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses
                 FROM cash_movements 
                 WHERE cash_session_id = ?",
                [$openSession['id']]
            )->fetch();
            
            $totals['balance'] = $openSession['opening_amount'] + $totals['incomes'] - $totals['expenses'];
        }
        
        // Últimas sesiones cerradas (historial)
        $historySessions = $this->db->query(
            "SELECT cs.*, u.name as user_name 
             FROM cash_sessions cs 
             JOIN users u ON cs.user_id = u.id 
             WHERE cs.status = 'closed' 
             ORDER BY cs.closing_date DESC LIMIT 10"
        )->fetchAll();
        
        $this->view('tenant.caja', [
            'openSession' => $openSession,
            'movements' => $movements,
            'totals' => $totals,
            'historySessions' => $historySessions,
            'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa',
            'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
        ]);
    }
    
    /**
     * Abrir una nueva sesión de caja
     */
    private function openCash(): void
    {
        if (!$this->validateCsrfOrFail('/app/caja')) {
            return;
        }
        
        // Verificar que no haya una sesión abierta
        $existing = $this->db->query(
            "SELECT id FROM cash_sessions WHERE status = 'open' LIMIT 1"
        )->fetch();
        
        if ($existing) {
            $this->respond(false, 'Ya existe una caja abierta. Debe cerrarla primero.', '/app/caja');
            return;
        }
        
        $openingAmount = (float) $this->request->post('opening_amount');
        $notes = $this->request->post('notes', '');
        
        if ($openingAmount < 0) {
            $this->respond(false, 'El monto de apertura no puede ser negativo', '/app/caja');
            return;
        }
        
        $userId = $_SESSION['tenant_user_id'];
        
        $this->db->query(
            "INSERT INTO cash_sessions (user_id, opening_amount, opening_date, status, notes) 
             VALUES (?, ?, NOW(), 'open', ?)",
            [$userId, $openingAmount, $notes]
        );
        
        $this->respond(true, 'Caja abierta exitosamente con $' . number_format($openingAmount, 2), '/app/caja');
    }
    
    /**
     * Cerrar la sesión de caja actual
     */
    private function closeCash(): void
    {
        if (!$this->validateCsrfOrFail('/app/caja')) {
            return;
        }
        
        $sessionId = (int) $this->request->post('session_id');
        $closingAmount = (float) $this->request->post('closing_amount');
        $notes = $this->request->post('notes', '');
        
        // Verificar que la sesión existe y está abierta
        $session = $this->db->query(
            "SELECT * FROM cash_sessions WHERE id = ? AND status = 'open'",
            [$sessionId]
        )->fetch();
        
        if (!$session) {
            $this->respond(false, 'Sesión de caja no encontrada o ya está cerrada', '/app/caja');
            return;
        }
        
        // Cerrar la sesión
        $this->db->query(
            "UPDATE cash_sessions 
             SET closing_amount = ?, closing_date = NOW(), status = 'closed', notes = CONCAT(IFNULL(notes,''), '\nCierre: ', ?) 
             WHERE id = ?",
            [$closingAmount, $notes, $sessionId]
        );
        
        $this->respond(true, 'Caja cerrada exitosamente. Monto final: $' . number_format($closingAmount, 2), '/app/caja');
    }
    
    /**
     * Registrar un movimiento manual (entrada o salida)
     */
    private function addMovement(): void
    {
        if (!$this->validateCsrfOrFail('/app/caja')) {
            return;
        }
        
        $sessionId = (int) $this->request->post('session_id');
        $type = $this->request->post('type');
        $amount = (float) $this->request->post('amount');
        $description = $this->request->post('description', '');
        
        if (!in_array($type, ['income', 'expense'])) {
            $this->respond(false, 'Tipo de movimiento inválido', '/app/caja');
            return;
        }
        
        if ($amount <= 0) {
            $this->respond(false, 'El monto debe ser mayor a cero', '/app/caja');
            return;
        }
        
        if (empty(trim($description))) {
            $this->respond(false, 'La descripción es requerida', '/app/caja');
            return;
        }
        
        // Verificar que la sesión esté abierta
        $session = $this->db->query(
            "SELECT id FROM cash_sessions WHERE id = ? AND status = 'open'",
            [$sessionId]
        )->fetch();
        
        if (!$session) {
            $this->respond(false, 'La caja no está abierta', '/app/caja');
            return;
        }
        
        $this->db->query(
            "INSERT INTO cash_movements (cash_session_id, type, amount, description) 
             VALUES (?, ?, ?, ?)",
            [$sessionId, $type, $amount, $description]
        );
        
        $label = $type === 'income' ? 'Ingreso' : 'Egreso';
        $this->respond(true, "{$label} registrado: $" . number_format($amount, 2), '/app/caja');
    }
}
