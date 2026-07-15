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
}
