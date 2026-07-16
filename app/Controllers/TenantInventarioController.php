<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

class TenantInventarioController extends Controller
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
        return ['COP'=>['symbol'=>'$','decimals'=>0,'thousands'=>'.','decimal'=>','],'USD'=>['symbol'=>'US$','decimals'=>2,'thousands'=>',','decimal'=>'.'],'EUR'=>['symbol'=>'€','decimals'=>2,'thousands'=>'.','decimal'=>',']][$code]??['symbol'=>'$','decimals'=>0,'thousands'=>'.','decimal'=>','];
    }
    
    private function addStockMovement(int $productId, string $type, int $qty, string $refType='', ?int $refId=null, string $notes=''): void
    {
        $this->query("INSERT INTO stock_movements (product_id, type, quantity, reference_type, reference_id, notes, user_id) VALUES (?,?,?,?,?,?,?)",
            [$productId, $type, $qty, $refType, $refId, $notes, $_SESSION['tenant_user_id']]);
    }
    
    public function index(): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'create' && $this->request->method() === 'POST') { $this->create(); return; }
        if ($action === 'edit' && $this->request->method() === 'POST') { $this->edit(); return; }
        if ($action === 'delete' && $this->request->method() === 'POST') { $this->delete(); return; }
        if ($action === 'add_stock' && $this->request->method() === 'POST') { $this->addStock(); return; }
        if ($action === 'detail' && $this->request->method() === 'GET') { $this->detail(); return; }
        
        $typeFilter = $this->request->get('type', '');
        $where = $typeFilter ? "WHERE p.product_type = ?" : "";
        $params = $typeFilter ? [$typeFilter] : [];
        
        $products = $this->query(
            "SELECT p.*, c.name as category_name,
                    DATEDIFF(NOW(), p.created_at) as days_in_inventory,
                    DATEDIFF(NOW(), p.last_sale_date) as days_since_last_sale
             FROM products p LEFT JOIN categories c ON p.category_id = c.id {$where} ORDER BY p.name ASC",
            $params
        )->fetchAll();
        
        $categories = $this->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
        $lowStock = $this->query("SELECT COUNT(*) as c FROM products WHERE stock<=min_stock AND status='active' AND product_type='product'")->fetch()['c'];
        $totalProducts = $this->query("SELECT COUNT(*) as c FROM products WHERE status='active' AND product_type='product'")->fetch()['c'];
        $totalServices = $this->query("SELECT COUNT(*) as c FROM products WHERE status='active' AND product_type='service'")->fetch()['c'];
        
        $this->view('tenant.inventario', [
            'products'=>$products, 'categories'=>$categories, 'lowStock'=>$lowStock, 'totalProducts'=>$totalProducts, 'totalServices'=>$totalServices,
            'currency'=>$this->getCurrency(), 'typeFilter'=>$typeFilter,
            'tenantName'=>$_SESSION['tenant_name']??'Mi Empresa', 'userName'=>$_SESSION['tenant_user_name']??'Usuario',
        ]);
    }
    
    public function detail(): void
    {
        $id = (int)$this->request->get('id');
        $product = $this->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=?", [$id])->fetch();
        if (!$product) { $this->json(['error'=>'Producto no encontrado']); return; }
        
        $movements = $this->query(
            "SELECT sm.*, u.name as user_name FROM stock_movements sm LEFT JOIN users u ON sm.user_id=u.id WHERE sm.product_id=? ORDER BY sm.created_at DESC LIMIT 30",
            [$id]
        )->fetchAll();
        
        $lastSales = $this->query(
            "SELECT si.*, s.invoice_number, s.sale_date FROM sale_items si JOIN sales s ON si.sale_id=s.id WHERE si.product_id=? ORDER BY s.sale_date DESC LIMIT 5",
            [$id]
        )->fetchAll();
        
        $this->json(['product'=>$product, 'movements'=>$movements, 'lastSales'=>$lastSales]);
    }
    
    private function create(): void
    {
        if (!$this->validateCsrfOrFail('/app/inventario')) return;
        
        $name = trim($this->request->post('name'));
        $productType = $this->request->post('product_type', 'product');
        $stock = $productType === 'service' ? 0 : (int)$this->request->post('stock', 0);
        
        if (empty($name)) { $this->respond(false, 'El nombre es requerido', '/app/inventario'); return; }
        
        $this->query("INSERT INTO products (code, name, product_type, description, category_id, purchase_price, sale_price, stock, min_stock, unit, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$this->request->post('code'), $name, $productType, $this->request->post('description'), $this->request->post('category_id')?:null,
             $this->request->post('purchase_price',0), $this->request->post('sale_price',0), $stock, $this->request->post('min_stock',5),
             $this->request->post('unit','UNIDAD'), $_SESSION['tenant_user_id']]);
        
        $newId = $this->db->lastInsertId();
        
        // Registrar movimiento inicial
        if ($stock > 0 && $productType === 'product') {
            $this->addStockMovement((int)$newId, 'in', $stock, 'purchase', null, 'Stock inicial');
        }
        
        $this->respond(true, ($productType==='service'?'Servicio':'Producto').' creado: '.$name, '/app/inventario');
    }
    
    private function edit(): void
    {
        if (!$this->validateCsrfOrFail('/app/inventario')) return;
        
        $id = (int)$this->request->post('id');
        $name = trim($this->request->post('name'));
        if (empty($name)) { $this->respond(false, 'El nombre es requerido', '/app/inventario'); return; }
        
        $oldProduct = $this->query("SELECT * FROM products WHERE id=?", [$id])->fetch();
        $productType = $this->request->post('product_type', $oldProduct['product_type']??'product');
        $newStock = $productType === 'service' ? 0 : (int)$this->request->post('stock', 0);
        
        $this->query("UPDATE products SET code=?, name=?, product_type=?, description=?, category_id=?, purchase_price=?, sale_price=?, stock=?, min_stock=?, unit=?, status=? WHERE id=?",
            [$this->request->post('code'), $name, $productType, $this->request->post('description'), $this->request->post('category_id')?:null,
             $this->request->post('purchase_price',0), $this->request->post('sale_price',0), $newStock, $this->request->post('min_stock',5),
             $this->request->post('unit','UNIDAD'), $this->request->post('status','active'), $id]);
        
        // Registrar ajuste de stock si cambió
        $oldStock = (int)$oldProduct['stock'];
        if ($newStock !== $oldStock && $productType === 'product') {
            $diff = $newStock - $oldStock;
            $this->addStockMovement($id, $diff > 0 ? 'in' : 'out', abs($diff), 'adjustment', null, 'Ajuste manual de stock');
        }
        
        $this->respond(true, 'Actualizado', '/app/inventario');
    }
    
    private function addStock(): void
    {
        if (!$this->validateCsrfOrFail('/app/inventario')) return;
        
        $id = (int)$this->request->post('id');
        $qty = (int)$this->request->post('quantity');
        $notes = $this->request->post('notes', 'Entrada de stock');
        
        if ($qty <= 0) { $this->respond(false, 'La cantidad debe ser mayor a 0', '/app/inventario'); return; }
        
        $this->query("UPDATE products SET stock = stock + ? WHERE id=?", [$qty, $id]);
        $this->addStockMovement($id, 'in', $qty, 'purchase', null, $notes);
        
        $this->respond(true, "Stock aumentado en {$qty} unidades", '/app/inventario');
    }
    
    private function delete(): void
    {
        if (!$this->validateCsrfOrFail('/app/inventario')) return;
        $id = (int)$this->request->post('id');
        $this->query("DELETE FROM products WHERE id=?", [$id]);
        $this->respond(true, 'Eliminado', '/app/inventario');
    }
}
