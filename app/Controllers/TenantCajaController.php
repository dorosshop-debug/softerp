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
     * Helper: ejecutar query con parámetros (compatible con PDO crudo)
     */
    private function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Obtener la moneda configurada por el tenant
     */
    private function getCurrency(): array
    {
        $currency = $this->query(
            "SELECT setting_value FROM settings WHERE setting_key = 'currency'"
        )->fetch();
        
        $code = $currency['setting_value'] ?? 'COP';
        
        $currencies = [
            'COP' => ['symbol' => '$', 'name' => 'Peso Colombiano', 'decimals' => 0, 'thousands' => '.', 'decimal' => ','],
            'USD' => ['symbol' => 'US$', 'name' => 'Dólar Estadounidense', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
            'EUR' => ['symbol' => '€', 'name' => 'Euro', 'decimals' => 2, 'thousands' => '.', 'decimal' => ','],
        ];
        
        return $currencies[$code] ?? $currencies['COP'];
    }
    
    /**
     * Formatear monto según moneda
     */
    private function formatMoney(float $amount): string
    {
        $c = $this->getCurrency();
        return $c['symbol'] . ' ' . number_format($amount, $c['decimals'], $c['decimal'], $c['thousands']);
    }
    
    /**
     * Panel principal de Caja
     */
    public function index(): void
    {
        \SoftNova\Core\TenantMiddleware::authorize('caja');
        
        $action = $this->request->get('action');
        
        if ($action === 'open' && $this->request->method() === 'POST') {
            \SoftNova\Core\TenantMiddleware::authorize('caja', 'create');
            $this->openCash();
            return;
        }
        if ($action === 'close' && $this->request->method() === 'POST') {
            \SoftNova\Core\TenantMiddleware::authorize('caja', 'edit');
            $this->closeCash();
            return;
        }
        if ($action === 'movement' && $this->request->method() === 'POST') {
            \SoftNova\Core\TenantMiddleware::authorize('caja', 'create');
            $this->addMovement();
            return;
        }
        if ($action === 'pdf' && $this->request->method() === 'GET') {
            $this->closingPdf();
            return;
        }
        
        // Buscar sesión abierta hoy
        $openSession = $this->query(
            "SELECT cs.*, u.name as user_name
             FROM cash_sessions cs
             JOIN users u ON cs.user_id = u.id
             WHERE cs.status = 'open'
             ORDER BY cs.opening_date DESC LIMIT 1"
        )->fetch();
        
        // Movimientos del día
        $movements = [];
        $todaySales = [];
        $totals = ['incomes' => 0, 'expenses' => 0, 'balance' => 0];
        $salesIncome = 0;
        
        if ($openSession) {
            $movements = $this->query(
                "SELECT * FROM cash_movements
                 WHERE cash_session_id = ?
                 ORDER BY created_at DESC",
                [$openSession['id']]
            )->fetchAll();
            
            $totals = $this->query(
                "SELECT
                    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as incomes,
                    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses
                 FROM cash_movements
                 WHERE cash_session_id = ?",
                [$openSession['id']]
            )->fetch();
            
            // Ventas del día (completadas, desde apertura de caja)
            $todaySales = $this->query(
                "SELECT s.id, s.invoice_number, s.total, s.sale_date, s.payment_method,
                        c.name as customer_name, u.name as user_name,
                        COALESCE((SELECT SUM(sp.amount) FROM sale_payments sp WHERE sp.sale_id = s.id), 0) as paid_amount
                 FROM sales s
                 LEFT JOIN customers c ON s.customer_id = c.id
                 LEFT JOIN users u ON s.user_id = u.id
                 WHERE s.status = 'completed'
                   AND s.sale_date >= ?
                 ORDER BY s.sale_date DESC",
                [$openSession['opening_date']]
            )->fetchAll();
            
            // Sumar ventas que no estén ya en cash_movements como income
            $movementSaleIds = [];
            foreach ($movements as $mov) {
                if ($mov['reference_type'] === 'sale' && $mov['reference_id']) {
                    $movementSaleIds[] = (int)$mov['reference_id'];
                }
            }
            foreach ($todaySales as $sale) {
                if (!in_array((int)$sale['id'], $movementSaleIds)) {
                    $salesIncome += (float)$sale['total'];
                }
            }
            
            $totals['incomes'] = (float)$totals['incomes'] + $salesIncome;
            $totals['balance'] = $openSession['opening_amount'] + $totals['incomes'] - (float)$totals['expenses'];
        }
        
        // Últimas sesiones cerradas (historial)
        $historySessions = $this->query(
            "SELECT cs.*, u.name as user_name
             FROM cash_sessions cs
             JOIN users u ON cs.user_id = u.id
             WHERE cs.status = 'closed'
             ORDER BY cs.closing_date DESC LIMIT 10"
        )->fetchAll();
        
        $this->view('tenant.caja', [
            'openSession' => $openSession,
            'movements' => $movements,
            'todaySales' => $todaySales,
            'totals' => $totals,
            'historySessions' => $historySessions,
            'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa',
            'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
            'currency' => $this->getCurrency(),
            'formatMoney' => fn($a) => $this->formatMoney($a),
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
        $existing = $this->query(
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
        
        $this->query(
            "INSERT INTO cash_sessions (user_id, opening_amount, opening_date, status, notes) 
             VALUES (?, ?, NOW(), 'open', ?)",
            [$userId, $openingAmount, $notes]
        );
        
        $this->respond(true, 'Caja abierta exitosamente con ' . $this->formatMoney($openingAmount), '/app/caja');
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
        $session = $this->query(
            "SELECT * FROM cash_sessions WHERE id = ? AND status = 'open'",
            [$sessionId]
        )->fetch();
        
        if (!$session) {
            $this->respond(false, 'Sesión de caja no encontrada o ya está cerrada', '/app/caja');
            return;
        }
        
        // Cerrar la sesión
        $this->query(
            "UPDATE cash_sessions 
             SET closing_amount = ?, closing_date = NOW(), status = 'closed', notes = CONCAT(IFNULL(notes,''), '\nCierre: ', ?) 
             WHERE id = ?",
            [$closingAmount, $notes, $sessionId]
        );
        
        $this->respond(true, 'Caja cerrada exitosamente. Monto final: ' . $this->formatMoney($closingAmount), '/app/caja');
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
        $session = $this->query(
            "SELECT id FROM cash_sessions WHERE id = ? AND status = 'open'",
            [$sessionId]
        )->fetch();
        
        if (!$session) {
            $this->respond(false, 'La caja no está abierta', '/app/caja');
            return;
        }
        
        $this->query(
            "INSERT INTO cash_movements (cash_session_id, type, amount, description) 
             VALUES (?, ?, ?, ?)",
            [$sessionId, $type, $amount, $description]
        );
        
        $label = $type === 'income' ? 'Ingreso' : 'Egreso';
        $this->respond(true, "{$label} registrado: " . $this->formatMoney($amount), '/app/caja');
    }
    
    /**
     * Generar PDF de cierre de caja
     */
    private function closingPdf(): void
    {
        $id = (int)$this->request->get('id');
        $session = $this->query(
            "SELECT cs.*, u.name as user_name FROM cash_sessions cs JOIN users u ON cs.user_id=u.id WHERE cs.id=?", [$id]
        )->fetch();
        if (!$session) { echo 'Sesión no encontrada'; exit; }
        
        $movements = $this->query("SELECT * FROM cash_movements WHERE cash_session_id=? ORDER BY created_at DESC", [$id])->fetchAll();
        
        $totals = $this->query(
            "SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) as incomes,
                    COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) as expenses
             FROM cash_movements WHERE cash_session_id=?", [$id]
        )->fetch();
        
        $company = [];
        $rows = $this->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
        foreach ($rows as $row) { $company[$row['setting_key']] = $row['setting_value']; }
        
        $pdf = new \SoftNova\Services\PdfService($company, $this->getCurrency());
        $content = $pdf->generateCashClosing($session, $movements, $totals);
        $pdf->download($content, 'Cierre_Caja_' . $id . '.pdf');
    }
}
