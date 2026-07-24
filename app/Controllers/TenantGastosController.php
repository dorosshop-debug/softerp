<?php

namespace SoftNova\Controllers;

use SoftNova\Core\TenantController;
use SoftNova\Services\CashService;
use SoftNova\Services\AccountingService;

/**
 * Modulo Gastos del tenant (tabla expenses)
 */
class TenantGastosController extends TenantController
{
    private CashService $cash;
    private AccountingService $accounting;
    
    public function __construct()
    {
        parent::__construct();
        \SoftNova\Core\TenantMiddleware::authorize('gastos');
        $this->cash = new CashService($this->db);
        $this->accounting = new AccountingService($this->db);
    }
    
    public function index(): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'create' && $this->request->method() === 'POST') {
            \SoftNova\Core\TenantMiddleware::authorize('gastos', 'create');
            $this->create();
            return;
        }
        if ($action === 'edit' && $this->request->method() === 'POST') {
            \SoftNova\Core\TenantMiddleware::authorize('gastos', 'edit');
            $this->edit();
            return;
        }
        if ($action === 'delete' && $this->request->method() === 'POST') {
            \SoftNova\Core\TenantMiddleware::authorize('gastos', 'delete');
            $this->delete();
            return;
        }
        if ($action === 'export' && $this->request->method() === 'GET') {
            \SoftNova\Core\TenantMiddleware::authorize('gastos', 'export');
            $this->exportExpenses();
            return;
        }
        
        $total = (int)$this->query("SELECT COUNT(*) as c FROM expenses")->fetch()['c'];
        $pagination = $this->paginate($total);
        
        $expenses = $this->query(
            "SELECT e.*, s.name as supplier_name, u.name as user_name
             FROM expenses e
             LEFT JOIN suppliers s ON e.supplier_id = s.id
             LEFT JOIN users u ON e.user_id = u.id
             ORDER BY e.expense_date DESC, e.id DESC
             LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}"
        )->fetchAll();
        
        $monthTotal = (float)$this->query(
            "SELECT COALESCE(SUM(amount), 0) as t FROM expenses
             WHERE MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())"
        )->fetch()['t'];
        
        $suppliers = $this->query(
            "SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name"
        )->fetchAll();
        
        $this->view('tenant.gastos', $this->tenantViewData([
            'expenses' => $expenses,
            'monthTotal' => $monthTotal,
            'suppliers' => $suppliers,
            'pagination' => $pagination,
        ]));
    }
    
    private function create(): void
    {
        if (!$this->validateCsrfOrFail('/app/gastos')) {
            return;
        }
        
        $description = trim($this->request->post('description', ''));
        $amount = (float)$this->request->post('amount', 0);
        $category = trim($this->request->post('category', 'General'));
        $expenseDate = $this->request->post('expense_date') ?: date('Y-m-d');
        $supplierId = $this->request->post('supplier_id') ? (int)$this->request->post('supplier_id') : null;
        $paymentMethod = $this->request->post('payment_method', 'cash');
        $receiptNumber = trim($this->request->post('receipt_number', ''));
        $notes = trim($this->request->post('notes', ''));
        $affectCash = $this->request->post('affect_cash') === '1';
        
        if ($description === '' || $amount <= 0) {
            $this->respond(false, 'Descripcion y monto son requeridos', '/app/gastos');
            return;
        }
        
        try {
            $this->db->beginTransaction();
            $this->query(
                "INSERT INTO expenses
                    (description, amount, category, expense_date, supplier_id, payment_method, receipt_number, notes, user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $description,
                    $amount,
                    $category,
                    $expenseDate,
                    $supplierId,
                    $paymentMethod,
                    $receiptNumber ?: null,
                    $notes ?: null,
                    $_SESSION['tenant_user_id'],
                ]
            );

            $expenseId = (int)$this->db->lastInsertId();
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->respond(false, 'No se pudo registrar el gasto: ' . $e->getMessage(), '/app/gastos');
            return;
        }

        // Contabilización fuera de la transacción: un periodo cerrado u otro
        // problema contable NO debe impedir registrar el gasto.
        try {
            $this->accounting->postExpense($expenseId, $affectCash);
        } catch (\Throwable $e) {
            error_log('Contabilidad gasto ' . $expenseId . ': ' . $e->getMessage());
        }
        
        // Solo el efectivo afecta la caja física; tarjeta/transferencia salen del
        // banco en contabilidad, no del arqueo de caja.
        if ($affectCash && $paymentMethod === 'cash') {
            $this->cash->registerMovement(
                $amount,
                'Gasto: ' . $description,
                'expense',
                'expense',
                $expenseId
            );
        }
        
        $this->respond(true, 'Gasto registrado: ' . $this->formatMoney($amount), '/app/gastos');
    }
    
    private function edit(): void
    {
        if (!$this->validateCsrfOrFail('/app/gastos')) {
            return;
        }
        
        $id = (int)$this->request->post('id');
        $description = trim($this->request->post('description', ''));
        $amount = (float)$this->request->post('amount', 0);
        
        if ($id <= 0 || $description === '' || $amount <= 0) {
            $this->respond(false, 'Datos invalidos', '/app/gastos');
            return;
        }

        $posted = $this->query(
            "SELECT 1 FROM accounting_entries
             WHERE source_type = 'expense' AND source_id = ? AND status = 'posted' LIMIT 1",
            [$id]
        )->fetchColumn();
        if ($posted) {
            $this->respond(
                false,
                'El gasto ya está contabilizado. Elimínelo para generar una reversión y registre uno nuevo.',
                '/app/gastos'
            );
            return;
        }
        
        $this->query(
            "UPDATE expenses SET description = ?, amount = ?, category = ?, expense_date = ?,
                supplier_id = ?, payment_method = ?, receipt_number = ?, notes = ?
             WHERE id = ?",
            [
                $description,
                $amount,
                trim($this->request->post('category', 'General')),
                $this->request->post('expense_date') ?: date('Y-m-d'),
                $this->request->post('supplier_id') ? (int)$this->request->post('supplier_id') : null,
                $this->request->post('payment_method', 'cash'),
                trim($this->request->post('receipt_number', '')) ?: null,
                trim($this->request->post('notes', '')) ?: null,
                $id,
            ]
        );
        
        $this->respond(true, 'Gasto actualizado', '/app/gastos');
    }
    
    private function delete(): void
    {
        if (!$this->validateCsrfOrFail('/app/gastos')) {
            return;
        }
        
        $id = (int)$this->request->post('id');
        try {
            $expense = $this->query(
                "SELECT description FROM expenses WHERE id = ?",
                [$id]
            )->fetch();
            if (!$expense) {
                throw new \RuntimeException('Gasto no encontrado');
            }
            // Reversa contable antes de borrar; si falla (p.ej. periodo cerrado)
            // no debe impedir eliminar el gasto.
            $reversed = true;
            try {
                $this->accounting->reverseSource('expense', $id, 'Gasto eliminado');
            } catch (\Throwable $e) {
                $reversed = false;
                error_log('Reversa contable gasto ' . $id . ': ' . $e->getMessage());
            }
            $this->query("DELETE FROM expenses WHERE id = ?", [$id]);
            $cashReversed = $this->cash->reverseExpenseMovements($id, $expense['description']);
            $message = $reversed ? 'Gasto eliminado y asiento revertido' : 'Gasto eliminado (revisar asiento contable manualmente)';
            if ($cashReversed > 0) {
                $message .= '. Caja ajustada: +' . $this->formatMoney($cashReversed);
            }
            $this->respond(true, $message, '/app/gastos');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->respond(false, 'No se pudo eliminar: ' . $e->getMessage(), '/app/gastos');
        }
    }
    
    private function exportExpenses(): void
    {
        $rows = $this->query(
            "SELECT e.expense_date, e.description, e.category, e.amount, e.payment_method,
                    COALESCE(s.name, '') as supplier_name, e.receipt_number
             FROM expenses e
             LEFT JOIN suppliers s ON e.supplier_id = s.id
             ORDER BY e.expense_date DESC"
        )->fetchAll();
        
        $csvRows = [];
        foreach ($rows as $r) {
            $csvRows[] = [
                $r['expense_date'], $r['description'], $r['category'], $r['amount'],
                $r['payment_method'], $r['supplier_name'], $r['receipt_number'],
            ];
        }
        
        $this->exportCsv(
            'gastos_' . date('Ymd_His') . '.csv',
            ['Fecha', 'Descripcion', 'Categoria', 'Monto', 'Metodo', 'Proveedor', 'Comprobante'],
            $csvRows
        );
    }
}
