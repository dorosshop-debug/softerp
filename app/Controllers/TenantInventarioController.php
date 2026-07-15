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
        $currencies = ['COP'=>['symbol'=>'$','decimals'=>0,'thousands'=>'.','decimal'=>','],'USD'=>['symbol'=>'US$','decimals'=>2,'thousands'=>',','decimal'=>'.'],'EUR'=>['symbol'=>'€','decimals'=>2,'thousands'=>'.','decimal'=>',']];
        return $currencies[$code] ?? $currencies['COP'];
    }
    
    public function index(): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'create' && $this->request->method() === 'POST') { $this->create(); return; }
        if ($action === 'edit' && $this->request->method() === 'POST') { $this->edit(); return; }
        if ($action === 'delete' && $this->request->method() === 'POST') { $this->delete(); return; }
        
        $products = $this->query(
            "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.name ASC"
        )->fetchAll();
        
        $categories = $this->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
        
        $lowStock = $this->query("SELECT COUNT(*) as c FROM products WHERE stock <= min_stock AND status='active'")->fetch()['c'];
        $totalProducts = $this->query("SELECT COUNT(*) as c FROM products WHERE status='active'")->fetch()['c'];
        
        $this->view('tenant.inventario', [
            'products' => $products,
            'categories' => $categories,
            'lowStock' => $lowStock,
            'totalProducts' => $totalProducts,
            'currency' => $this->getCurrency(),
            'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa',
            'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
        ]);
    }
    
    private function create(): void
    {
        if (!$this->validateCsrfOrFail('/app/inventario')) return;
        
        $name = trim($this->request->post('name'));
        if (empty($name)) { $this->respond(false, 'El nombre es requerido', '/app/inventario'); return; }
        
        $this->query("INSERT INTO products (code, name, description, category_id, purchase_price, sale_price, stock, min_stock, unit) VALUES (?,?,?,?,?,?,?,?,?)",
            [$this->request->post('code'), $name, $this->request->post('description'), $this->request->post('category_id')?:null, $this->request->post('purchase_price',0), $this->request->post('sale_price',0), $this->request->post('stock',0), $this->request->post('min_stock',5), $this->request->post('unit','UNIDAD')]);
        
        $this->respond(true, 'Producto creado: ' . $name, '/app/inventario');
    }
    
    private function edit(): void
    {
        if (!$this->validateCsrfOrFail('/app/inventario')) return;
        
        $id = (int)$this->request->post('id');
        $name = trim($this->request->post('name'));
        if (empty($name)) { $this->respond(false, 'El nombre es requerido', '/app/inventario'); return; }
        
        $this->query("UPDATE products SET code=?, name=?, description=?, category_id=?, purchase_price=?, sale_price=?, stock=?, min_stock=?, unit=?, status=? WHERE id=?",
            [$this->request->post('code'), $name, $this->request->post('description'), $this->request->post('category_id')?:null, $this->request->post('purchase_price',0), $this->request->post('sale_price',0), $this->request->post('stock',0), $this->request->post('min_stock',5), $this->request->post('unit','UNIDAD'), $this->request->post('status','active'), $id]);
        
        $this->respond(true, 'Producto actualizado', '/app/inventario');
    }
    
    private function delete(): void
    {
        if (!$this->validateCsrfOrFail('/app/inventario')) return;
        $id = (int)$this->request->post('id');
        $this->query("DELETE FROM products WHERE id=?", [$id]);
        $this->respond(true, 'Producto eliminado', '/app/inventario');
    }
}
