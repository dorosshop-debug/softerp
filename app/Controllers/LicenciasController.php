<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\Database;
use SoftNova\Core\Middleware;
use SoftNova\Services\AuditService;

/**
 * Controlador del módulo Control de Licencias
 * Registra ventas y suscripciones de clientes
 */
class LicenciasController extends Controller
{
    private Database $db;
    
    public function __construct()
    {
        parent::__construct();
        Middleware::auth();
        $this->db = Database::getInstance();
    }
    
    /**
     * Genera un código único de venta
     */
    private function generateSaleCode(): string
    {
        $prefix = 'LIC-' . date('Y') . '-';
        $random = strtoupper(bin2hex(random_bytes(3)));
        return $prefix . $random;
    }
    
    /**
     * Calcula la fecha de fin según el ciclo de facturación
     */
    private function calculateEndDate(string $startDate, string $billingCycle): string
    {
        $start = new \DateTime($startDate);
        
        switch ($billingCycle) {
            case 'monthly':
                $start->modify('+1 month');
                break;
            case 'quarterly':
                $start->modify('+3 months');
                break;
            case 'semiannual':
                $start->modify('+6 months');
                break;
            case 'annual':
                $start->modify('+1 year');
                break;
            default:
                $start->modify('+1 month');
        }
        
        return $start->format('Y-m-d');
    }
    
    /**
     * Calcula el monto según el plan y ciclo de facturación
     */
    private function calculateAmount(int $planId, string $billingCycle): float
    {
        $plan = $this->db->query("SELECT monthly_price, annual_price FROM subscription_plans WHERE id = ?", [$planId])->fetch();
        
        if (!$plan) {
            return 0;
        }
        
        return match ($billingCycle) {
            'annual' => (float) $plan['annual_price'],
            'quarterly' => (float) $plan['monthly_price'] * 3,
            'semiannual' => (float) $plan['monthly_price'] * 6,
            default => (float) $plan['monthly_price'],
        };
    }
    
    /**
     * Lista todas las ventas de licencias
     */
    public function index(): void
    {
        $action = $this->request->post('action') ?? $this->request->get('action');
        
        if ($this->request->method() === 'POST') {
            if ($action === 'create') {
                $this->createSale();
                return;
            }
            
            if ($action === 'edit') {
                $this->editSale();
                return;
            }
            
            if ($action === 'delete') {
                $this->deleteSale();
                return;
            }
            
            if ($action === 'payment') {
                $this->registerPayment();
                return;
            }
        }
        
        $sales = $this->db->query("
            SELECT ls.*,
                   t.company_name,
                   t.email as tenant_email,
                   t.status as tenant_status,
                   sp.name as plan_name,
                   sp.monthly_price,
                   sp.annual_price,
                   COALESCE(SUM(lp.amount), 0) as paid_amount
            FROM license_sales ls
            LEFT JOIN tenants t ON ls.tenant_id = t.id
            LEFT JOIN subscription_plans sp ON ls.plan_id = sp.id
            LEFT JOIN license_payments lp ON ls.id = lp.license_sale_id
            GROUP BY ls.id
            ORDER BY ls.created_at DESC
        ")->fetchAll();
        
        $tenants = $this->db->query("
            SELECT id, company_name, email, status
            FROM tenants
            ORDER BY company_name ASC
        ")->fetchAll();
        
        $plans = $this->db->query("SELECT * FROM subscription_plans WHERE status = 'active'")->fetchAll();
        
        $stats = [
            'total_sales' => $this->db->query("SELECT COUNT(*) as count FROM license_sales WHERE status = 'active'")->fetch()['count'],
            'total_revenue' => $this->db->query("SELECT COALESCE(SUM(amount), 0) as total FROM license_sales WHERE status = 'active'")->fetch()['total'],
            'pending_payments' => $this->db->query("SELECT COUNT(*) as count FROM license_sales WHERE payment_status = 'pending'")->fetch()['count'],
            'active_subscriptions' => $this->db->query("SELECT COUNT(*) as count FROM license_sales WHERE status = 'active' AND end_date >= CURDATE()")->fetch()['count'],
        ];
        
        $this->view('superadmin.licencias', [
            'sales' => $sales,
            'tenants' => $tenants,
            'plans' => $plans,
            'stats' => $stats
        ]);
    }
    
    /**
     * Crea una nueva venta de licencia
     */
    private function createSale(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/licencias')) {
            return;
        }
        
        $tenantId = $this->request->post('tenant_id') ? (int) $this->request->post('tenant_id') : null;
        $planId = (int) $this->request->post('plan_id');
        $saleDate = $this->request->post('sale_date') ?: date('Y-m-d');
        $startDate = $this->request->post('start_date') ?: date('Y-m-d');
        $billingCycle = $this->request->post('billing_cycle') ?: 'monthly';
        $paymentStatus = $this->request->post('payment_status') ?: 'pending';
        $paymentMethod = $this->request->post('payment_method') ?: 'other';
        $referenceNumber = $this->request->post('reference_number');
        $notes = $this->request->post('notes');
        
        if ($planId <= 0) {
            $this->respond(false, 'Debe seleccionar un plan', '/superadmin/licencias');
            return;
        }
        
        $endDate = $this->calculateEndDate($startDate, $billingCycle);
        $amount = $this->calculateAmount($planId, $billingCycle);
        $saleCode = $this->generateSaleCode();
        $createdBy = $_SESSION['super_admin_id'] ?? null;
        
        try {
            $this->db->query("
                INSERT INTO license_sales
                (tenant_id, plan_id, sale_code, sale_date, start_date, end_date, billing_cycle, amount, payment_status, payment_method, reference_number, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $tenantId, $planId, $saleCode, $saleDate, $startDate, $endDate, $billingCycle, $amount,
                $paymentStatus, $paymentMethod, $referenceNumber, $notes, $createdBy
            ]);
            
            $saleId = $this->db->lastInsertId();
            
            // Si el pago es completo, registrar el pago automáticamente
            if ($paymentStatus === 'paid') {
                $this->db->query("
                    INSERT INTO license_payments (license_sale_id, payment_date, amount, payment_method, reference_number, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ", [$saleId, $saleDate, $amount, $paymentMethod, $referenceNumber, $notes, $createdBy]);
            }
            
            AuditService::log(
                'create',
                'license_sales',
                "Venta de licencia creada: {$saleCode} por \${$amount}",
                $tenantId
            );
            
            $this->respond(true, 'Venta de licencia creada exitosamente', '/superadmin/licencias');
        } catch (\Exception $e) {
            $this->respond(false, 'Error al crear la venta: ' . $e->getMessage(), '/superadmin/licencias');
        }
    }
    
    /**
     * Edita una venta de licencia existente
     */
    private function editSale(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/licencias')) {
            return;
        }
        
        $id = (int) $this->request->post('id');
        
        $sale = $this->db->query("SELECT * FROM license_sales WHERE id = ?", [$id])->fetch();
        
        if (!$sale) {
            $this->respond(false, 'Venta no encontrada', '/superadmin/licencias');
            return;
        }
        
        $tenantId = $this->request->post('tenant_id') ? (int) $this->request->post('tenant_id') : null;
        $planId = (int) $this->request->post('plan_id');
        $saleDate = $this->request->post('sale_date');
        $startDate = $this->request->post('start_date');
        $billingCycle = $this->request->post('billing_cycle');
        $paymentStatus = $this->request->post('payment_status');
        $paymentMethod = $this->request->post('payment_method');
        $referenceNumber = $this->request->post('reference_number');
        $notes = $this->request->post('notes');
        $status = $this->request->post('status');
        
        $endDate = $this->calculateEndDate($startDate, $billingCycle);
        $amount = $this->calculateAmount($planId, $billingCycle);
        
        try {
            $this->db->query("
                UPDATE license_sales
                SET tenant_id = ?, plan_id = ?, sale_date = ?, start_date = ?, end_date = ?,
                    billing_cycle = ?, amount = ?, payment_status = ?, payment_method = ?,
                    reference_number = ?, notes = ?, status = ?
                WHERE id = ?
            ", [
                $tenantId, $planId, $saleDate, $startDate, $endDate, $billingCycle, $amount,
                $paymentStatus, $paymentMethod, $referenceNumber, $notes, $status, $id
            ]);
            
            AuditService::log(
                'update',
                'license_sales',
                "Venta de licencia actualizada ID: {$id}",
                $tenantId
            );
            
            $this->respond(true, 'Venta de licencia actualizada exitosamente', '/superadmin/licencias');
        } catch (\Exception $e) {
            $this->respond(false, 'Error al actualizar la venta: ' . $e->getMessage(), '/superadmin/licencias');
        }
    }
    
    /**
     * Elimina una venta de licencia
     */
    private function deleteSale(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/licencias')) {
            return;
        }
        
        $id = (int) $this->request->post('id');
        
        $sale = $this->db->query("SELECT * FROM license_sales WHERE id = ?", [$id])->fetch();
        
        if (!$sale) {
            $this->respond(false, 'Venta no encontrada', '/superadmin/licencias');
            return;
        }
        
        $this->db->query("DELETE FROM license_sales WHERE id = ?", [$id]);
        
        AuditService::log(
            'delete',
            'license_sales',
            "Venta de licencia eliminada ID: {$id}",
            $sale['tenant_id']
        );
        
        $this->respond(true, 'Venta de licencia eliminada exitosamente', '/superadmin/licencias');
    }
    
    /**
     * Registra un pago para una venta de licencia
     */
    private function registerPayment(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/licencias')) {
            return;
        }
        
        $saleId = (int) $this->request->post('sale_id');
        $amount = (float) $this->request->post('amount');
        $paymentDate = $this->request->post('payment_date') ?: date('Y-m-d');
        $paymentMethod = $this->request->post('payment_method') ?: 'other';
        $referenceNumber = $this->request->post('reference_number');
        $notes = $this->request->post('notes');
        $createdBy = $_SESSION['super_admin_id'] ?? null;
        
        $sale = $this->db->query("SELECT * FROM license_sales WHERE id = ?", [$saleId])->fetch();
        
        if (!$sale) {
            $this->respond(false, 'Venta no encontrada', '/superadmin/licencias');
            return;
        }
        
        if ($amount <= 0) {
            $this->respond(false, 'El monto del pago debe ser mayor a cero', '/superadmin/licencias');
            return;
        }
        
        try {
            $this->db->query("
                INSERT INTO license_payments (license_sale_id, payment_date, amount, payment_method, reference_number, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [$saleId, $paymentDate, $amount, $paymentMethod, $referenceNumber, $notes, $createdBy]);
            
            // Actualizar estado de pago
            $totalPaid = $this->db->query("
                SELECT COALESCE(SUM(amount), 0) as total
                FROM license_payments
                WHERE license_sale_id = ?
            ", [$saleId])->fetch()['total'];
            
            $newStatus = 'pending';
            if ($totalPaid >= $sale['amount']) {
                $newStatus = 'paid';
            } elseif ($totalPaid > 0) {
                $newStatus = 'partial';
            }
            
            $this->db->query("UPDATE license_sales SET payment_status = ? WHERE id = ?", [$newStatus, $saleId]);
            
            AuditService::log(
                'create',
                'license_payments',
                "Pago registrado por \${$amount} para venta ID: {$saleId}",
                $sale['tenant_id']
            );
            
            $this->respond(true, 'Pago registrado exitosamente', '/superadmin/licencias');
        } catch (\Exception $e) {
            $this->respond(false, 'Error al registrar el pago: ' . $e->getMessage(), '/superadmin/licencias');
        }
    }
}
