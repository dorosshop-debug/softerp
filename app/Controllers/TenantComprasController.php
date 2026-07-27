<?php

namespace SoftNova\Controllers;

use SoftNova\Core\TenantController;
use SoftNova\Core\TenantMiddleware;
use SoftNova\Services\AccountingService;
use SoftNova\Services\CashService;
use SoftNova\Services\StockService;
use SoftNova\Services\TenantOpsSchema;

/**
 * Compras a proveedores → cargan inventario + asiento contable.
 */
class TenantComprasController extends TenantController
{
    private StockService $stock;
    private AccountingService $accounting;
    private CashService $cash;

    public function __construct()
    {
        parent::__construct();
        TenantMiddleware::authorize('compras');
        TenantOpsSchema::ensure($this->db);
        $this->stock = new StockService($this->db);
        $this->accounting = new AccountingService($this->db);
        $this->cash = new CashService($this->db);
    }

    public function index(): void
    {
        $action = $this->request->get('action');

        if ($action === 'create' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('compras', 'create');
            $this->create();
            return;
        }
        if ($action === 'detail') {
            $this->detail();
            return;
        }
        if ($action === 'cancel' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('compras', 'delete');
            $this->cancel();
            return;
        }
        if ($action === 'products') {
            $this->searchProducts();
            return;
        }

        $filters = $this->listFilters([
            'date' => 'p.purchase_date',
            'number' => 'p.purchase_number',
            'supplier' => 's.name',
            'total' => 'p.total',
        ], 'date', 'desc');

        $where = ["p.status != 'cancelled' OR 1=1"]; // show all including cancelled
        $where = ['1=1'];
        $params = [];
        if ($filters['q'] !== '') {
            $where[] = '(p.purchase_number LIKE ? OR s.name LIKE ? OR p.notes LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        if ($filters['from'] !== '') {
            $where[] = 'DATE(p.purchase_date) >= ?';
            $params[] = $filters['from'];
        }
        if ($filters['to'] !== '') {
            $where[] = 'DATE(p.purchase_date) <= ?';
            $params[] = $filters['to'];
        }
        if ($filters['status'] !== '') {
            $where[] = 'p.status = ?';
            $params[] = $filters['status'];
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $total = (int)$this->query(
            "SELECT COUNT(*) FROM purchases p LEFT JOIN suppliers s ON s.id = p.supplier_id {$whereSql}",
            $params
        )->fetchColumn();
        $pagination = $this->paginate($total);

        $purchases = $this->query(
            "SELECT p.*, s.name supplier_name, u.name user_name
             FROM purchases p
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN users u ON u.id = p.user_id
             {$whereSql}
             ORDER BY {$filters['orderSql']}, p.id DESC
             LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
            $params
        )->fetchAll();

        $monthTotal = (float)$this->query(
            "SELECT COALESCE(SUM(total),0) FROM purchases
             WHERE status = 'completed'
               AND MONTH(purchase_date) = MONTH(CURDATE())
               AND YEAR(purchase_date) = YEAR(CURDATE())"
        )->fetchColumn();

        $suppliers = $this->query(
            "SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name"
        )->fetchAll();

        $this->view('tenant.compras', $this->tenantViewData([
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'monthTotal' => $monthTotal,
            'pagination' => $pagination,
            'filters' => $filters,
        ]));
    }

    private function searchProducts(): void
    {
        $q = trim((string)$this->request->get('q', ''));
        $rows = $this->query(
            "SELECT id, code, name, purchase_price, stock, product_type
             FROM products WHERE status = 'active' AND product_type = 'product'
               AND (? = '' OR name LIKE ? OR code LIKE ?)
             ORDER BY name ASC LIMIT 30",
            [$q, '%' . $q . '%', '%' . $q . '%']
        )->fetchAll();
        $this->json(['products' => $rows]);
    }

    private function create(): void
    {
        if (!$this->validateCsrfOrFail('/app/compras')) {
            return;
        }

        $supplierId = $this->request->post('supplier_id') ? (int)$this->request->post('supplier_id') : null;
        $purchaseDate = trim((string)$this->request->post('purchase_date', date('Y-m-d')));
        $paymentMethod = (string)$this->request->post('payment_method', 'cash');
        $paymentStatus = (string)$this->request->post('payment_status', 'paid');
        $notes = trim((string)$this->request->post('notes', ''));
        $items = $this->request->post('items', []);
        $affectCash = $this->request->post('affect_cash') === '1';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
            $purchaseDate = date('Y-m-d');
        }
        if (!in_array($paymentStatus, ['paid', 'pending', 'partial'], true)) {
            $paymentStatus = 'paid';
        }
        $allowedPay = array_keys(\SoftNova\Core\payment_methods(true));
        if (!in_array($paymentMethod, $allowedPay, true)) {
            $paymentMethod = 'cash';
        }

        if (empty($items) || !is_array($items)) {
            $this->respond(false, 'Agregue al menos un producto', '/app/compras');
            return;
        }

        $validItems = [];
        $subtotal = 0.0;
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 0);
            $unitCost = (float)($item['unit_cost'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            $product = $this->query(
                "SELECT id, name, purchase_price, product_type FROM products WHERE id = ? AND status = 'active'",
                [$productId]
            )->fetch();
            if (!$product || ($product['product_type'] ?? '') === 'service') {
                continue;
            }
            if ($unitCost <= 0) {
                $unitCost = (float)$product['purchase_price'];
            }
            $line = round($unitCost * $qty, 2);
            $subtotal += $line;
            $validItems[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'subtotal' => $line,
            ];
        }

        if (empty($validItems)) {
            $this->respond(false, 'No hay ítems válidos', '/app/compras');
            return;
        }

        $tax = 0.0;
        $total = round($subtotal + $tax, 2);
        $number = 'COM-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $purchaseDateTime = $purchaseDate . ' ' . date('H:i:s');

        try {
            $this->db->beginTransaction();
            $this->query(
                "INSERT INTO purchases
                    (purchase_number, supplier_id, user_id, purchase_date, subtotal, tax, total,
                     payment_method, payment_status, notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')",
                [
                    $number, $supplierId, $_SESSION['tenant_user_id'] ?? null,
                    $purchaseDateTime, $subtotal, $tax, $total,
                    $paymentMethod, $paymentStatus, $notes ?: null,
                ]
            );
            $purchaseId = (int)$this->db->lastInsertId();

            foreach ($validItems as $vi) {
                $this->query(
                    "INSERT INTO purchase_items (purchase_id, product_id, product_name, quantity, unit_cost, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$purchaseId, $vi['product_id'], $vi['product_name'], $vi['quantity'], $vi['unit_cost'], $vi['subtotal']]
                );
                // Actualizar costo promedio/simple al último costo de compra
                $this->query(
                    "UPDATE products SET purchase_price = ?, source_channel = IF(source_channel = 'manual' OR source_channel = '', 'purchase', source_channel)
                     WHERE id = ?",
                    [$vi['unit_cost'], $vi['product_id']]
                );
                $this->stock->increase(
                    $vi['product_id'],
                    $vi['quantity'],
                    'purchase',
                    $purchaseId,
                    'Compra ' . $number,
                    'in',
                    $purchaseDateTime
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->respond(false, 'Error al registrar compra: ' . $e->getMessage(), '/app/compras');
            return;
        }

        try {
            $this->accounting->postPurchase($purchaseId);
        } catch (\Throwable $e) {
            error_log('Contabilidad compra ' . $purchaseId . ': ' . $e->getMessage());
        }

        if ($paymentStatus === 'paid' && $affectCash && $paymentMethod === 'cash' && $total > 0) {
            $this->cash->registerMovement($total, 'Compra: ' . $number, 'expense', 'purchase', $purchaseId);
        }

        $this->respond(true, 'Compra registrada: ' . $number, '/app/compras');
    }

    private function detail(): void
    {
        $id = (int)$this->request->get('id');
        $purchase = $this->query(
            "SELECT p.*, s.name supplier_name FROM purchases p
             LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ?",
            [$id]
        )->fetch();
        if (!$purchase) {
            $this->json(['success' => false, 'message' => 'Compra no encontrada']);
            return;
        }
        $items = $this->query(
            "SELECT * FROM purchase_items WHERE purchase_id = ?",
            [$id]
        )->fetchAll();
        $movements = $this->query(
            "SELECT sm.*, pr.name product_name FROM stock_movements sm
             LEFT JOIN products pr ON pr.id = sm.product_id
             WHERE sm.reference_type = 'purchase' AND sm.reference_id = ?
             ORDER BY COALESCE(sm.movement_date, sm.created_at)",
            [$id]
        )->fetchAll();
        $this->json(['success' => true, 'purchase' => $purchase, 'items' => $items, 'movements' => $movements]);
    }

    private function cancel(): void
    {
        if (!$this->validateCsrfOrFail('/app/compras')) {
            return;
        }
        $id = (int)$this->request->post('id');
        $purchase = $this->query(
            "SELECT * FROM purchases WHERE id = ? AND status = 'completed'",
            [$id]
        )->fetch();
        if (!$purchase) {
            $this->respond(false, 'Compra no encontrada o ya cancelada', '/app/compras');
            return;
        }

        try {
            $this->db->beginTransaction();
            $items = $this->query(
                "SELECT * FROM purchase_items WHERE purchase_id = ?",
                [$id]
            )->fetchAll();
            foreach ($items as $item) {
                // Revertir stock (sale-like decrease)
                $this->stock->decrease(
                    (int)$item['product_id'],
                    (int)$item['quantity'],
                    'purchase_cancel',
                    $id,
                    'Cancelación compra ' . $purchase['purchase_number']
                );
            }
            $this->query("UPDATE purchases SET status = 'cancelled' WHERE id = ?", [$id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->respond(false, 'No se pudo cancelar: ' . $e->getMessage(), '/app/compras');
            return;
        }

        try {
            $this->accounting->reversePurchase($id, 'Compra cancelada');
        } catch (\Throwable $e) {
            error_log('Reversa contable compra ' . $id . ': ' . $e->getMessage());
        }

        $this->respond(true, 'Compra cancelada y stock revertido', '/app/compras');
    }
}
