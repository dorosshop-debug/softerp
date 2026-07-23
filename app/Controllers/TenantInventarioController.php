<?php

namespace SoftNova\Controllers;

use SoftNova\Core\TenantController;
use SoftNova\Core\TenantMiddleware;
use SoftNova\Services\StockService;

class TenantInventarioController extends TenantController
{
    private StockService $stock;
    private const MAX_UPLOAD_BYTES = 2097152; // 2MB
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    
    public function __construct()
    {
        parent::__construct();
        TenantMiddleware::authorize('inventario');
        $this->ensureQuotesSchema();
        $this->stock = new StockService($this->db);
    }
    
    public function index(): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'create' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('inventario', 'create');
            $this->create();
            return;
        }
        if ($action === 'edit' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('inventario', 'edit');
            $this->edit();
            return;
        }
        if ($action === 'delete' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('inventario', 'delete');
            $this->delete();
            return;
        }
        if ($action === 'add_stock' && $this->request->method() === 'POST') {
            TenantMiddleware::authorize('inventario', 'edit');
            $this->addStock();
            return;
        }
        if ($action === 'detail' && $this->request->method() === 'GET') {
            $this->detail();
            return;
        }
        if ($action === 'export' && $this->request->method() === 'GET') {
            TenantMiddleware::authorize('inventario', 'export');
            $this->exportProducts();
            return;
        }
        
        $typeFilter = $this->request->get('type', '');
        $where = "WHERE p.status = 'active'";
        $params = [];
        if ($typeFilter) {
            $where .= " AND p.product_type = ?";
            $params[] = $typeFilter;
        }
        
        $total = (int)$this->query(
            "SELECT COUNT(*) as c FROM products p {$where}",
            $params
        )->fetch()['c'];
        $pagination = $this->paginate($total);
        
        $products = $this->query(
            "SELECT p.*, c.name as category_name,
                    DATEDIFF(NOW(), p.created_at) as days_in_inventory,
                    DATEDIFF(NOW(), p.last_sale_date) as days_since_last_sale,
                    COALESCE((
                        SELECT SUM(qi.quantity)
                        FROM quote_items qi
                        JOIN quotes q ON qi.quote_id = q.id
                        WHERE qi.product_id = p.id AND q.status IN ('pending', 'accepted')
                    ), 0) as reserved_qty
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             {$where}
             ORDER BY p.name ASC
             LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
            $params
        )->fetchAll();
        
        $categories = $this->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
        $lowStock = $this->query(
            "SELECT COUNT(*) as c FROM products WHERE stock <= min_stock AND status = 'active' AND product_type = 'product'"
        )->fetch()['c'];
        $totalProducts = $this->query(
            "SELECT COUNT(*) as c FROM products WHERE status = 'active' AND product_type = 'product'"
        )->fetch()['c'];
        $totalServices = $this->query(
            "SELECT COUNT(*) as c FROM products WHERE status = 'active' AND product_type = 'service'"
        )->fetch()['c'];
        
        $this->view('tenant.inventario', $this->tenantViewData([
            'products' => $products,
            'categories' => $categories,
            'lowStock' => $lowStock,
            'totalProducts' => $totalProducts,
            'totalServices' => $totalServices,
            'typeFilter' => $typeFilter,
            'pagination' => $pagination,
        ]));
    }
    
    public function detail(): void
    {
        $id = (int)$this->request->get('id');
        $product = $this->query(
            "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?",
            [$id]
        )->fetch();
        
        if (!$product) {
            $this->json(['error' => 'Producto no encontrado']);
            return;
        }
        
        $movements = $this->query(
            "SELECT sm.*, u.name as user_name
             FROM stock_movements sm
             LEFT JOIN users u ON sm.user_id = u.id
             WHERE sm.product_id = ?
             ORDER BY sm.created_at DESC LIMIT 30",
            [$id]
        )->fetchAll();
        
        $lastSales = $this->query(
            "SELECT si.*, s.invoice_number, s.sale_date
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             WHERE si.product_id = ?
             ORDER BY s.sale_date DESC LIMIT 5",
            [$id]
        )->fetchAll();
        
        $this->json(['product' => $product, 'movements' => $movements, 'lastSales' => $lastSales]);
    }
    
    private function create(): void
    {
        if (!$this->validateCsrfOrFail('/app/inventario')) {
            return;
        }
        
        $name = trim($this->request->post('name'));
        $productType = $this->request->post('product_type', 'product');
        $stock = $productType === 'service' ? 0 : (int)$this->request->post('stock', 0);
        
        if (empty($name)) {
            $this->respond(false, 'El nombre es requerido', '/app/inventario');
            return;
        }
        
        $image = $this->uploadImage();
        if ($image === false) {
            $this->respond(false, 'Imagen invalida. Use JPG/PNG/GIF/WEBP max 2MB', '/app/inventario');
            return;
        }
        
        $code = $this->request->post('code');
        if (empty($code)) {
            $code = 'SKU-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        
        $this->query(
            "INSERT INTO products
                (code, name, product_type, description, category_id, purchase_price, sale_price,
                 stock, min_stock, unit, image, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $code,
                $name,
                $productType,
                $this->request->post('description'),
                $this->request->post('category_id') ?: null,
                $this->request->post('purchase_price', 0),
                $this->request->post('sale_price', 0),
                $stock,
                $this->request->post('min_stock', 5),
                $this->request->post('unit', 'UNIDAD'),
                $image,
                $_SESSION['tenant_user_id'],
            ]
        );
        
        $newId = (int)$this->db->lastInsertId();
        
        if ($stock > 0 && $productType === 'product') {
            $this->stock->addMovement($newId, 'in', $stock, 'purchase', null, 'Stock inicial');
        }
        
        $this->respond(true, ($productType === 'service' ? 'Servicio' : 'Producto') . ' creado: ' . $name, '/app/inventario');
    }
    
    private function edit(): void
    {
        if (!$this->validateCsrfOrFail('/app/inventario')) {
            return;
        }
        
        $id = (int)$this->request->post('id');
        $name = trim($this->request->post('name'));
        if (empty($name)) {
            $this->respond(false, 'El nombre es requerido', '/app/inventario');
            return;
        }
        
        $oldProduct = $this->query("SELECT * FROM products WHERE id = ?", [$id])->fetch();
        if (!$oldProduct) {
            $this->respond(false, 'Producto no encontrado', '/app/inventario');
            return;
        }
        
        $productType = $this->request->post('product_type', $oldProduct['product_type'] ?? 'product');
        $newStock = $productType === 'service' ? 0 : (int)$this->request->post('stock', 0);
        
        $image = $this->uploadImage();
        if ($image === false) {
            $this->respond(false, 'Imagen invalida. Use JPG/PNG/GIF/WEBP max 2MB', '/app/inventario');
            return;
        }
        
        $sql = "UPDATE products SET code = ?, name = ?, product_type = ?, description = ?, category_id = ?,
                purchase_price = ?, sale_price = ?, stock = ?, min_stock = ?, unit = ?, status = ?";
        $params = [
            $this->request->post('code'),
            $name,
            $productType,
            $this->request->post('description'),
            $this->request->post('category_id') ?: null,
            $this->request->post('purchase_price', 0),
            $this->request->post('sale_price', 0),
            $newStock,
            $this->request->post('min_stock', 5),
            $this->request->post('unit', 'UNIDAD'),
            $this->request->post('status', 'active'),
        ];
        
        if ($image) {
            $sql .= ", image = ?";
            $params[] = $image;
        }
        $sql .= " WHERE id = ?";
        $params[] = $id;
        $this->query($sql, $params);
        
        $oldStock = (int)$oldProduct['stock'];
        if ($newStock !== $oldStock && $productType === 'product') {
            $diff = $newStock - $oldStock;
            $this->stock->addMovement(
                $id,
                $diff > 0 ? 'in' : 'out',
                abs($diff),
                'adjustment',
                null,
                'Ajuste manual de stock'
            );
        }
        
        $this->respond(true, 'Actualizado', '/app/inventario');
    }
    
    private function addStock(): void
    {
        if (!$this->validateCsrfOrFail('/app/inventario')) {
            return;
        }
        
        $id = (int)$this->request->post('id');
        $qty = (int)$this->request->post('quantity');
        $notes = $this->request->post('notes', 'Entrada de stock');
        
        if ($qty <= 0) {
            $this->respond(false, 'La cantidad debe ser mayor a 0', '/app/inventario');
            return;
        }
        
        $this->stock->increase($id, $qty, 'purchase', null, $notes);
        $this->respond(true, "Stock aumentado en {$qty} unidades", '/app/inventario');
    }
    
    private function delete(): void
    {
        if (!$this->validateCsrfOrFail('/app/inventario')) {
            return;
        }
        
        $id = (int)$this->request->post('id');
        
        try {
            $this->query(
                "UPDATE products SET status = 'inactive', deleted_at = NOW() WHERE id = ?",
                [$id]
            );
        } catch (\Exception $e) {
            $this->query("UPDATE products SET status = 'inactive' WHERE id = ?", [$id]);
        }
        
        $this->respond(true, 'Producto desactivado (soft-delete)', '/app/inventario');
    }
    
    /**
     * @return string|null|false null = sin archivo, string = URL, false = error de validacion
     */
    private function uploadImage()
    {
        if (empty($_FILES['image']['tmp_name'])) {
            return null;
        }
        
        if (!is_uploaded_file($_FILES['image']['tmp_name'])) {
            return false;
        }
        
        if (($_FILES['image']['size'] ?? 0) > self::MAX_UPLOAD_BYTES) {
            return false;
        }
        
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image']['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            return false;
        }
        
        $ext = self::ALLOWED_MIME[$mime];
        $uploadDir = PUBLIC_PATH . '/uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filename = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $uploadDir . $filename;
        
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            return false;
        }
        
        $baseUrl = \SoftNova\Core\config('app.url', 'http://localhost/SoftNova/public');
        return rtrim($baseUrl, '/') . '/uploads/products/' . $filename;
    }
    
    private function exportProducts(): void
    {
        $rows = $this->query(
            "SELECT p.code, p.name, p.product_type, c.name as category_name, p.purchase_price,
                    p.sale_price, p.stock, p.min_stock, p.unit, p.status
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.status = 'active'
             ORDER BY p.name"
        )->fetchAll();
        
        $csvRows = [];
        foreach ($rows as $r) {
            $csvRows[] = [
                $r['code'],
                $r['name'],
                $r['product_type'],
                $r['category_name'],
                $r['purchase_price'],
                $r['sale_price'],
                $r['stock'],
                $r['min_stock'],
                $r['unit'],
                $r['status'],
            ];
        }
        
        $this->exportCsv(
            'inventario_' . date('Ymd_His') . '.csv',
            ['SKU', 'Nombre', 'Tipo', 'Categoria', 'Costo', 'Precio', 'Stock', 'Min', 'Unidad', 'Estado'],
            $csvRows
        );
    }
}
