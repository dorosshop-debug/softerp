<?php

namespace SoftNova\Controllers;

use SoftNova\Core\TenantController;
use SoftNova\Core\TenantMiddleware;
use SoftNova\Services\SaleService;
use SoftNova\Services\CashService;
use SoftNova\Services\ReceivableService;

class TenantVentasController extends TenantController
{
    private SaleService $sales;
    private CashService $cash;
    private ReceivableService $receivables;
    
    public function __construct()
    {
        parent::__construct();
        $this->sales = new SaleService($this->db);
        $this->cash = new CashService($this->db);
        $this->receivables = new ReceivableService($this->db);
    }
    
    public function index(): void
    {
        TenantMiddleware::authorize('ventas');
        
        $action = $this->request->get('action');
        
        if ($action === 'create' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('ventas', 'create');
            $this->createSale();
            return;
        }
        if ($action === 'detail' && $this->request->method() === 'GET') {
            $this->detail();
            return;
        }
        if ($action === 'cancel' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('ventas', 'delete');
            $this->cancelSale();
            return;
        }
        if ($action === 'payment' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('ventas', 'create');
            $this->registerPayment();
            return;
        }
        if ($action === 'pdf' && $this->request->method() === 'GET') {
            $this->invoicePdf();
            return;
        }
        if ($action === 'export' && $this->request->method() === 'GET') {
            TenantMiddleware::authorize('ventas', 'export');
            $this->exportSales();
            return;
        }
        
        $total = (int)$this->query("SELECT COUNT(*) as c FROM sales WHERE status != 'cancelled'")->fetch()['c'];
        $pagination = $this->paginate($total);
        
        $sales = $this->query(
            "SELECT s.*, c.name as customer_name, u.name as user_name,
                    COALESCE(p.paid_amount, 0) as paid_amount
             FROM sales s
             LEFT JOIN customers c ON s.customer_id = c.id
             LEFT JOIN users u ON s.user_id = u.id
             LEFT JOIN (
                SELECT sale_id, COALESCE(SUM(amount), 0) as paid_amount
                FROM sale_payments
                GROUP BY sale_id
             ) p ON p.sale_id = s.id
             WHERE s.status != 'cancelled'
             ORDER BY s.created_at DESC
             LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}"
        )->fetchAll();
        
        $todayStats = $this->query(
            "SELECT COALESCE(SUM(total), 0) as t, COUNT(*) as c
             FROM sales
             WHERE DATE(sale_date) = CURDATE() AND status != 'cancelled'"
        )->fetch();
        $todayTotal = (float)($todayStats['t'] ?? 0);
        $todayCount = (int)($todayStats['c'] ?? 0);
        
        $products = $this->query(
            "SELECT id, name, sale_price, stock FROM products
             WHERE status = 'active'
             ORDER BY name"
        )->fetchAll();
        
        $customers = $this->query(
            "SELECT id, name, first_name, last_name FROM customers WHERE status = 'active' ORDER BY name"
        )->fetchAll();
        
        $this->view('tenant.ventas', $this->tenantViewData([
            'sales' => $sales,
            'todayTotal' => $todayTotal,
            'todayCount' => $todayCount,
            'products' => $products,
            'customers' => $customers,
            'pagination' => $pagination,
        ]));
    }
    
    public function detail(): void
    {
        $id = (int)$this->request->get('id');
        $sale = $this->query(
            "SELECT s.*, c.name as customer_name, c.first_name, c.last_name, u.name as user_name
             FROM sales s
             LEFT JOIN customers c ON s.customer_id = c.id
             LEFT JOIN users u ON s.user_id = u.id
             WHERE s.id = ?",
            [$id]
        )->fetch();
        
        if (!$sale) {
            $this->json(['error' => 'Venta no encontrada']);
            return;
        }
        
        $items = $this->query("SELECT * FROM sale_items WHERE sale_id = ?", [$id])->fetchAll();
        $payments = $this->query(
            "SELECT * FROM sale_payments WHERE sale_id = ? ORDER BY payment_date DESC",
            [$id]
        )->fetchAll();
        
        $this->json(['sale' => $sale, 'items' => $items, 'payments' => $payments]);
    }
    
    private function createSale(): void
    {
        if (!$this->validateCsrfOrFail('/app/ventas')) {
            return;
        }
        
        $customerId = $this->request->post('customer_id') ? (int)$this->request->post('customer_id') : null;
        $paymentMethod = $this->request->post('payment_method', 'cash');
        $paymentType = $this->request->post('payment_type', 'full');
        $initialPayment = (float)$this->request->post('initial_payment', 0);
        $items = $this->request->post('items', []);
        $notes = $this->request->post('notes', '');
        
        if (empty($items) || !is_array($items)) {
            $this->respond(false, 'Debe agregar al menos un producto', '/app/ventas');
            return;
        }
        
        $prefix = $this->getSetting('invoice_prefix', 'FAC-');
        $invoiceNumber = $prefix . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        
        $subtotal = 0;
        $validItems = [];
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }
            $product = $this->query(
                "SELECT * FROM products WHERE id = ? AND status = 'active'",
                [$productId]
            )->fetch();
            if (!$product) {
                continue;
            }
            $up = (float)$product['sale_price'];
            $is = $up * $quantity;
            $subtotal += $is;
            $validItems[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'quantity' => $quantity,
                'unit_price' => $up,
                'subtotal' => $is,
                'product_type' => $product['product_type'] ?? 'product',
            ];
        }
        
        if (empty($validItems)) {
            $this->respond(false, 'No se encontraron productos validos', '/app/ventas');
            return;
        }
        
        $taxRate = (float)$this->getSetting('tax_rate', '0');
        $tax = $subtotal * ($taxRate / 100);
        $total = $subtotal + $tax;
        
        $paymentStatus = $paymentType === 'credit' ? 'pending' : 'paid';
        $initialPayment = max(0, $initialPayment);
        if ($paymentType === 'credit') {
            $initialPayment = min($initialPayment, $total);
            if ($initialPayment >= $total && $total > 0) {
                $paymentStatus = 'paid';
            }
        }
        
        try {
            $this->db->beginTransaction();
            
            $this->query(
                "INSERT INTO sales
                    (invoice_number, customer_id, user_id, sale_date, subtotal, tax, discount, total,
                     payment_method, payment_status, notes, status)
                 VALUES (?, ?, ?, NOW(), ?, ?, 0, ?, ?, ?, ?, ?)",
                [
                    $invoiceNumber,
                    $customerId,
                    $_SESSION['tenant_user_id'],
                    $subtotal,
                    $tax,
                    $total,
                    $paymentMethod,
                    $paymentStatus,
                    $notes,
                    $paymentStatus === 'paid' ? 'completed' : 'pending',
                ]
            );
            $saleId = (int)$this->db->lastInsertId();
            
            foreach ($validItems as $vi) {
                $this->query(
                    "INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$saleId, $vi['product_id'], $vi['product_name'], $vi['quantity'], $vi['unit_price'], $vi['subtotal']]
                );
                $this->sales->stock()->decrease(
                    $vi['product_id'],
                    $vi['quantity'],
                    'sale',
                    $saleId,
                    'Venta: ' . $invoiceNumber
                );
            }
            
            $cashAmount = 0.0;
            $paidNow = $paymentType === 'credit' ? $initialPayment : $total;
            if ($paidNow > 0) {
                $this->query(
                    "INSERT INTO sale_payments (sale_id, amount, payment_method, notes, user_id)
                     VALUES (?, ?, ?, ?, ?)",
                    [
                        $saleId,
                        $paidNow,
                        $paymentMethod,
                        $paymentType === 'credit' ? 'Pago inicial - credito' : 'Pago completo',
                        $_SESSION['tenant_user_id'],
                    ]
                );
                $cashAmount = $paidNow;
                if ($paymentType === 'credit' && $paidNow < $total) {
                    $this->query("UPDATE sales SET payment_status = 'partial' WHERE id = ?", [$saleId]);
                }
            }
            
            $this->db->commit();
            
            if ($cashAmount > 0) {
                $label = ($paymentType === 'credit' && $paymentStatus !== 'paid')
                    ? 'Abono inicial: ' . $invoiceNumber
                    : 'Venta: ' . $invoiceNumber;
                $this->cash->registerIncome($cashAmount, $label, 'sale', $saleId);
            }
            
            // Credito / pago parcial: crear tarea de cuentas por cobrar
            if ($paymentType === 'credit') {
                $paidForRx = min($initialPayment, $total);
                $finalStatus = $this->query("SELECT payment_status FROM sales WHERE id = ?", [$saleId])->fetch()['payment_status'] ?? $paymentStatus;
                if ($finalStatus !== 'paid') {
                    $this->receivables->upsertFromSale(
                        $saleId,
                        $customerId,
                        $invoiceNumber,
                        $total,
                        $paidForRx,
                        $notes ?: null
                    );
                }
            }
            
            $msg = $paymentType === 'credit' && $paymentStatus !== 'paid'
                ? 'Venta a credito creada: ' . $invoiceNumber . ' (Pendiente: ' . $this->formatMoney($total - $initialPayment) . ')'
                : 'Venta creada: ' . $invoiceNumber;
            $this->respond(true, $msg, '/app/ventas');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->respond(false, 'Error: ' . $e->getMessage(), '/app/ventas');
        }
    }
    
    private function registerPayment(): void
    {
        if (!$this->validateCsrfOrFail('/app/ventas')) {
            return;
        }
        
        $saleId = (int)$this->request->post('sale_id');
        $amount = (float)$this->request->post('amount');
        $method = $this->request->post('payment_method', 'cash');
        $notes = $this->request->post('notes', 'Abono');
        
        if ($amount <= 0) {
            $this->respond(false, 'El monto debe ser mayor a cero', '/app/ventas');
            return;
        }
        
        try {
            $this->db->beginTransaction();
            
            $sale = $this->query(
                "SELECT * FROM sales WHERE id = ? AND payment_status IN ('pending', 'partial') FOR UPDATE",
                [$saleId]
            )->fetch();
            
            if (!$sale) {
                $this->db->rollBack();
                $this->respond(false, 'Venta no encontrada o ya esta pagada', '/app/ventas');
                return;
            }
            
            $alreadyPaid = (float)$this->query(
                "SELECT COALESCE(SUM(amount), 0) as t FROM sale_payments WHERE sale_id = ?",
                [$saleId]
            )->fetch()['t'];
            $balance = max(0, round((float)$sale['total'] - $alreadyPaid, 2));
            
            if ($balance <= 0) {
                $this->db->rollBack();
                $this->respond(false, 'Esta venta ya no tiene saldo pendiente', '/app/ventas');
                return;
            }
            
            if ($amount > $balance + 0.009) {
                $this->db->rollBack();
                $this->respond(false, 'El abono no puede superar el saldo pendiente (' . $this->formatMoney($balance) . ')', '/app/ventas');
                return;
            }
            
            $amount = min($amount, $balance);
            
            $this->query(
                "INSERT INTO sale_payments (sale_id, amount, payment_date, payment_method, notes, user_id)
                 VALUES (?, ?, NOW(), ?, ?, ?)",
                [$saleId, $amount, $method, $notes, $_SESSION['tenant_user_id']]
            );
            
            $totalPaid = round($alreadyPaid + $amount, 2);
            $remaining = round((float)$sale['total'] - $totalPaid, 2);
            
            if ($remaining <= 0.009) {
                $this->query("UPDATE sales SET payment_status = 'paid', status = 'completed' WHERE id = ?", [$saleId]);
                $remaining = 0;
            } else {
                $this->query("UPDATE sales SET payment_status = 'partial' WHERE id = ?", [$saleId]);
            }
            
            $this->db->commit();
            
            $this->cash->registerIncome($amount, 'Abono: ' . $sale['invoice_number'], 'sale_payment', $saleId);
            $this->receivables->applyPayment($saleId, $totalPaid, (float)$sale['total']);
            
            $this->respond(true, 'Abono registrado. Pendiente: ' . $this->formatMoney(max(0, $remaining)), '/app/ventas');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->respond(false, 'Error: ' . $e->getMessage(), '/app/ventas');
        }
    }
    
    private function cancelSale(): void
    {
        if (!$this->validateCsrfOrFail('/app/ventas')) {
            return;
        }
        
        $id = (int)$this->request->post('id');
        
        try {
            $result = $this->sales->cancelSale($id);
            $this->receivables->cancelBySale($id);
            $msg = 'Venta eliminada. Stock devuelto.';
            if ($result['reversed_cash'] > 0) {
                $msg .= ' Caja ajustada: -' . $this->formatMoney($result['reversed_cash']);
            }
            $this->respond(true, $msg, '/app/ventas');
        } catch (\Exception $e) {
            $this->respond(false, 'Error: ' . $e->getMessage(), '/app/ventas');
        }
    }
    
    private function invoicePdf(): void
    {
        $id = (int)$this->request->get('id');
        $sale = $this->query(
            "SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = ?",
            [$id]
        )->fetch();
        
        if (!$sale) {
            echo 'Venta no encontrada';
            exit;
        }
        
        $items = $this->query("SELECT * FROM sale_items WHERE sale_id = ?", [$id])->fetchAll();
        $payments = $this->query(
            "SELECT * FROM sale_payments WHERE sale_id = ? ORDER BY payment_date DESC",
            [$id]
        )->fetchAll();
        
        $pdf = new \SoftNova\Services\PdfService($this->companySettings(), $this->getCurrency());
        $content = $pdf->generateInvoice($sale, $items, $payments);
        $pdf->download($content, 'Factura_' . ($sale['invoice_number'] ?? $id) . '.pdf');
    }
    
    private function exportSales(): void
    {
        $rows = $this->query(
            "SELECT s.invoice_number, COALESCE(c.name, 'General') as customer_name, s.sale_date,
                    s.subtotal, s.tax, s.total, s.payment_method, s.payment_status, s.status
             FROM sales s
             LEFT JOIN customers c ON s.customer_id = c.id
             ORDER BY s.created_at DESC"
        )->fetchAll();
        
        $csvRows = [];
        foreach ($rows as $r) {
            $csvRows[] = [
                $r['invoice_number'],
                $r['customer_name'],
                $r['sale_date'],
                $r['subtotal'],
                $r['tax'],
                $r['total'],
                $r['payment_method'],
                $r['payment_status'],
                $r['status'],
            ];
        }
        
        $this->exportCsv(
            'ventas_' . date('Ymd_His') . '.csv',
            ['Factura', 'Cliente', 'Fecha', 'Subtotal', 'Impuesto', 'Total', 'Metodo', 'Pago', 'Estado'],
            $csvRows
        );
    }
}
