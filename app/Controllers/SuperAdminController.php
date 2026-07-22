<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\Database;
use SoftNova\Core\Middleware;
use SoftNova\Core\TenantDatabase;
use SoftNova\Services\AuditService;

/**
 * Controlador del Super Administrador
 */
class SuperAdminController extends Controller
{
    private Database $db;
    
    public function __construct()
    {
        parent::__construct();
        Middleware::auth();
        $this->db = Database::getInstance();
    }
    
    public function index(): void
    {
        $cache = new \SoftNova\Core\Cache();
        $data = $cache->remember('superadmin_dashboard', 300, function() {
            return [
                'stats' => [
                    'total_tenants' => $this->db->query("SELECT COUNT(*) as count FROM tenants")->fetch()['count'],
                    'active_tenants' => $this->db->query("SELECT COUNT(*) as count FROM tenants WHERE status = 'active'")->fetch()['count'],
                    'suspended_tenants' => $this->db->query("SELECT COUNT(*) as count FROM tenants WHERE status = 'suspended'")->fetch()['count'],
                    'total_plans' => $this->db->query("SELECT COUNT(*) as count FROM subscription_plans")->fetch()['count'],
                    'total_users' => $this->db->query("SELECT COUNT(*) as count FROM tenant_users")->fetch()['count'],
                    'open_tickets' => $this->db->query("SELECT COUNT(*) as c FROM tickets WHERE status = 'open'")->fetch()['c'],
                    'in_progress_tickets' => $this->db->query("SELECT COUNT(*) as c FROM tickets WHERE status = 'in_progress'")->fetch()['c'],
                    'urgent_tickets' => $this->db->query("SELECT COUNT(*) as c FROM tickets WHERE priority = 'urgent' AND status IN ('open','in_progress')")->fetch()['c'],
                    'active_licenses' => $this->db->query("SELECT COUNT(*) as c FROM license_sales WHERE status='active'")->fetch()['c'],
                    'total_revenue' => $this->db->query("SELECT COALESCE(SUM(amount),0) as t FROM license_sales WHERE status='active'")->fetch()['t'],
                    'pending_payments' => $this->db->query("SELECT COUNT(*) as c FROM license_sales WHERE payment_status='pending'")->fetch()['c'],
                    'active_subscriptions' => $this->db->query("SELECT COUNT(*) as c FROM license_sales WHERE status='active' AND end_date>=CURDATE()")->fetch()['c'],
                ]
            ];
        });
        
        $recentActivity = $this->db->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10")->fetchAll();
        $expiringTenants = $this->db->query("SELECT t.company_name, t.subscription_end_date, sp.name as plan_name FROM tenants t LEFT JOIN subscription_plans sp ON t.subscription_plan_id=sp.id WHERE t.status='active' AND t.subscription_end_date IS NOT NULL AND t.subscription_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) ORDER BY t.subscription_end_date ASC LIMIT 5")->fetchAll();
        
        $this->view('superadmin.dashboard', ['stats'=>$data['stats'], 'recentActivity'=>$recentActivity, 'expiringTenants'=>$expiringTenants]);
    }
    
    public function tenants(): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'create') {
            $this->createTenant();
            return;
        }
        
        if ($action === 'edit') {
            $this->editTenant();
            return;
        }
        
        if ($action === 'delete') {
            $this->deleteTenant();
            return;
        }
        
        if ($action === 'toggle_status') {
            $this->toggleTenantStatus();
            return;
        }
        
        $this->checkExpiredPlans();
        
        $tenants = $this->db->query("
            SELECT t.*, sp.name as plan_name, sp.monthly_price
            FROM tenants t
            LEFT JOIN subscription_plans sp ON t.subscription_plan_id = sp.id
            ORDER BY t.created_at DESC
        ")->fetchAll();
        
        $plans = $this->db->query("SELECT * FROM subscription_plans WHERE status = 'active'")->fetchAll();
        
        $this->view('superadmin.tenants', ['tenants' => $tenants, 'plans' => $plans]);
    }
    
    /**
     * Verifica planes vencidos y suspende el acceso automaticamente
     */
    private function checkExpiredPlans(): void
    {
        $today = date('Y-m-d');
        
        $this->db->query("
            UPDATE tenants
            SET status = 'suspended', updated_at = NOW()
            WHERE status = 'active'
              AND subscription_end_date IS NOT NULL
              AND subscription_end_date < ?
        ", [$today]);
    }
    
    /**
     * Habilita o cancela el acceso de un cliente
     */
    private function toggleTenantStatus(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/tenants')) {
            return;
        }
        
        $id = (int) $this->request->post('id');
        
        $tenant = $this->db->query("SELECT * FROM tenants WHERE id = ?", [$id])->fetch();
        
        if (!$tenant) {
            $this->respond(false, 'Cliente no encontrado', '/superadmin/tenants');
            return;
        }
        
        $newStatus = $tenant['status'] === 'cancelled' ? 'active' : 'cancelled';
        
        $this->db->query("UPDATE tenants SET status = ?, updated_at = NOW() WHERE id = ?", [$newStatus, $id]);
        
        $actionText = $newStatus === 'active' ? 'habilitado' : 'cancelado';
        
        AuditService::log(
            'update',
            'tenants',
            "Acceso {$actionText} para el cliente ID: {$id}",
            $id
        );
        
        $this->respond(true, "Acceso {$actionText} exitosamente", '/superadmin/tenants');
    }
    
    private function createTenant(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/tenants')) {
            return;
        }
        
        $name = $this->request->post('name');
        $razon_social = $this->request->post('razon_social');
        $documento_tipo = $this->request->post('documento_tipo');
        $documento_numero = $this->request->post('documento_numero');
        $email = $this->request->post('email');
        $phone = $this->request->post('phone');
        $address = $this->request->post('address');
        $subscription_plan_id = $this->request->post('plan_id');
        $billing_cycle = $this->request->post('billing_cycle');
        
        if (empty($name) || empty($email) || empty($subscription_plan_id)) {
            $this->respond(false, 'Por favor complete todos los campos requeridos', '/superadmin/tenants');
            return;
        }
        
        $existingTenant = $this->db->query("SELECT id FROM tenants WHERE email = ? LIMIT 1", [$email])->fetch();
        if ($existingTenant) {
            $this->respond(false, 'Ya existe un cliente registrado con este email', '/superadmin/tenants');
            return;
        }
        
        $plan = $this->db->query("SELECT * FROM subscription_plans WHERE id = ?", [$subscription_plan_id])->fetch();
        
        $price = $billing_cycle === 'annual' ? $plan['annual_price'] : $plan['monthly_price'];
        $subscription_start_date = date('Y-m-d');
        $subscription_end_date = $billing_cycle === 'annual'
            ? date('Y-m-d', strtotime('+1 year'))
            : date('Y-m-d', strtotime('+1 month'));
        
        // Generar nombre de base de datos único
        $company_slug = strtolower(preg_replace('/[^a-z0-9]+/', '_', $name));
        $database_name = 'softnova_' . $company_slug . '_' . time();
        
        // Generar credenciales de base de datos (criptográficamente seguras)
        $database_user = 'tn_' . bin2hex(random_bytes(6));
        $database_password = bin2hex(random_bytes(16));
        
        $this->db->query("
            INSERT INTO tenants (company_name, razon_social, documento_tipo, documento_numero, email, phone, address, database_name, database_host, database_port, database_user, database_password, subscription_plan_id, subscription_start_date, subscription_end_date, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'localhost', 3306, ?, ?, ?, ?, ?, 'active', NOW())
        ", [$name, $razon_social, $documento_tipo, $documento_numero, $email, $phone, $address, $database_name, $database_user, $database_password, $subscription_plan_id, $subscription_start_date, $subscription_end_date]);
        
        $tenant_id = $this->db->lastInsertId();
        
        // Crear base de datos automáticamente para el tenant
        $tenantDb = TenantDatabase::getInstance();
        $dbCreated = $tenantDb->createTenantDatabase($database_name, $database_user, $database_password);
        
        if (!$dbCreated) {
            // Si falla la creación de BD, eliminar el tenant y notificar
            $this->db->query("DELETE FROM tenants WHERE id = ?", [$tenant_id]);
            $this->respond(false, 'Error al crear la base de datos del cliente. Verifique los permisos de MySQL.', '/superadmin/tenants');
            return;
        }
        
        AuditService::log(
            'create',
            'tenants',
            "Cliente creado: {$name} (ID: {$tenant_id}, BD: {$database_name})",
            (int) $tenant_id
        );
        
        $this->respond(true, 'Cliente creado exitosamente. Base de datos configurada.', '/superadmin/tenants');
    }
    
    private function editTenant(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/tenants')) {
            return;
        }
        
        $id = $this->request->post('id');
        
        $name = $this->request->post('name');
        $razon_social = $this->request->post('razon_social');
        $documento_tipo = $this->request->post('documento_tipo');
        $documento_numero = $this->request->post('documento_numero');
        $email = $this->request->post('email');
        $phone = $this->request->post('phone');
        $address = $this->request->post('address');
        $subscription_plan_id = $this->request->post('plan_id');
        $status = $this->request->post('status');
        
        if (empty($name) || empty($email) || empty($subscription_plan_id)) {
            $this->respond(false, 'Por favor complete todos los campos requeridos', '/superadmin/tenants');
            return;
        }
        
        $existingTenant = $this->db->query("SELECT id FROM tenants WHERE email = ? AND id != ? LIMIT 1", [$email, $id])->fetch();
        if ($existingTenant) {
            $this->respond(false, 'Ya existe otro cliente registrado con este email', '/superadmin/tenants');
            return;
        }
        
        $this->db->query("
            UPDATE tenants
            SET company_name = ?, razon_social = ?, documento_tipo = ?, documento_numero = ?, email = ?, phone = ?, address = ?, subscription_plan_id = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ", [$name, $razon_social, $documento_tipo, $documento_numero, $email, $phone, $address, $subscription_plan_id, $status, $id]);
        
        AuditService::log(
            'update',
            'tenants',
            "Cliente actualizado: {$name} (ID: {$id})",
            (int) $id
        );
        
        $this->respond(true, 'Cliente actualizado exitosamente', '/superadmin/tenants');
    }
    
    private function deleteTenant(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/tenants')) {
            return;
        }
        
        $id = $this->request->post('id');
        
        AuditService::log(
            'delete',
            'tenants',
            "Cliente eliminado (ID: {$id})",
            (int) $id
        );
        
        $this->db->query("DELETE FROM tenants WHERE id = ?", [$id]);
        
        $this->respond(true, 'Cliente eliminado exitosamente', '/superadmin/tenants');
    }
    
    public function plans(): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'create') {
            $this->createPlan();
            return;
        }
        
        if ($action === 'edit') {
            $this->editPlan();
            return;
        }
        
        if ($action === 'delete') {
            $this->deletePlan();
            return;
        }
        
        $plans = $this->db->query("SELECT * FROM subscription_plans ORDER BY monthly_price ASC")->fetchAll();
        
        $this->view('superadmin.plans', ['plans' => $plans]);
    }
    
    private function createPlan(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/plans')) {
            return;
        }
        
        $name = $this->request->post('name');
        $description = $this->request->post('description');
        $monthly_price = $this->request->post('monthly_price');
        $semiannual_price = $this->request->post('semiannual_price');
        $annual_price = $this->request->post('annual_price');
        $max_users = $this->request->post('max_users');
        $max_products = $this->request->post('max_products');
        $modules = json_encode($this->request->post('modules', []));
        
        if (empty($name) || empty($monthly_price) || empty($annual_price)) {
            $this->respond(false, 'Por favor complete todos los campos requeridos', '/superadmin/plans');
            return;
        }
        
        $this->db->query("
            INSERT INTO subscription_plans (name, description, monthly_price, semiannual_price, annual_price, max_users, max_products, modules, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ", [$name, $description, $monthly_price, $semiannual_price ?: null, $annual_price, $max_users, $max_products, $modules]);
        
        AuditService::log(
            'create',
            'plans',
            "Plan creado: {$name}"
        );
        
        $this->respond(true, 'Plan creado exitosamente', '/superadmin/plans');
    }
    
    private function editPlan(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/plans')) {
            return;
        }
        
        $id = $this->request->post('id');
        
        $name = $this->request->post('name');
        $description = $this->request->post('description');
        $monthly_price = $this->request->post('monthly_price');
        $annual_price = $this->request->post('annual_price');
        $max_users = $this->request->post('max_users');
        $max_products = $this->request->post('max_products');
        $modules = json_encode($this->request->post('modules', []));
        $status = $this->request->post('status');
        
        if (empty($name) || empty($monthly_price) || empty($annual_price)) {
            $this->respond(false, 'Por favor complete todos los campos requeridos', '/superadmin/plans');
            return;
        }
        
        $this->db->query("
            UPDATE subscription_plans
            SET name = ?, description = ?, monthly_price = ?, annual_price = ?, max_users = ?, max_products = ?, modules = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ", [$name, $description, $monthly_price, $annual_price, $max_users, $max_products, $modules, $status, $id]);
        
        AuditService::log(
            'update',
            'plans',
            "Plan actualizado: {$name} (ID: {$id})"
        );
        
        $this->respond(true, 'Plan actualizado exitosamente', '/superadmin/plans');
    }
    
    private function deletePlan(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/plans')) {
            return;
        }
        
        $id = $this->request->post('id');
        
        AuditService::log(
            'delete',
            'plans',
            "Plan eliminado (ID: {$id})"
        );
        
        $this->db->query("DELETE FROM subscription_plans WHERE id = ?", [$id]);
        
        $this->respond(true, 'Plan eliminado exitosamente', '/superadmin/plans');
    }
    
    public function audits(): void
    {
        $audits = $this->db->query("
            SELECT * FROM audit_logs
            ORDER BY created_at DESC
            LIMIT 100
        ")->fetchAll();
        
        $this->view('superadmin.audits', ['audits' => $audits]);
    }
    
    public function tenantUsers(int $tenant_id): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'create') {
            $this->createTenantUser($tenant_id);
            return;
        }
        
        if ($action === 'delete') {
            $this->deleteTenantUser();
            return;
        }
        
        $tenant = $this->db->query("SELECT * FROM tenants WHERE id = ?", [$tenant_id])->fetch();
        $users = $this->db->query("SELECT * FROM tenant_users WHERE tenant_id = ? ORDER BY created_at DESC", [$tenant_id])->fetchAll();
        
        $this->view('superadmin.tenant_users', ['tenant' => $tenant, 'users' => $users]);
    }
    
    private function createTenantUser(int $tenant_id): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/tenants/' . $tenant_id . '/users')) {
            return;
        }
        
        $name = $this->request->post('name');
        $email = $this->request->post('email');
        $password = $this->request->post('password');
        $role = $this->request->post('role');
        $permissions = $this->request->post('permissions', []);
        
        if (empty($name) || empty($email) || empty($password)) {
            $this->respond(false, 'Por favor complete todos los campos requeridos', '/superadmin/tenants/' . $tenant_id . '/users');
            return;
        }
        
        if (strlen($password) < 8) {
            $this->respond(false, 'La contraseña debe tener al menos 8 caracteres', '/superadmin/tenants/' . $tenant_id . '/users');
            return;
        }
        
        $tenant = $this->db->query("SELECT * FROM tenants WHERE id = ?", [$tenant_id])->fetch();
        $plan = $this->db->query("SELECT * FROM subscription_plans WHERE id = ?", [$tenant['subscription_plan_id'] ?? 0])->fetch();
        $maxUsers = $plan['max_users'] ?? 0;
        
        $currentUsers = $this->db->query("SELECT COUNT(*) as count FROM tenant_users WHERE tenant_id = ?", [$tenant_id])->fetch()['count'];
        
        if ($maxUsers > 0 && $currentUsers >= $maxUsers) {
            $this->respond(false, "Este cliente ha alcanzado el limite de usuarios permitidos por su plan ({$maxUsers})", '/superadmin/tenants/' . $tenant_id . '/users');
            return;
        }
        
        $hashed_password = password_hash($password, PASSWORD_ARGON2ID);
        
        $processedPermissions = [];
        if ($role === 'user' && is_array($permissions)) {
            foreach ($permissions as $module => $actions) {
                $processedPermissions[$module] = [
                    'view' => !empty($actions['view']),
                    'edit' => !empty($actions['edit']),
                ];
            }
        }
        
        $permissionsJson = json_encode($processedPermissions);
        
        $this->db->query("
            INSERT INTO tenant_users (tenant_id, name, email, password, role, permissions, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
        ", [$tenant_id, $name, $email, $hashed_password, $role, $permissionsJson]);
        
        AuditService::log(
            'create',
            'tenant_users',
            "Usuario creado: {$name} ({$email}) para el cliente ID: {$tenant_id}",
            $tenant_id
        );
        
        $this->respond(true, 'Usuario creado exitosamente', '/superadmin/tenants/' . $tenant_id . '/users');
    }
    
    private function deleteTenantUser(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/tenants/' . ($_POST['tenant_id'] ?? '') . '/users')) {
            return;
        }
        
        $id = $this->request->post('id');
        $tenant_id = $this->request->post('tenant_id');
        
        AuditService::log(
            'delete',
            'tenant_users',
            "Usuario eliminado (ID: {$id}) del cliente ID: {$tenant_id}",
            (int) $tenant_id
        );
        
        $this->db->query("DELETE FROM tenant_users WHERE id = ?", [$id]);
        
        $this->respond(true, 'Usuario eliminado exitosamente', '/superadmin/tenants/' . $tenant_id . '/users');
    }
    
    public function settings(): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'change_password') {
            $this->changePassword();
            return;
        }
        
        $this->view('superadmin.settings');
    }
    
    private function changePassword(): void
    {
        if (!$this->validateCsrfOrFail('/superadmin/settings')) {
            return;
        }
        
        $current_password = $this->request->post('current_password');
        $new_password = $this->request->post('new_password');
        $confirm_password = $this->request->post('confirm_password');
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $this->respond(false, 'Por favor complete todos los campos', '/superadmin/settings');
            return;
        }
        
        if ($new_password !== $confirm_password) {
            $this->respond(false, 'Las contraseñas nuevas no coinciden', '/superadmin/settings');
            return;
        }
        
        if (strlen($new_password) < 8) {
            $this->respond(false, 'La contraseña debe tener al menos 8 caracteres', '/superadmin/settings');
            return;
        }
        
        $admin_email = $_SESSION['super_admin_email'] ?? '';
        $admin = $this->db->query("SELECT * FROM super_admin_users WHERE email = ?", [$admin_email])->fetch();
        
        if (!$admin || !password_verify($current_password, $admin['password'])) {
            $this->respond(false, 'La contraseña actual es incorrecta', '/superadmin/settings');
            return;
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_ARGON2ID);
        
        $this->db->query("UPDATE super_admin_users SET password = ?, updated_at = NOW() WHERE email = ?", [$hashed_password, $admin_email]);
        
        AuditService::log(
            'update',
            'settings',
            'Contraseña de Super Admin actualizada'
        );
        
        $this->respond(true, 'Contraseña cambiada exitosamente', '/superadmin/settings');
    }
    
    /**
     * Búsqueda global: tenants, tickets y licencias
     * Retorna JSON para el buscador del header
     */
    public function search(): void
    {
        try {
            $q = trim($this->request->get('q', ''));
            
            if (strlen($q) < 2) {
                $this->json(['results' => []]);
                return;
            }
            
            $like = '%' . $q . '%';
            $results = [];
            
            // Buscar tenants (clientes)
            $tenants = $this->db->query(
                "SELECT id, company_name as title, email as subtitle, 'tenant' as type,
                        '/superadmin/tenants' as url
                 FROM tenants
                 WHERE company_name LIKE ? OR email LIKE ? OR IFNULL(razon_social, '') LIKE ?
                 LIMIT 5",
                [$like, $like, $like]
            )->fetchAll();
            
            foreach ($tenants as $t) {
                $t['badge'] = 'Cliente';
                $t['badgeClass'] = 'badge-success';
                $results[] = $t;
            }
            
            // Buscar tickets
            $tickets = $this->db->query(
                "SELECT t.id, t.subject as title, t.ticket_code as subtitle, 'ticket' as type,
                        CONCAT('/superadmin/tickets/view/', t.id) as url,
                        t.status, t.priority
                 FROM tickets t
                 WHERE t.subject LIKE ? OR t.ticket_code LIKE ?
                 LIMIT 5",
                [$like, $like]
            )->fetchAll();
            
            foreach ($tickets as $tk) {
                $statusLabels = ['open' => 'Abierto', 'in_progress' => 'En Progreso', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];
                $statusBadges = ['open' => 'badge-danger', 'in_progress' => 'badge-warning', 'resolved' => 'badge-success', 'closed' => 'badge-info'];
                $tk['badge'] = $statusLabels[$tk['status']] ?? ucfirst($tk['status']);
                $tk['badgeClass'] = $statusBadges[$tk['status']] ?? 'badge-info';
                $results[] = $tk;
            }
            
            // Buscar licencias
            $licenses = $this->db->query(
                "SELECT ls.id, ls.sale_code as title,
                        COALESCE(t.company_name, 'Sin cliente') as subtitle,
                        'license' as type,
                        '/superadmin/licencias' as url,
                        ls.payment_status
                 FROM license_sales ls
                 LEFT JOIN tenants t ON ls.tenant_id = t.id
                 WHERE ls.sale_code LIKE ? OR t.company_name LIKE ?
                 LIMIT 5",
                [$like, $like]
            )->fetchAll();
            
            foreach ($licenses as $lic) {
                $lic['badge'] = $lic['payment_status'] === 'paid' ? 'Pagado' : 'Pendiente';
                $lic['badgeClass'] = $lic['payment_status'] === 'paid' ? 'badge-success' : 'badge-warning';
                $results[] = $lic;
            }
            
            $this->json(['results' => $results]);
        } catch (\Exception $e) {
            error_log('Search error: ' . $e->getMessage());
            $this->json(['results' => [], 'error' => $e->getMessage()]);
        }
    }
}