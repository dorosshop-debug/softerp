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
        TenantMiddleware::authorize('clientes');
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
        if ($action === 'detail' && $this->request->method() === 'GET') { $this->detail(); return; }
        
        $customers = $this->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();
        
        $this->view('tenant.clientes', [
            'customers' => $customers,
            'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa',
            'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
        ]);
    }
    
    public function detail(): void
    {
        $id = (int)$this->request->get('id');
        $customer = $this->query("SELECT * FROM customers WHERE id=?", [$id])->fetch();
        if (!$customer) { $this->json(['error'=>'Cliente no encontrado']); return; }
        
        // Historial de compras
        $purchases = $this->query(
            "SELECT s.*, u.name as user_name FROM sales s LEFT JOIN users u ON s.user_id=u.id 
             WHERE s.customer_id=? AND s.status='completed' ORDER BY s.created_at DESC LIMIT 20",
            [$id]
        )->fetchAll();
        
        $this->json(['customer'=>$customer, 'purchases'=>$purchases]);
    }
    
    private function create(): void
    {
        if (!$this->validateCsrfOrFail('/app/clientes')) return;
        
        $name = trim($this->request->post('name') ?? '');
        $firstName = trim($this->request->post('first_name') ?? '');
        $lastName = trim($this->request->post('last_name') ?? '');
        $companyName = trim($this->request->post('company_name') ?? '');
        $source = $this->request->post('source');
        
        if (empty($name) && empty($firstName)) { $this->respond(false, 'El nombre es requerido', '/app/clientes'); return; }
        
        // Si no hay name, construirlo con first + last
        if (empty($name) && !empty($firstName)) {
            $name = trim($firstName . ' ' . $lastName);
        }
        
        $this->query("INSERT INTO customers (name, first_name, last_name, company_name, document_type, document_number, email, phone, address, source) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$name, $firstName?:null, $lastName?:null, $companyName?:null, $this->request->post('document_type','CC'), $this->request->post('document_number'), $this->request->post('email'), $this->request->post('phone'), $this->request->post('address'), $source?:null]);
        
        $newId = $this->db->lastInsertId();
        if ($this->wantsJson()) {
            $this->json(['success' => true, 'message' => 'Cliente creado: ' . $name, 'data' => ['id' => $newId, 'name' => $name]]);
            return;
        }
        $this->respond(true, 'Cliente creado: ' . $name, '/app/clientes');
    }
    
    private function edit(): void
    {
        if (!$this->validateCsrfOrFail('/app/clientes')) return;
        
        $id = (int)$this->request->post('id');
        $name = trim($this->request->post('name') ?? '');
        $firstName = trim($this->request->post('first_name') ?? '');
        $lastName = trim($this->request->post('last_name') ?? '');
        
        if (empty($name) && empty($firstName)) { $this->respond(false, 'El nombre es requerido', '/app/clientes'); return; }
        if (empty($name) && !empty($firstName)) { $name = trim($firstName . ' ' . $lastName); }
        
        $this->query("UPDATE customers SET name=?, first_name=?, last_name=?, company_name=?, document_type=?, document_number=?, email=?, phone=?, address=?, source=? WHERE id=?",
            [$name, $firstName?:null, $lastName?:null, $this->request->post('company_name')?:null, $this->request->post('document_type','CC'), $this->request->post('document_number'), $this->request->post('email'), $this->request->post('phone'), $this->request->post('address'), $this->request->post('source')?:null, $id]);
        
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
