<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

/**
 * Dashboard del tenant - panel principal del cliente
 */
class TenantDashboardController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        TenantMiddleware::auth();
    }
    
    public function index(): void
    {
        $db = TenantMiddleware::getDb();
        
        // Búsqueda global (AJAX)
        $q = $_GET['q'] ?? '';
        if (!empty($q) && $this->wantsJson()) {
            $this->search($db, $q);
            return;
        }
        
        // Estadísticas del tenant
        $stats = [
            'total_products' => $db->query("SELECT COUNT(*) as c FROM products")->fetch()['c'],
            'total_customers' => $db->query("SELECT COUNT(*) as c FROM customers")->fetch()['c'],
            'total_sales' => $db->query("SELECT COUNT(*) as c FROM sales")->fetch()['c'],
            'today_sales' => $db->query("SELECT COALESCE(SUM(total), 0) as t FROM sales WHERE DATE(sale_date) = CURDATE() AND status = 'completed'")->fetch()['t'],
            'low_stock' => $db->query("SELECT COUNT(*) as c FROM products WHERE stock <= min_stock AND status = 'active'")->fetch()['c'],
        ];
        
        // Ventas recientes
        $recentSales = $db->query("
            SELECT s.*, c.name as customer_name
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            ORDER BY s.created_at DESC
            LIMIT 5
        ")->fetchAll();
        
        $this->view('tenant.dashboard', [
            'stats' => $stats,
            'recentSales' => $recentSales,
            'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa',
            'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
        ]);
    }
    
    /**
     * Búsqueda global en vivo
     */
    private function search(\PDO $db, string $q): void
    {
        $like = '%' . $q . '%';
        $results = [];
        
        // Buscar productos
        $products = $db->prepare("SELECT id, name, code, sale_price, stock FROM products WHERE (name LIKE ? OR code LIKE ?) AND status = 'active' LIMIT 5");
        $products->execute([$like, $like]);
        foreach ($products->fetchAll() as $p) {
            $results[] = [
                'type' => 'producto',
                'title' => $p['name'],
                'subtitle' => 'Stock: ' . $p['stock'] . ' | Precio: $' . number_format($p['sale_price'], 0),
                'url' => \SoftNova\Core\route('app/inventario'),
                'badge' => 'Producto',
                'badgeClass' => 'badge-success',
            ];
        }
        
        // Buscar clientes
        $customers = $db->prepare("SELECT id, first_name, last_name, name, document_number, email, phone FROM customers WHERE (first_name LIKE ? OR last_name LIKE ? OR name LIKE ? OR document_number LIKE ? OR email LIKE ?) AND status = 'active' LIMIT 5");
        $customers->execute([$like, $like, $like, $like, $like]);
        foreach ($customers->fetchAll() as $c) {
            $displayName = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
            if (empty($displayName)) $displayName = $c['name'];
            $results[] = [
                'type' => 'cliente',
                'title' => $displayName,
                'subtitle' => ($c['document_number'] ? 'Doc: ' . $c['document_number'] . ' | ' : '') . ($c['phone'] ?? ''),
                'url' => \SoftNova\Core\route('app/clientes'),
                'badge' => 'Cliente',
                'badgeClass' => 'badge-info',
            ];
        }
        
        // Buscar ventas
        $sales = $db->prepare("SELECT s.id, s.invoice_number, s.total, s.sale_date, c.first_name, c.last_name, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.invoice_number LIKE ? ORDER BY s.sale_date DESC LIMIT 5");
        $sales->execute([$like]);
        foreach ($sales->fetchAll() as $s) {
            $custName = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
            if (empty($custName)) $custName = $s['customer_name'] ?? 'General';
            $results[] = [
                'type' => 'venta',
                'title' => $s['invoice_number'],
                'subtitle' => $custName . ' | $' . number_format($s['total'], 0) . ' | ' . date('d/m/Y', strtotime($s['sale_date'])),
                'url' => \SoftNova\Core\route('app/ventas'),
                'badge' => 'Venta',
                'badgeClass' => 'badge-warning',
            ];
        }
        
        $this->json(['results' => $results]);
    }
}
