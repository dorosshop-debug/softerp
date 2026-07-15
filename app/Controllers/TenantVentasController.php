<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

/**
 * Controlador del módulo Ventas para tenants
 * Creación, listado y detalle de ventas
 */
class TenantVentasController extends Controller
{
    private \PDO $db;
    
    public function __construct()
    {
        parent::__construct();
        TenantMiddleware::auth();
        $this->db = TenantMiddleware::getDb();
    }
    
    private function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    private function getCurrency(): array
    {
        $c = $this->query("SELECT setting_value FROM settings WHERE setting_key = 'currency'")->fetch();
        $code = $c['setting_value'] ?? 'COP';
        $currencies = [
            'COP' => ['symbol' => '$', 'decimals' => 0, 'thousands' => '.', 'decimal' => ','],
            'USD' => ['symbol' => 'US$', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
            'EUR' => ['symbol' => '€', 'decimals' => 2, 'thousands' => '.', 'decimal' => ','],
        ];
        return $currencies[$code] ?? $currencies['COP'];
    }
    
    private function fmt(float $amount): string
    {
        $c = $this->getCurrency();
        return $c['symbol'] . ' ' . number_format($amount, $c['decimals'], $c['decimal'], $c['thousands']);
    }
    
    /**
     * Listado de ventas
     */
    public function index(): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'create' && $this->request->method() === 'POST') {
            $this->createSale();
            return;
        }
        
        // Ventas del día
        $sales = $this->query(
            "SELECT s.*, c.name as customer_name, u.name as user_name
             FROM sales s
             LEFT JOIN customers c ON s.customer_id = c.id
             LEFT JOIN users u ON s.user_id = u.id
             ORDER BY s.created_at DESC
             LIMIT 50"
        )->fetchAll();
        
        // Totales del día
        $todayTotal = $this->query(
            "SELECT COALESCE(SUM(total), 0) as t FROM sales WHERE DATE(sale_date) = CURDATE() AND status = 'completed'"
        )->fetch()['t'];
        
        // Productos para el formulario de venta
        $products = $this->query(
            "SELECT id, name, sale_price, stock FROM products WHERE status = 'active' ORDER BY name"
        )->fetchAll();
        
        // Clientes para el formulario
        $customers = $this->query(
            "SELECT id, name FROM customers WHERE status = 'active' ORDER BY name"
        )->fetchAll();
        
        $this->view('tenant.ventas', [
            'sales' => $sales,
            'todayTotal' => $todayTotal,
            'products' => $products,
            'customers' => $customers,
            'currency' => $this->getCurrency(),
            'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa',
            'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
        ]);
    }
    
    /**
     * Crear una nueva venta
     */
    private function createSale(): void
    {
        if (!$this->validateCsrfOrFail('/app/ventas')) {
            return;
        }
        
        $customerId = $this->request->post('customer_id') ? (int)$this->request->post('customer_id') : null;
        $paymentMethod = $this->request->post('payment_method', 'cash');
        $items = $this->request->post('items', []);
        $notes = $this->request->post('notes', '');
        
        if (empty($items) || !is_array($items)) {
            $this->respond(false, 'Debe agregar al menos un producto', '/app/ventas');
            return;
        }
        
        // Obtener prefijo de factura
        $prefix = $this->query("SELECT setting_value FROM settings WHERE setting_key = 'invoice_prefix'")->fetch();
        $prefix = $prefix['setting_value'] ?? 'FAC-';
        $invoiceNumber = $prefix . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        
        $subtotal = 0;
        $validItems = [];
        
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            
            if ($productId <= 0 || $quantity <= 0) continue;
            
            $product = $this->query("SELECT * FROM products WHERE id = ?", [$productId])->fetch();
            if (!$product) continue;
            
            $unitPrice = (float)$product['sale_price'];
            $itemSubtotal = $unitPrice * $quantity;
            $subtotal += $itemSubtotal;
            
            $validItems[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $itemSubtotal,
            ];
        }
        
        if (empty($validItems)) {
            $this->respond(false, 'No se encontraron productos válidos', '/app/ventas');
            return;
        }
        
        $taxRate = (float)($this->query("SELECT setting_value FROM settings WHERE setting_key = 'tax_rate'")->fetch()['setting_value'] ?? 0);
        $tax = $subtotal * ($taxRate / 100);
        $total = $subtotal + $tax;
        $userId = $_SESSION['tenant_user_id'];
        
        try {
            $this->db->beginTransaction();
            
            $this->query(
                "INSERT INTO sales (invoice_number, customer_id, user_id, sale_date, subtotal, tax, discount, total, payment_method, payment_status, notes, status) 
                 VALUES (?, ?, ?, NOW(), ?, ?, 0, ?, ?, 'paid', ?, 'completed')",
                [$invoiceNumber, $customerId, $userId, $subtotal, $tax, $total, $paymentMethod, $notes]
            );
            
            $saleId = $this->db->lastInsertId();
            
            foreach ($validItems as $vi) {
                $this->query(
                    "INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, subtotal) 
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$saleId, $vi['product_id'], $vi['product_name'], $vi['quantity'], $vi['unit_price'], $vi['subtotal']]
                );
                
                // Descontar stock
                $this->query("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?", [$vi['quantity'], $vi['product_id'], $vi['quantity']]);
            }
            
            $this->db->commit();
            
            $this->respond(true, 'Venta creada: ' . $invoiceNumber . ' - Total: ' . $this->fmt($total), '/app/ventas');
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->respond(false, 'Error al crear la venta: ' . $e->getMessage(), '/app/ventas');
        }
    }
}
