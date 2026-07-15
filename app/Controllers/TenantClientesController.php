<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

class TenantClientesController extends Controller
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
        
        $customers = $this->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();
        
        $this->view('tenant.clientes', [
            'customers' => $customers,
            'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa',
            'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
        ]);
    }
    
    private function create(): void
    {
        if (!$this->validateCsrfOrFail('/app/clientes')) return;
        
        $name = trim($this->request->post('name'));
        $docType = $this->request->post('document_type', 'CC');
        $docNum = $this->request->post('document_number');
        $email = $this->request->post('email');
        $phone = $this->request->post('phone');
        $address = $this->request->post('address');
        
        if (empty($name)) { $this->respond(false, 'El nombre es requerido', '/app/clientes'); return; }
        
        $this->query("INSERT INTO customers (name, document_type, document_number, email, phone, address) VALUES (?,?,?,?,?,?)",
            [$name, $docType, $docNum, $email, $phone, $address]);
        
        $this->respond(true, 'Cliente creado: ' . $name, '/app/clientes');
    }
    
    private function edit(): void
    {
        if (!$this->validateCsrfOrFail('/app/clientes')) return;
        
        $id = (int)$this->request->post('id');
        $name = trim($this->request->post('name'));
        
        if (empty($name)) { $this->respond(false, 'El nombre es requerido', '/app/clientes'); return; }
        
        $this->query("UPDATE customers SET name=?, document_type=?, document_number=?, email=?, phone=?, address=? WHERE id=?",
            [$name, $this->request->post('document_type','CC'), $this->request->post('document_number'), $this->request->post('email'), $this->request->post('phone'), $this->request->post('address'), $id]);
        
        $this->respond(true, 'Cliente actualizado', '/app/clientes');
    }
    
    private function delete(): void
    {
        if (!$this->validateCsrfOrFail('/app/clientes')) return;
        $id = (int)$this->request->post('id');
        $this->query("DELETE FROM customers WHERE id=?", [$id]);
        $this->respond(true, 'Cliente eliminado', '/app/clientes');
    }
}
