<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

/**
 * Controlador de Reportes del Tenant
 * Dashboard analítico con gráficos, tendencias, rentabilidad y recomendaciones
 */
class TenantReportesController extends Controller
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
        $row = $this->query("SELECT setting_value FROM settings WHERE setting_key = 'currency'")->fetch();
        $code = $row['setting_value'] ?? 'COP';
        $currencies = [
            'COP' => ['symbol' => '$', 'decimals' => 0, 'decimal' => ',', 'thousands' => '.'],
            'USD' => ['symbol' => 'US$', 'decimals' => 2, 'decimal' => '.', 'thousands' => ','],
            'EUR' => ['symbol' => '€', 'decimals' => 2, 'decimal' => ',', 'thousands' => '.'],
            'MXN' => ['symbol' => 'MX$', 'decimals' => 2, 'decimal' => '.', 'thousands' => ','],
            'ARS' => ['symbol' => 'AR$', 'decimals' => 2, 'decimal' => ',', 'thousands' => '.'],
            'PEN' => ['symbol' => 'S/', 'decimals' => 2, 'decimal' => '.', 'thousands' => ','],
            'CLP' => ['symbol' => 'CL$', 'decimals' => 0, 'decimal' => ',', 'thousands' => '.'],
        ];
        return $currencies[$code] ?? $currencies['COP'];
    }
    
    public function index(): void
    {
        $currency = $this->getCurrency();
        
        // Filtros de fecha
        $dateFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $_GET['to'] ?? date('Y-m-d');
        
        // Período anterior (misma duración)
        $diff = strtotime($dateTo) - strtotime($dateFrom);
        $prevFrom = date('Y-m-d', strtotime($dateFrom) - $diff - 86400);
        $prevTo = date('Y-m-d', strtotime($dateFrom) - 86400);
        
        // Totales generales (período actual)
        $totalSales = (float)$this->query(
            "SELECT COALESCE(SUM(total), 0) as t FROM sales WHERE status = 'completed' AND DATE(sale_date) BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        )->fetch()['t'];
        
        $totalSalesCount = (int)$this->query(
            "SELECT COUNT(*) as c FROM sales WHERE status = 'completed' AND DATE(sale_date) BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        )->fetch()['c'];
        
        // Totales período anterior (para comparativa)
        $prevTotalSales = (float)$this->query(
            "SELECT COALESCE(SUM(total), 0) as t FROM sales WHERE status = 'completed' AND DATE(sale_date) BETWEEN ? AND ?",
            [$prevFrom, $prevTo]
        )->fetch()['t'];
        
        $prevSalesCount = (int)$this->query(
            "SELECT COUNT(*) as c FROM sales WHERE status = 'completed' AND DATE(sale_date) BETWEEN ? AND ?",
            [$prevFrom, $prevTo]
        )->fetch()['c'];
        
        // Totales globales (sin filtro)
        $totalProducts = (int)$this->query("SELECT COUNT(*) as c FROM products WHERE status = 'active'")->fetch()['c'];
        $totalCustomers = (int)$this->query("SELECT COUNT(*) as c FROM customers WHERE status = 'active'")->fetch()['c'];
        $lowStock = (int)$this->query("SELECT COUNT(*) as c FROM products WHERE stock <= min_stock AND status = 'active'")->fetch()['c'];
        
        // Ventas por mes (últimos 12 meses)
        $salesByMonth = $this->query("
            SELECT DATE_FORMAT(sale_date, '%Y-%m') as month, 
                   COALESCE(SUM(total), 0) as total,
                   COUNT(*) as count
            FROM sales 
            WHERE status = 'completed' AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
            ORDER BY month ASC
        ")->fetchAll();
        
        // Ventas por día (período seleccionado)
        $salesByDay = $this->query(
            "SELECT DATE(sale_date) as day, COALESCE(SUM(total), 0) as total
             FROM sales WHERE status = 'completed' AND DATE(sale_date) BETWEEN ? AND ?
             GROUP BY DATE(sale_date) ORDER BY day ASC",
            [$dateFrom, $dateTo]
        )->fetchAll();
        
        // Top productos más vendidos
        $topProducts = $this->query("
            SELECT p.name, SUM(si.quantity) as total_qty, SUM(si.subtotal) as total_sales
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            JOIN sales s ON si.sale_id = s.id
            WHERE s.status = 'completed' AND DATE(s.sale_date) BETWEEN ? AND ?
            GROUP BY p.id, p.name
            ORDER BY total_qty DESC
            LIMIT 10
        ",[$dateFrom, $dateTo])->fetchAll();
        
        // Rentabilidad por producto (margen de ganancia)
        $profitability = $this->query("
            SELECT p.name, 
                   SUM(si.quantity) as total_qty,
                   SUM(si.subtotal) as total_sales,
                   SUM(si.quantity * COALESCE(p.purchase_price, 0)) as total_cost,
                   SUM(si.subtotal) - SUM(si.quantity * COALESCE(p.purchase_price, 0)) as profit
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            JOIN sales s ON si.sale_id = s.id
            WHERE s.status = 'completed' AND DATE(s.sale_date) BETWEEN ? AND ?
            GROUP BY p.id, p.name
            HAVING total_qty > 0
            ORDER BY profit DESC
            LIMIT 10
        ",[$dateFrom, $dateTo])->fetchAll();
        
        // Ventas por categoría
        $salesByCategory = $this->query("
            SELECT COALESCE(cat.name, 'Sin categoría') as category,
                   COALESCE(SUM(si.subtotal), 0) as total
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            LEFT JOIN products p ON si.product_id = p.id
            LEFT JOIN categories cat ON p.category_id = cat.id
            WHERE s.status = 'completed' AND DATE(s.sale_date) BETWEEN ? AND ?
            GROUP BY cat.id, cat.name
            ORDER BY total DESC
        ",[$dateFrom, $dateTo])->fetchAll();
        
        // Clientes frecuentes
        $topCustomers = $this->query("
            SELECT COALESCE(CONCAT(c.first_name, ' ', c.last_name), c.name) as customer_name,
                   COUNT(s.id) as purchase_count,
                   COALESCE(SUM(s.total), 0) as total_spent
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.status = 'completed' AND s.customer_id IS NOT NULL AND DATE(s.sale_date) BETWEEN ? AND ?
            GROUP BY c.id, c.first_name, c.last_name, c.name
            ORDER BY purchase_count DESC
            LIMIT 10
        ",[$dateFrom, $dateTo])->fetchAll();
        
        // Trazabilidad
        $stockMovements = $this->query("
            SELECT sm.*, p.name as product_name
            FROM stock_movements sm
            JOIN products p ON sm.product_id = p.id
            ORDER BY sm.created_at DESC
            LIMIT 20
        ")->fetchAll();
        
        // Stock bajo
        $lowStockProducts = $this->query("
            SELECT name, stock, min_stock, sale_price
            FROM products 
            WHERE stock <= min_stock AND status = 'active'
            ORDER BY (min_stock - stock) DESC
            LIMIT 10
        ")->fetchAll();
        
        // Tendencia de productos (comparativa mes actual vs anterior)
        $productTrend = $this->query("
            SELECT p.name,
                   COALESCE(SUM(CASE WHEN MONTH(s.sale_date) = MONTH(CURDATE()) AND YEAR(s.sale_date) = YEAR(CURDATE()) THEN si.quantity ELSE 0 END), 0) as current_month,
                   COALESCE(SUM(CASE WHEN MONTH(s.sale_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(s.sale_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN si.quantity ELSE 0 END), 0) as last_month
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            JOIN sales s ON si.sale_id = s.id
            WHERE s.status = 'completed' AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
            GROUP BY p.id, p.name
            HAVING current_month > 0 OR last_month > 0
            ORDER BY current_month DESC
            LIMIT 10
        ")->fetchAll();
        
        // Métodos de pago
        $paymentMethods = $this->query("
            SELECT payment_method, COUNT(*) as count, COALESCE(SUM(total), 0) as total
            FROM sales WHERE status = 'completed' AND DATE(sale_date) BETWEEN ? AND ?
            GROUP BY payment_method ORDER BY count DESC
        ",[$dateFrom, $dateTo])->fetchAll();
        
        // Calcular tendencias KPI (% cambio)
        $salesTrend = $prevTotalSales > 0 ? round((($totalSales - $prevTotalSales) / $prevTotalSales) * 100, 1) : 0;
        $countTrend = $prevSalesCount > 0 ? round((($totalSalesCount - $prevSalesCount) / $prevSalesCount) * 100, 1) : 0;
        $avgTicket = $totalSalesCount > 0 ? $totalSales / $totalSalesCount : 0;
        $prevAvgTicket = $prevSalesCount > 0 ? $prevTotalSales / $prevSalesCount : 0;
        $ticketTrend = $prevAvgTicket > 0 ? round((($avgTicket - $prevAvgTicket) / $prevAvgTicket) * 100, 1) : 0;
        
        $this->view('tenant.reportes', [
            'currency' => $currency,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'prevFrom' => $prevFrom,
            'prevTo' => $prevTo,
            'totalSales' => $totalSales,
            'totalSalesCount' => $totalSalesCount,
            'prevTotalSales' => $prevTotalSales,
            'prevSalesCount' => $prevSalesCount,
            'totalProducts' => $totalProducts,
            'totalCustomers' => $totalCustomers,
            'lowStock' => $lowStock,
            'salesTrend' => $salesTrend,
            'countTrend' => $countTrend,
            'ticketTrend' => $ticketTrend,
            'avgTicket' => $avgTicket,
            'salesByMonth' => $salesByMonth,
            'salesByDay' => $salesByDay,
            'topProducts' => $topProducts,
            'profitability' => $profitability,
            'salesByCategory' => $salesByCategory,
            'topCustomers' => $topCustomers,
            'stockMovements' => $stockMovements,
            'lowStockProducts' => $lowStockProducts,
            'productTrend' => $productTrend,
            'paymentMethods' => $paymentMethods,
            'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa',
            'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
        ]);
    }
}