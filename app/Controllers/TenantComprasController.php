<?php

namespace SoftNova\Controllers;

use SoftNova\Core\TenantController;
use SoftNova\Core\TenantMiddleware;
use SoftNova\Services\AccountingService;
use SoftNova\Services\PurchasingService;

/**
 * Módulo Compras: OC + recepción/factura proveedor + IVA descontable.
 */
class TenantComprasController extends TenantController
{
    private PurchasingService $purchasing;
    private AccountingService $accounting;

    public function __construct()
    {
        parent::__construct();
        TenantMiddleware::authorize('compras');
        $this->purchasing = new PurchasingService($this->db);
        $this->accounting = new AccountingService($this->db);
    }

    public function index(): void
    {
        $action = (string)$this->request->get('action', '');

        if ($action === 'create' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('compras', 'create');
            $this->create();
            return;
        }
        if ($action === 'receive' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('compras', 'edit');
            $this->receive();
            return;
        }
        if ($action === 'cancel' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('compras', 'delete');
            $this->cancel();
            return;
        }
        if ($action === 'detail' && $this->request->method() === 'GET') {
            $this->detail();
            return;
        }

        $status = (string)$this->request->get('status', '');
        $total = $this->purchasing->countOrders($status);
        $pagination = $this->paginate($total);
        $orders = $this->purchasing->listOrders($pagination['perPage'], $pagination['offset'], $status);

        $products = $this->query(
            "SELECT id, code, name, purchase_price, stock
             FROM products
             WHERE status = 'active' AND COALESCE(product_type, 'product') = 'product'
             ORDER BY name"
        )->fetchAll();
        $suppliers = $this->query(
            "SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name"
        )->fetchAll();

        $openTotal = (float)$this->query(
            "SELECT COALESCE(SUM(total),0) FROM purchase_orders WHERE status IN ('draft','ordered')"
        )->fetchColumn();
        $monthReceived = (float)$this->query(
            "SELECT COALESCE(SUM(total),0) FROM purchase_orders
             WHERE status = 'received'
               AND MONTH(COALESCE(warehouse_date, order_date)) = MONTH(CURDATE())
               AND YEAR(COALESCE(warehouse_date, order_date)) = YEAR(CURDATE())"
        )->fetchColumn();

        $this->view('tenant.compras', $this->tenantViewData([
            'orders' => $orders,
            'products' => $products,
            'suppliers' => $suppliers,
            'pagination' => $pagination,
            'statusFilter' => $status,
            'openTotal' => $openTotal,
            'monthReceived' => $monthReceived,
        ]));
    }

    private function detail(): void
    {
        $order = $this->purchasing->getOrder((int)$this->request->get('id', 0));
        if (!$order) {
            $this->json(['success' => false, 'message' => 'Orden no encontrada']);
            return;
        }
        $this->json(['success' => true, 'order' => $order]);
    }

    private function create(): void
    {
        if (!$this->validateCsrfOrFail('/app/compras')) {
            return;
        }

        $supplierId = (int)$this->request->post('supplier_id', 0) ?: null;
        $orderDate = (string)$this->request->post('order_date', date('Y-m-d'));
        $warehouseDate = trim((string)$this->request->post('warehouse_date', ''));
        $expectedDate = trim((string)$this->request->post('expected_date', ''));
        $notes = trim((string)$this->request->post('notes', ''));
        $receiveNow = $this->request->post('receive_now') === '1';
        $earlyDiscount = (float)$this->request->post('early_payment_discount_pct', 0);
        $dueDate = trim((string)$this->request->post('due_date', ''));
        $invoiceDate = trim((string)$this->request->post('invoice_date', ''));
        $invoiceNumberCreate = trim((string)$this->request->post('invoice_number', ''));

        $productIds = (array)$this->request->post('product_id', []);
        $quantities = (array)$this->request->post('quantity', []);
        $unitCosts = (array)$this->request->post('unit_cost', []);
        $taxRates = (array)$this->request->post('tax_rate', []);

        $items = [];
        $count = max(count($productIds), count($quantities), count($unitCosts));
        for ($i = 0; $i < $count; $i++) {
            $pid = (int)($productIds[$i] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $items[] = [
                'product_id' => $pid,
                'quantity' => (int)($quantities[$i] ?? 0),
                'unit_cost' => (float)($unitCosts[$i] ?? 0),
                'tax_rate' => (float)($taxRates[$i] ?? 19),
            ];
        }

        try {
            $orderId = $this->purchasing->createOrder(
                $supplierId,
                $orderDate !== '' ? $orderDate : date('Y-m-d'),
                $warehouseDate !== '' ? $warehouseDate : null,
                $expectedDate !== '' ? $expectedDate : null,
                $notes,
                $items,
                'ordered',
                $earlyDiscount,
                $dueDate !== '' ? $dueDate : null,
                $invoiceDate !== '' ? $invoiceDate : null,
                $invoiceNumberCreate !== '' ? $invoiceNumberCreate : null
            );

            if ($receiveNow) {
                $this->doReceive(
                    $orderId,
                    $warehouseDate !== '' ? $warehouseDate : $orderDate,
                    trim((string)$this->request->post('invoice_number', '')),
                    (string)$this->request->post('payment_mode', 'payable'),
                    $notes
                );
                $this->respond(true, 'Orden creada y mercancía recibida/contabilizada', '/app/compras');
                return;
            }

            $order = $this->purchasing->getOrder($orderId);
            $this->respond(
                true,
                'Orden creada: ' . ($order['order_number'] ?? ('#' . $orderId)),
                '/app/compras'
            );
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), '/app/compras');
        }
    }

    private function receive(): void
    {
        if (!$this->validateCsrfOrFail('/app/compras')) {
            return;
        }
        try {
            $this->doReceive(
                (int)$this->request->post('id', 0),
                (string)$this->request->post('warehouse_date', date('Y-m-d')),
                trim((string)$this->request->post('invoice_number', '')),
                (string)$this->request->post('payment_mode', 'payable'),
                trim((string)$this->request->post('notes', ''))
            );
            $this->respond(true, 'Mercancía recibida, inventario y contabilidad actualizados', '/app/compras');
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), '/app/compras');
        }
    }

    private function doReceive(
        int $orderId,
        string $warehouseDate,
        string $invoiceNumber,
        string $paymentMode,
        string $notes
    ): void {
        $this->purchasing->receiveOrder($orderId, $warehouseDate, $invoiceNumber, $paymentMode, $notes);
        try {
            $this->accounting->postPurchaseOrder($orderId);
        } catch (\Throwable $e) {
            error_log('Contabilidad OC ' . $orderId . ': ' . $e->getMessage());
        }
    }

    private function cancel(): void
    {
        if (!$this->validateCsrfOrFail('/app/compras')) {
            return;
        }
        try {
            $this->purchasing->cancelOrder(
                (int)$this->request->post('id', 0),
                trim((string)$this->request->post('reason', ''))
            );
            $this->respond(true, 'Orden cancelada', '/app/compras');
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), '/app/compras');
        }
    }
}
