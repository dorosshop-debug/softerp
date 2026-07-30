<?php

namespace SoftNova\Controllers;

use SoftNova\Core\TenantController;
use SoftNova\Core\TenantMiddleware;

class TenantCajaController extends TenantController
{
    public function index(): void
    {
        TenantMiddleware::authorize('caja');
        
        $action = $this->request->get('action');
        
        if ($action === 'open' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('caja', 'create');
            $this->openCash();
            return;
        }
        if ($action === 'close' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('caja', 'edit');
            $this->closeCash();
            return;
        }
        if ($action === 'movement' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('caja', 'create');
            $this->addMovement();
            return;
        }
        if ($action === 'expense' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('caja', 'create');
            $this->registerExpense();
            return;
        }
        if ($action === 'pdf' && $this->request->method() === 'GET') {
            $this->closingPdf();
            return;
        }
        if ($action === 'searchProducts' && $this->request->method() === 'GET') {
            $this->searchProducts();
            return;
        }
        
        $openSession = $this->query(
            "SELECT cs.*, u.name as user_name
             FROM cash_sessions cs
             JOIN users u ON cs.user_id = u.id
             WHERE cs.status = 'open'
             ORDER BY cs.opening_date DESC LIMIT 1"
        )->fetch();
        
        $movements = [];
        $todaySales = [];
        $salesPagination = null;
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
            
            $salesTotal = (int)$this->query(
                "SELECT COUNT(*) FROM sales
                 WHERE status != 'cancelled' AND sale_date >= ?",
                [$openSession['opening_date']]
            )->fetchColumn();
            $salesPagination = $this->paginate($salesTotal, 20);

            $todaySales = $this->query(
                "SELECT s.id, s.invoice_number, s.total, s.sale_date, s.payment_method, s.payment_status, s.status,
                        c.name as customer_name, u.name as user_name,
                        COALESCE((SELECT SUM(sp.amount) FROM sale_payments sp WHERE sp.sale_id = s.id), 0) as paid_amount
                 FROM sales s
                 LEFT JOIN customers c ON s.customer_id = c.id
                 LEFT JOIN users u ON s.user_id = u.id
                 WHERE s.status != 'cancelled'
                   AND s.sale_date >= ?
                 ORDER BY s.sale_date DESC
                 LIMIT {$salesPagination['perPage']} OFFSET {$salesPagination['offset']}",
                [$openSession['opening_date']]
            )->fetchAll();
            
            $movementSaleIds = [];
            foreach ($movements as $mov) {
                if (($mov['reference_type'] ?? '') === 'sale' && !empty($mov['reference_id'])) {
                    $movementSaleIds[(int)$mov['reference_id']] = true;
                }
            }
            // Ingresos de ventas en efectivo (toda la sesión, no solo la página)
            $allCashSales = $this->query(
                "SELECT s.id, s.total, s.payment_method, s.payment_status,
                        COALESCE((SELECT SUM(sp.amount) FROM sale_payments sp WHERE sp.sale_id = s.id), 0) as paid_amount
                 FROM sales s
                 WHERE s.status != 'cancelled' AND s.sale_date >= ?",
                [$openSession['opening_date']]
            )->fetchAll();
            foreach ($allCashSales as $sale) {
                $saleId = (int)$sale['id'];
                if (isset($movementSaleIds[$saleId])) {
                    continue;
                }
                $method = (string)($sale['payment_method'] ?? 'cash');
                if (!\SoftNova\Services\PaymentMethodCatalog::affectsCash($method)) {
                    continue;
                }
                $paid = (float)($sale['paid_amount'] ?? 0);
                if (($sale['payment_status'] ?? '') === 'paid' && $paid <= 0) {
                    $paid = (float)$sale['total'];
                }
                if ($paid > 0) {
                    $salesIncome += $paid;
                }
            }
            
            $totals['incomes'] = (float)$totals['incomes'] + $salesIncome;
            $totals['expenses'] = (float)$totals['expenses'];
            $totals['balance'] = (float)$openSession['opening_amount'] + $totals['incomes'] - $totals['expenses'];
        }
        
        $historySessions = [];
        if (TenantMiddleware::isAdmin()) {
            $historySessions = $this->query(
                "SELECT cs.*, u.name as user_name
                 FROM cash_sessions cs
                 JOIN users u ON cs.user_id = u.id
                 WHERE cs.status = 'closed'
                 ORDER BY cs.closing_date DESC LIMIT 10"
            )->fetchAll();
        }

        $posCustomers = [];
        $paymentMethods = [];
        $taxRate = 0.0;
        $expenseCategories = [];
        $expenseSuppliers = [];
        if ($openSession) {
            try {
                $posCustomers = $this->query(
                    "SELECT id, name, first_name, last_name, document_number, phone
                     FROM customers
                     WHERE status = 'active'
                     ORDER BY COALESCE(NULLIF(TRIM(name), ''), first_name) ASC
                     LIMIT 400"
                )->fetchAll();
            } catch (\Throwable $e) {
                $posCustomers = [];
            }
            $paymentMethods = \SoftNova\Services\PaymentMethodCatalog::all();
            $taxRate = (float)$this->getSetting('tax_rate', '0');
            try {
                $catSvc = new \SoftNova\Services\ExpenseCategoryService($this->db);
                $expenseCategories = $catSvc->listActive();
            } catch (\Throwable $e) {
                $expenseCategories = [];
            }
            try {
                $expenseSuppliers = $this->query(
                    "SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name ASC LIMIT 300"
                )->fetchAll();
            } catch (\Throwable $e) {
                $expenseSuppliers = [];
            }
        }
        
        $this->view('tenant.caja', $this->tenantViewData([
            'openSession' => $openSession,
            'movements' => $movements,
            'todaySales' => $todaySales,
            'salesPagination' => $salesPagination ?? null,
            'totals' => $totals,
            'historySessions' => $historySessions,
            'posCustomers' => $posCustomers,
            'paymentMethods' => $paymentMethods,
            'expenseCategories' => $expenseCategories,
            'expenseSuppliers' => $expenseSuppliers,
            'invoicePrefix' => $this->getSetting('invoice_prefix', 'FAC-'),
            'taxRate' => $taxRate,
            'isAdmin' => TenantMiddleware::isAdmin(),
            'isPosUser' => TenantMiddleware::isPosUser(),
            'formatMoney' => fn($a) => $this->formatMoney($a),
        ]));
    }

    /**
     * Búsqueda rápida de productos para el POS de caja (código / nombre).
     */
    private function searchProducts(): void
    {
        $q = trim((string)$this->request->get('q', ''));
        if (mb_strlen($q) < 1) {
            $this->json(['success' => true, 'products' => []]);
            return;
        }

        $like = '%' . $q . '%';
        $exact = $q;
        try {
            $rows = $this->query(
                "SELECT id, name, code, sale_price, stock
                 FROM products
                 WHERE status = 'active'
                   AND (
                        code = ?
                        OR code LIKE ?
                        OR name LIKE ?
                        OR CAST(id AS CHAR) = ?
                   )
                 ORDER BY
                    CASE
                        WHEN code = ? THEN 0
                        WHEN code LIKE ? THEN 1
                        WHEN name LIKE ? THEN 2
                        ELSE 3
                    END,
                    name ASC
                 LIMIT 25",
                [$exact, $like, $like, $exact, $exact, $exact . '%', $like]
            )->fetchAll();
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'No se pudo buscar productos', 'products' => []]);
            return;
        }

        $products = array_map(static function (array $p): array {
            return [
                'id' => (int)$p['id'],
                'name' => (string)$p['name'],
                'code' => (string)($p['code'] ?? ''),
                'sale_price' => (float)$p['sale_price'],
                'stock' => (int)$p['stock'],
            ];
        }, $rows);

        $this->json(['success' => true, 'products' => $products]);
    }
    
    private function openCash(): void
    {
        if (!$this->validateCsrfOrFail('/app/caja')) {
            return;
        }
        
        $existing = $this->query("SELECT id FROM cash_sessions WHERE status = 'open' LIMIT 1")->fetch();
        if ($existing) {
            $this->respond(false, 'Ya existe una caja abierta. Debe cerrarla primero.', '/app/caja');
            return;
        }
        
        $openingAmount = (float)$this->request->post('opening_amount');
        $notes = $this->request->post('notes', '');
        
        if ($openingAmount < 0) {
            $this->respond(false, 'El monto de apertura no puede ser negativo', '/app/caja');
            return;
        }
        
        $this->query(
            "INSERT INTO cash_sessions (user_id, opening_amount, opening_date, status, notes)
             VALUES (?, ?, NOW(), 'open', ?)",
            [$_SESSION['tenant_user_id'], $openingAmount, $notes]
        );
        
        $this->respond(true, 'Caja abierta exitosamente con ' . $this->formatMoney($openingAmount), '/app/caja');
    }
    
    private function closeCash(): void
    {
        if (!$this->validateCsrfOrFail('/app/caja')) {
            return;
        }
        
        $sessionId = (int)$this->request->post('session_id');
        $closingAmount = (float)$this->request->post('closing_amount');
        $notes = $this->request->post('notes', '');
        
        $session = $this->query(
            "SELECT * FROM cash_sessions WHERE id = ? AND status = 'open'",
            [$sessionId]
        )->fetch();
        
        if (!$session) {
            $this->respond(false, 'Sesion de caja no encontrada o ya esta cerrada', '/app/caja');
            return;
        }
        
        $this->query(
            "UPDATE cash_sessions
             SET closing_amount = ?, closing_date = NOW(), status = 'closed',
                 notes = CONCAT(IFNULL(notes, ''), '\nCierre: ', ?)
             WHERE id = ?",
            [$closingAmount, $notes, $sessionId]
        );
        
        $this->respond(true, 'Caja cerrada exitosamente. Monto final: ' . $this->formatMoney($closingAmount), '/app/caja');
    }
    
    private function addMovement(): void
    {
        if (!$this->validateCsrfOrFail('/app/caja')) {
            return;
        }
        
        $sessionId = (int)$this->request->post('session_id');
        $type = $this->request->post('type');
        $amount = (float)$this->request->post('amount');
        $description = $this->request->post('description', '');
        
        if (!in_array($type, ['income', 'expense'], true)) {
            $this->respond(false, 'Tipo de movimiento invalido', '/app/caja');
            return;
        }
        
        if ($amount <= 0) {
            $this->respond(false, 'El monto debe ser mayor a cero', '/app/caja');
            return;
        }
        
        if (empty(trim($description))) {
            $this->respond(false, 'La descripcion es requerida', '/app/caja');
            return;
        }
        
        $session = $this->query(
            "SELECT id FROM cash_sessions WHERE id = ? AND status = 'open'",
            [$sessionId]
        )->fetch();
        
        if (!$session) {
            $this->respond(false, 'La caja no esta abierta', '/app/caja');
            return;
        }
        
        $this->query(
            "INSERT INTO cash_movements (cash_session_id, type, amount, description, reference_type, user_id, created_at)
             VALUES (?, ?, ?, ?, 'manual', ?, NOW())",
            [$sessionId, $type, $amount, $description, $_SESSION['tenant_user_id'] ?? null]
        );
        
        $label = $type === 'income' ? 'Ingreso' : 'Egreso';
        $this->respond(true, "{$label} registrado: " . $this->formatMoney($amount), '/app/caja');
    }

    /**
     * Registrar gasto desde Caja-POS (mismo flujo del módulo Gastos).
     */
    private function registerExpense(): void
    {
        if (!$this->validateCsrfOrFail('/app/caja')) {
            return;
        }

        $sessionId = (int)$this->request->post('session_id');
        $session = $this->query(
            "SELECT id FROM cash_sessions WHERE id = ? AND status = 'open'",
            [$sessionId]
        )->fetch();
        if (!$session) {
            $this->respond(false, 'La caja no esta abierta', '/app/caja');
            return;
        }

        $description = trim((string)$this->request->post('description', ''));
        $amount = (float)$this->request->post('amount', 0);
        $categoryId = (int)$this->request->post('category_id', 0);
        $expenseDate = (string)($this->request->post('expense_date') ?: date('Y-m-d'));
        $supplierId = $this->request->post('supplier_id') ? (int)$this->request->post('supplier_id') : null;
        $paymentMethod = \SoftNova\Services\PaymentMethodCatalog::normalize($this->request->post('payment_method', 'cash'));
        $receiptNumber = trim((string)$this->request->post('receipt_number', ''));
        $notes = trim((string)$this->request->post('notes', ''));
        $affectCash = $this->request->post('affect_cash') === '1';

        if ($description === '' || $amount <= 0) {
            $this->respond(false, 'Descripcion y monto son requeridos', '/app/caja');
            return;
        }

        $catSvc = new \SoftNova\Services\ExpenseCategoryService($this->db);
        $cat = $categoryId > 0 ? $catSvc->find($categoryId) : null;
        $category = $cat ? (string)$cat['name'] : 'General';

        $receipt = $this->storeExpenseReceipt();
        if ($receipt === false) {
            $this->respond(false, 'Comprobante inválido (JPG/PNG/WebP/PDF, máx. 5 MB)', '/app/caja');
            return;
        }

        try {
            $this->db->beginTransaction();
            $this->query(
                "INSERT INTO expenses
                    (description, amount, category, category_id, expense_date, supplier_id,
                     payment_method, receipt_number, receipt_path, receipt_original_name, receipt_mime,
                     notes, user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $description,
                    $amount,
                    $category,
                    $cat ? (int)$cat['id'] : null,
                    $expenseDate,
                    $supplierId,
                    $paymentMethod,
                    $receiptNumber ?: null,
                    $receipt['path'] ?? null,
                    $receipt['original'] ?? null,
                    $receipt['mime'] ?? null,
                    $notes ?: null,
                    $_SESSION['tenant_user_id'] ?? null,
                ]
            );
            $expenseId = (int)$this->db->lastInsertId();
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->respond(false, 'No se pudo registrar el gasto: ' . $e->getMessage(), '/app/caja');
            return;
        }

        try {
            (new \SoftNova\Services\AccountingService($this->db))->postExpense($expenseId, $affectCash);
        } catch (\Throwable $e) {
            error_log('Contabilidad gasto caja ' . $expenseId . ': ' . $e->getMessage());
        }

        if ($affectCash && \SoftNova\Services\PaymentMethodCatalog::affectsCash($paymentMethod)) {
            (new \SoftNova\Services\CashService($this->db))->registerMovement(
                $amount,
                'Gasto: ' . $description,
                'expense',
                'expense',
                $expenseId
            );
        }

        $this->respond(true, 'Gasto registrado: ' . $this->formatMoney($amount), '/app/caja');
    }

    /** @return array{path:?string,original:?string,mime:?string}|null|false */
    private function storeExpenseReceipt(): array|null|false
    {
        if (empty($_FILES['receipt_file']['tmp_name'])) {
            return null;
        }
        if (!is_uploaded_file($_FILES['receipt_file']['tmp_name'])) {
            return false;
        }
        if ((int)($_FILES['receipt_file']['size'] ?? 0) > 5242880) {
            return false;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['receipt_file']['tmp_name']) ?: '';
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        if (!isset($map[$mime])) {
            return false;
        }
        $tenantKey = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_SESSION['tenant_db_name'] ?? 'tenant')) ?: 'tenant';
        $dir = STORAGE_PATH . '/expenses/' . $tenantKey;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $filename = 'exp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $map[$mime];
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($_FILES['receipt_file']['tmp_name'], $dest)) {
            return false;
        }
        return [
            'path' => $tenantKey . '/' . $filename,
            'original' => (string)($_FILES['receipt_file']['name'] ?? $filename),
            'mime' => $mime,
        ];
    }
    
    private function closingPdf(): void
    {
        $id = (int)$this->request->get('id');
        $session = $this->query(
            "SELECT cs.*, u.name as user_name FROM cash_sessions cs JOIN users u ON cs.user_id = u.id WHERE cs.id = ?",
            [$id]
        )->fetch();
        
        if (!$session) {
            echo 'Sesion no encontrada';
            exit;
        }
        
        $movements = $this->query(
            "SELECT * FROM cash_movements WHERE cash_session_id = ? ORDER BY created_at DESC",
            [$id]
        )->fetchAll();
        
        $totals = $this->query(
            "SELECT COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as incomes,
                    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses
             FROM cash_movements WHERE cash_session_id = ?",
            [$id]
        )->fetch();
        
        $pdf = new \SoftNova\Services\PdfService($this->companySettings(), $this->getCurrency());
        $content = $pdf->generateCashClosing($session, $movements, $totals);
        $pdf->download($content, 'Cierre_Caja_' . $id . '.pdf');
    }
}
