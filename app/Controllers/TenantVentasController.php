<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

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
        $currencies = ['COP'=>['symbol'=>'$','decimals'=>0,'thousands'=>'.','decimal'=>','],'USD'=>['symbol'=>'US$','decimals'=>2,'thousands'=>',','decimal'=>'.'],'EUR'=>['symbol'=>'€','decimals'=>2,'thousands'=>'.','decimal'=>',']];
        return $currencies[$code] ?? $currencies['COP'];
    }
    
    public function index(): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'create' && $this->request->method() === 'POST') { $this->createSale(); return; }
        if ($action === 'detail' && $this->request->method() === 'GET') { $this->detail(); return; }
        if ($action === 'cancel' && $this->request->method() === 'POST') { $this->cancelSale(); return; }
        
        $sales = $this->query(
            "SELECT s.*, c.name as customer_name, u.name as user_name
             FROM sales s LEFT JOIN customers c ON s.customer_id = c.id LEFT JOIN users u ON s.user_id = u.id
             ORDER BY s.created_at DESC LIMIT 50"
        )->fetchAll();
        
        $todayTotal = $this->query("SELECT COALESCE(SUM(total),0) as t FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'")->fetch()['t'];
        $products = $this->query("SELECT id, name, sale_price, stock FROM products WHERE status='active' ORDER BY name")->fetchAll();
        $customers = $this->query("SELECT id, name, first_name, last_name FROM customers WHERE status='active' ORDER BY name")->fetchAll();
        
        $this->view('tenant.ventas', [
            'sales' => $sales, 'todayTotal' => $todayTotal, 'products' => $products, 'customers' => $customers,
            'currency' => $this->getCurrency(), 'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa', 'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
        ]);
    }
    
    public function detail(): void
    {
        $id = (int)$this->request->get('id');
        $sale = $this->query(
            "SELECT s.*, c.name as customer_name, c.first_name, c.last_name, u.name as user_name
             FROM sales s LEFT JOIN customers c ON s.customer_id=c.id LEFT JOIN users u ON s.user_id=u.id WHERE s.id=?", [$id]
        )->fetch();
        if (!$sale) { $this->json(['error'=>'Venta no encontrada']); return; }
        
        $items = $this->query("SELECT * FROM sale_items WHERE sale_id=?", [$id])->fetchAll();
        $this->json(['sale'=>$sale, 'items'=>$items]);
    }
    
    private function createSale(): void
    {
        if (!$this->validateCsrfOrFail('/app/ventas')) return;
        
        $customerId = $this->request->post('customer_id') ? (int)$this->request->post('customer_id') : null;
        $paymentMethod = $this->request->post('payment_method', 'cash');
        $items = $this->request->post('items', []);
        $notes = $this->request->post('notes', '');
        
        if (empty($items) || !is_array($items)) { $this->respond(false, 'Debe agregar al menos un producto', '/app/ventas'); return; }
        
        $prefix = $this->query("SELECT setting_value FROM settings WHERE setting_key='invoice_prefix'")->fetch();
        $prefix = $prefix['setting_value'] ?? 'FAC-';
        $invoiceNumber = $prefix . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        
        $subtotal = 0; $validItems = [];
        foreach ($items as $item) {
            $productId = (int)($item['product_id']??0); $quantity = (int)($item['quantity']??0);
            if ($productId <= 0 || $quantity <= 0) continue;
            $product = $this->query("SELECT * FROM products WHERE id=?", [$productId])->fetch();
            if (!$product) continue;
            $up = (float)$product['sale_price']; $is = $up * $quantity; $subtotal += $is;
            $validItems[] = ['product_id'=>$productId, 'product_name'=>$product['name'], 'quantity'=>$quantity, 'unit_price'=>$up, 'subtotal'=>$is];
        }
        if (empty($validItems)) { $this->respond(false, 'No se encontraron productos válidos', '/app/ventas'); return; }
        
        $taxRate = (float)($this->query("SELECT setting_value FROM settings WHERE setting_key='tax_rate'")->fetch()['setting_value']??0);
        $tax = $subtotal * ($taxRate/100); $total = $subtotal + $tax;
        
        try {
            $this->db->beginTransaction();
            $this->query("INSERT INTO sales (invoice_number, customer_id, user_id, sale_date, subtotal, tax, discount, total, payment_method, payment_status, notes, status) VALUES (?,?,?,NOW(),?,?,0,?,?,'paid',?,'completed')",
                [$invoiceNumber, $customerId, $_SESSION['tenant_user_id'], $subtotal, $tax, $total, $paymentMethod, $notes]);
            $saleId = $this->db->lastInsertId();
            foreach ($validItems as $vi) {
                $this->query("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?,?,?,?,?,?)",
                    [$saleId, $vi['product_id'], $vi['product_name'], $vi['quantity'], $vi['unit_price'], $vi['subtotal']]);
                $this->query("UPDATE products SET stock=stock-?, last_sale_date=NOW() WHERE id=? AND stock>=?", [$vi['quantity'], $vi['product_id'], $vi['quantity']]);
                // Registrar movimiento de stock (solo productos físicos)
                $this->query("INSERT INTO stock_movements (product_id, type, quantity, reference_type, reference_id, notes, user_id) VALUES (?,'out',?,'sale',?,?,?)",
                    [$vi['product_id'], $vi['quantity'], $saleId, 'Venta: '.$invoiceNumber, $_SESSION['tenant_user_id']]);
            }
            $this->db->commit();
            $this->respond(true, 'Venta creada: ' . $invoiceNumber, '/app/ventas');
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->respond(false, 'Error: ' . $e->getMessage(), '/app/ventas');
        }
    }
    
    private function cancelSale(): void
    {
        if (!$this->validateCsrfOrFail('/app/ventas')) return;
        
        $id = (int)$this->request->post('id');
        $sale = $this->query("SELECT * FROM sales WHERE id=? AND status='completed'", [$id])->fetch();
        if (!$sale) { $this->respond(false, 'Venta no encontrada o ya cancelada', '/app/ventas'); return; }
        
        try {
            $this->db->beginTransaction();
            
            // Devolver stock y registrar movimiento
            $items = $this->query("SELECT * FROM sale_items WHERE sale_id=?", [$id])->fetchAll();
            foreach ($items as $item) {
                $this->query("UPDATE products SET stock=stock+? WHERE id=?", [$item['quantity'], $item['product_id']]);
                $this->query("INSERT INTO stock_movements (product_id, type, quantity, reference_type, reference_id, notes, user_id) VALUES (?,'in',?,'return',?,?,?)",
                    [$item['product_id'], $item['quantity'], $id, 'Devolución: '.$sale['invoice_number'], $_SESSION['tenant_user_id']]);
            }
            
            // Marcar como cancelada
            $this->query("UPDATE sales SET status='cancelled', notes=CONCAT(IFNULL(notes,''), ' | CANCELADO: '," . $this->db->quote(date('Y-m-d H:i')) . ") WHERE id=?", [$id]);
            
            $this->db->commit();
            $this->respond(true, 'Venta cancelada. Stock devuelto.', '/app/ventas');
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->respond(false, 'Error: ' . $e->getMessage(), '/app/ventas');
        }
    }
}
