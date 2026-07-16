<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

class TenantProveedoresController extends Controller
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
    
    public function index(): void
    {
        $action = $this->request->get('action');
        if ($action === 'create' && $this->request->method() === 'POST') { $this->create(); return; }
        if ($action === 'edit' && $this->request->method() === 'POST') { $this->edit(); return; }
        if ($action === 'delete' && $this->request->method() === 'POST') { $this->delete(); return; }
        
        $suppliers = $this->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
        
        $this->view('tenant.proveedores', [
            'suppliers' => $suppliers,
            'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa',
            'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
        ]);
    }
    
    private function create(): void
    {
        if (!$this->validateCsrfOrFail('/app/proveedores')) return;
        
        $name = trim($this->request->post('name') ?? '');
        if (empty($name)) { $this->respond(false, 'El nombre de la empresa es requerido', '/app/proveedores'); return; }
        
        $image = $this->uploadImage();
        
        $this->query("INSERT INTO suppliers (name, document_type, document_number, contact_name, email, phone, address, notes, image) VALUES (?,?,?,?,?,?,?,?,?)",
            [$name, $this->request->post('document_type','NIT'), $this->request->post('document_number'), $this->request->post('contact_name'), $this->request->post('email'), $this->request->post('phone'), $this->request->post('address'), $this->request->post('notes'), $image]);
        
        $this->respond(true, 'Proveedor creado: ' . $name, '/app/proveedores');
    }
    
    private function edit(): void
    {
        if (!$this->validateCsrfOrFail('/app/proveedores')) return;
        $id = (int)$this->request->post('id');
        $name = trim($this->request->post('name') ?? '');
        if (empty($name)) { $this->respond(false, 'El nombre de la empresa es requerido', '/app/proveedores'); return; }
        
        $image = $this->uploadImage();
        $sql = "UPDATE suppliers SET name=?, document_type=?, document_number=?, contact_name=?, email=?, phone=?, address=?, notes=?";
        $params = [$name, $this->request->post('document_type','NIT'), $this->request->post('document_number'), $this->request->post('contact_name'), $this->request->post('email'), $this->request->post('phone'), $this->request->post('address'), $this->request->post('notes')];
        
        if ($image) {
            $sql .= ", image=?";
            $params[] = $image;
        }
        $sql .= " WHERE id=?";
        $params[] = $id;
        
        $this->query($sql, $params);
        $this->respond(true, 'Proveedor actualizado', '/app/proveedores');
    }
    
    private function uploadImage(): ?string
    {
        if (empty($_FILES['image']['tmp_name'])) return null;
        
        $uploadDir = PUBLIC_PATH . '/uploads/suppliers/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) return null;
        
        $filename = 'sup_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            $baseUrl = \SoftNova\Core\config('app.url', 'http://localhost/SoftNova/public');
            return rtrim($baseUrl, '/') . '/uploads/suppliers/' . $filename;
        }
        return null;
    }
    
    private function delete(): void
    {
        if (!$this->validateCsrfOrFail('/app/proveedores')) return;
        $id = (int)$this->request->post('id');
        $this->query("DELETE FROM suppliers WHERE id=?", [$id]);
        $this->respond(true, 'Proveedor eliminado', '/app/proveedores');
    }
}
