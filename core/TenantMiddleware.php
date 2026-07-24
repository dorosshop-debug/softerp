<?php

namespace SoftNova\Core;

/**
 * Middleware para verificar autenticación de tenant
 * y configurar conexión a su base de datos
 */
class TenantMiddleware
{
    /**
     * Verificar que el usuario esté autenticado como tenant
     */
    public static function auth(): bool
    {
        if (empty($_SESSION['tenant_authenticated'])) {
            redirect('/app/login');
            return false;
        }
        return true;
    }
    
    /**
     * Obtener conexión PDO a la base de datos del tenant actual
     */
    public static function getDb(): \PDO
    {
        $tenantDb = TenantDatabase::getInstance();
        
        return $tenantDb->getTenantConnection(
            (string)($_SESSION['tenant_db_name'] ?? ''),
            (string)($_SESSION['tenant_db_user'] ?? ''),
            (string)($_SESSION['tenant_db_pass'] ?? '')
        );
    }
    
    /**
     * Verificar que el tenant tenga acceso a un módulo específico
     */
    public static function hasModule(string $module): bool
    {
        // Los admins del tenant tienen acceso a todo
        if (($_SESSION['tenant_user_role'] ?? '') === 'admin') {
            return true;
        }
        
        // Verificar permisos del usuario
        $permissions = $_SESSION['tenant_permissions'] ?? [];
        return in_array($module, $permissions);
    }
    
    /**
     * Mapa de permisos por rol para módulos del tenant
     */
    private static function rolePermissions(): array
    {
        $admin = [
            'modules' => ['dashboard', 'caja', 'ventas', 'inventario', 'clientes', 'proveedores', 'cotizaciones', 'gastos', 'contabilidad', 'reportes', 'configuracion'],
            'actions' => ['create', 'edit', 'delete', 'view', 'export'],
        ];
        $manager = [
            'modules' => ['dashboard', 'caja', 'ventas', 'inventario', 'clientes', 'proveedores', 'cotizaciones', 'gastos', 'contabilidad', 'reportes'],
            'actions' => ['create', 'edit', 'delete', 'view', 'export'],
        ];
        
        return [
            'admin' => $admin,
            // Rol master tenant_users.role = 'user' (mapa a permisos operativos)
            'user' => $manager,
            'manager' => $manager,
            'cashier' => [
                'modules' => ['dashboard', 'caja', 'ventas', 'clientes', 'gastos'],
                'actions' => ['create', 'view'],
            ],
            'viewer' => [
                'modules' => ['dashboard', 'reportes'],
                'actions' => ['view'],
            ],
            // Auxiliar / Mesero: ventas (crear, sin eliminar) + inventario (solo ver / carrito)
            'auxiliar' => [
                'modules' => ['ventas', 'inventario'],
                'actions' => ['view'],
                'module_actions' => [
                    'ventas' => ['view', 'create'],
                    'inventario' => ['view'],
                ],
            ],
            'mesero' => [
                'modules' => ['ventas', 'inventario'],
                'actions' => ['view'],
                'module_actions' => [
                    'ventas' => ['view', 'create'],
                    'inventario' => ['view'],
                ],
            ],
        ];
    }
    
    /**
     * Obtener el rol del usuario actual
     */
    public static function getRole(): string
    {
        $role = strtolower(trim((string)($_SESSION['tenant_user_role'] ?? 'viewer')));
        // Compatibilidad: roles legacy / vacios / alias
        if ($role === '' || $role === 'administrator') {
            $role = 'admin';
        }
        if ($role === 'waiter' || $role === 'auxiliar_mesero' || $role === 'auxiliar/mesero') {
            $role = 'auxiliar';
        }
        return $role;
    }
    
    /**
     * Home segun rol (evitar redirigir a modulos sin acceso)
     */
    public static function homePath(): string
    {
        $role = self::getRole();
        if (in_array($role, ['auxiliar', 'mesero'], true)) {
            return '/app/ventas';
        }
        return '/app/dashboard';
    }
    
    /**
     * Verificar si el usuario puede acceder a un módulo
     */
    public static function canAccess(string $module): bool
    {
        $role = self::getRole();
        $perms = self::rolePermissions();
        
        if (!isset($perms[$role])) {
            return false;
        }
        
        return in_array($module, $perms[$role]['modules'], true);
    }
    
    /**
     * Verificar si el usuario puede realizar una acción
     * Si se indica $module, usa module_actions del rol cuando existan
     */
    public static function canDo(string $action, ?string $module = null): bool
    {
        $role = self::getRole();
        $perms = self::rolePermissions();
        
        if (!isset($perms[$role])) {
            return false;
        }
        
        if ($module !== null && !empty($perms[$role]['module_actions'][$module])) {
            return in_array($action, $perms[$role]['module_actions'][$module], true);
        }
        
        return in_array($action, $perms[$role]['actions'], true);
    }
    
    /**
     * Verificar acceso a módulo y acción, redirigir si no tiene permiso
     */
    public static function authorize(string $module, string $action = 'view'): bool
    {
        if (!self::canAccess($module)) {
            self::deny('No tienes acceso a este modulo', self::homePath());
            return false;
        }
        
        if (!self::canDo($action, $module)) {
            self::deny('No tienes permisos para realizar esta accion', '/app/' . ltrim($module, '/'));
            return false;
        }
        
        return true;
    }
    
    private static function deny(string $message, string $redirect): void
    {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $wantsJson = $isAjax
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string)$_SERVER['HTTP_ACCEPT'], 'application/json'));
        
        if ($wantsJson) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => $message,
                'redirect' => '',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $_SESSION['error'] = $message;
        redirect($redirect);
    }
    
    /**
     * Contexto del plan del tenant (tier + features)
     * Prioridad: columna features JSON > nombre del plan > precio
     */
    public static function getPlanContext(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        
        $default = [
            'tier' => 'basic',
            'reports' => 'basic',
            'export' => false,
            'plan_name' => 'Plan Basico',
            'upgrade_plans' => 'Pro o Premium',
        ];
        
        $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            return $cache = $default;
        }
        
        try {
            $db = Database::getInstance();
            $row = null;
            try {
                $row = $db->query(
                    "SELECT sp.name, sp.monthly_price, sp.features, sp.modules
                     FROM tenants t
                     JOIN subscription_plans sp ON t.subscription_plan_id = sp.id
                     WHERE t.id = ? LIMIT 1",
                    [$tenantId]
                )->fetch();
            } catch (\Throwable $e) {
                // Columna features puede no existir aun
                $row = $db->query(
                    "SELECT sp.name, sp.monthly_price, sp.modules
                     FROM tenants t
                     JOIN subscription_plans sp ON t.subscription_plan_id = sp.id
                     WHERE t.id = ? LIMIT 1",
                    [$tenantId]
                )->fetch();
            }
            
            if (!$row) {
                return $cache = $default;
            }
            
            $planName = (string)($row['name'] ?? 'Plan');
            $features = [];
            if (!empty($row['features'])) {
                $decoded = is_array($row['features'])
                    ? $row['features']
                    : json_decode((string)$row['features'], true);
                if (is_array($decoded)) {
                    $features = $decoded;
                }
            }
            
            if (!empty($features['reports']) || !empty($features['tier'])) {
                $tier = strtolower((string)($features['tier'] ?? 'basic'));
                $reports = strtolower((string)($features['reports'] ?? 'basic'));
                $export = array_key_exists('export', $features)
                    ? (bool)$features['export']
                    : ($reports === 'full');
                
                return $cache = [
                    'tier' => in_array($tier, ['basic', 'pro', 'premium', 'custom'], true) ? $tier : 'basic',
                    'reports' => $reports === 'full' ? 'full' : 'basic',
                    'export' => $export,
                    'plan_name' => $planName,
                    'upgrade_plans' => 'Pro o Premium',
                ];
            }
            
            $name = mb_strtolower($planName);
            $isBasic = (bool)preg_match('/b[aá]sic|basic|starter|inicio|essentials/', $name);
            $isFull = (bool)preg_match('/pro|premium|enterprise|empresarial|personal|custom|unlimited/', $name);
            
            if (!$isBasic && !$isFull) {
                $isFull = ((float)($row['monthly_price'] ?? 0)) >= 50;
            }
            
            return $cache = [
                'tier' => $isFull ? (preg_match('/premium|enterprise/', $name) ? 'premium' : (preg_match('/personal|custom/', $name) ? 'custom' : 'pro')) : 'basic',
                'reports' => $isFull ? 'full' : 'basic',
                'export' => $isFull,
                'plan_name' => $planName,
                'upgrade_plans' => 'Pro o Premium',
            ];
        } catch (\Throwable $e) {
            error_log('getPlanContext: ' . $e->getMessage());
            return $cache = $default;
        }
    }
    
    public static function hasFullReports(): bool
    {
        return self::getPlanContext()['reports'] === 'full';
    }
    
    public static function canExportReports(): bool
    {
        return !empty(self::getPlanContext()['export']);
    }
}
