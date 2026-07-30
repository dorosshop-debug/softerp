<?php

namespace SoftNova\Core;

/**
 * Middleware para verificar autenticación de tenant
 * y configurar conexión a su base de datos
 */
class TenantMiddleware
{
    /**
     * Módulos disponibles para asignar a usuarios (rol User)
     */
    public static function assignableModules(): array
    {
        return [
            'dashboard' => 'Dashboard',
            'caja' => 'Caja-POS',
            'ventas' => 'Ventas',
            'inventario' => 'Inventario',
            'compras' => 'Compras',
            'clientes' => 'Clientes',
            'proveedores' => 'Proveedores',
            'cotizaciones' => 'Cotizaciones',
            'gastos' => 'Gastos',
            'reportes' => 'Reportes',
            'contabilidad' => 'Contabilidad',
            'nomina' => 'Nómina',
        ];
    }

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
     * Verificar que el tenant tenga acceso a un módulo específico (plan o sesión)
     */
    public static function hasModule(string $module): bool
    {
        if (self::getRole() === 'admin') {
            return true;
        }
        $planModules = $_SESSION['tenant_modules'] ?? [];
        if (empty($planModules)) {
            return self::canAccess($module);
        }
        return in_array($module, $planModules, true) && self::canAccess($module);
    }

    /**
     * Etiqueta amigable del rol
     */
    public static function roleLabel(?string $role = null): string
    {
        $role = strtolower(trim((string)($role ?? self::getRole())));
        return match ($role) {
            'admin', 'administrator' => 'Administrador',
            'user', 'manager' => 'User',
            'auxiliar', 'mesero', 'pos', 'user_pos', 'waiter', 'cashier' => 'User POS',
            'viewer' => 'Solo lectura',
            default => ucfirst($role ?: 'Usuario'),
        };
    }

    public static function isAdmin(): bool
    {
        return self::getRole() === 'admin';
    }

    public static function isPosUser(): bool
    {
        return in_array(self::getRole(), ['auxiliar', 'mesero', 'pos', 'user_pos', 'cashier'], true);
    }
    
    /**
     * Mapa de permisos por rol para módulos del tenant
     */
    private static function rolePermissions(): array
    {
        $admin = [
            'modules' => ['dashboard', 'caja', 'ventas', 'inventario', 'clientes', 'proveedores', 'compras', 'cotizaciones', 'gastos', 'contabilidad', 'nomina', 'reportes', 'configuracion'],
            'actions' => ['create', 'edit', 'delete', 'view', 'export'],
        ];
        $manager = [
            'modules' => ['dashboard', 'caja', 'ventas', 'inventario', 'clientes', 'proveedores', 'compras', 'cotizaciones', 'gastos', 'contabilidad', 'nomina', 'reportes'],
            'actions' => ['create', 'edit', 'delete', 'view', 'export'],
        ];
        // User POS: solo Caja-POS para vender (sin eliminar ventas ni ver reportes financieros de tarjetas en UI)
        $pos = [
            'modules' => ['caja', 'ventas', 'clientes'],
            'actions' => ['view'],
            'module_actions' => [
                'caja' => ['view', 'create', 'edit'],
                'ventas' => ['view', 'create'],
                'clientes' => ['view', 'create'],
            ],
        ];
        
        return [
            'admin' => $admin,
            'user' => $manager,
            'manager' => $manager,
            'cashier' => $pos,
            'viewer' => [
                'modules' => ['dashboard', 'reportes'],
                'actions' => ['view'],
            ],
            'auxiliar' => $pos,
            'mesero' => $pos,
            'pos' => $pos,
            'user_pos' => $pos,
        ];
    }

    /**
     * Permisos personalizados del rol User (JSON de sesión).
     * null = usar mapa del rol; array (aunque vacío) = restricción explícita.
     */
    private static function customPermissions(): ?array
    {
        if (!array_key_exists('tenant_permissions', $_SESSION)) {
            return null;
        }
        $p = $_SESSION['tenant_permissions'];
        if (!is_array($p)) {
            return null;
        }
        return $p;
    }
    
    /**
     * Obtener el rol del usuario actual
     */
    public static function getRole(): string
    {
        $role = strtolower(trim((string)($_SESSION['tenant_user_role'] ?? 'viewer')));
        if ($role === '' || $role === 'administrator') {
            $role = 'admin';
        }
        if (in_array($role, ['waiter', 'auxiliar_mesero', 'auxiliar/mesero', 'user_pos', 'user-pos'], true)) {
            $role = 'auxiliar';
        }
        return $role;
    }
    
    /**
     * Home segun rol (evitar redirigir a modulos sin acceso)
     */
    public static function homePath(): string
    {
        if (self::isPosUser()) {
            return '/app/caja';
        }
        if (self::canAccess('dashboard')) {
            return '/app/dashboard';
        }
        foreach (['caja', 'ventas', 'inventario', 'clientes'] as $m) {
            if (self::canAccess($m)) {
                return '/app/' . $m;
            }
        }
        return '/app/configuracion';
    }
    
    /**
     * Verificar si el usuario puede acceder a un módulo
     */
    public static function canAccess(string $module): bool
    {
        $role = self::getRole();
        if ($role === 'admin') {
            return true;
        }

        $custom = self::customPermissions();
        if ($role === 'user' && $custom !== null) {
            if ($custom === []) {
                // Sin módulos elegidos: mapa manager por defecto
            } else {
                $mod = $custom[$module] ?? null;
                if (!is_array($mod)) {
                    return false;
                }
                return !empty($mod['view']) || !empty($mod['create']) || !empty($mod['edit']) || !empty($mod['delete']) || !empty($mod['export']);
            }
        }
        
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
        if ($role === 'admin') {
            return true;
        }

        $custom = self::customPermissions();
        if ($role === 'user' && $custom !== null && $custom !== [] && $module !== null) {
            $mod = $custom[$module] ?? [];
            if (!is_array($mod) || empty($mod['view'])) {
                // view implícito si tiene create/edit
                if (empty($mod['create']) && empty($mod['edit']) && empty($mod['delete']) && empty($mod['export'])) {
                    return false;
                }
            }
            if ($action === 'view') {
                return !empty($mod['view']) || !empty($mod['create']) || !empty($mod['edit']) || !empty($mod['delete']);
            }
            if ($action === 'create') {
                return !empty($mod['create']) || !empty($mod['edit']);
            }
            if ($action === 'edit') {
                return !empty($mod['edit']);
            }
            if ($action === 'delete') {
                return !empty($mod['delete']) || !empty($mod['edit']);
            }
            if ($action === 'export') {
                return !empty($mod['export']) || !empty($mod['edit']);
            }
            return !empty($mod[$action]);
        }
        
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

        $cacheKey = 'plan_context:' . $tenantId;
        $cached = SimpleCache::instance()->get($cacheKey);
        if (is_array($cached) && isset($cached['tier'])) {
            return $cache = $cached;
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
                
                $result = [
                    'tier' => in_array($tier, ['basic', 'pro', 'premium', 'custom'], true) ? $tier : 'basic',
                    'reports' => $reports === 'full' ? 'full' : 'basic',
                    'export' => $export,
                    'plan_name' => $planName,
                    'upgrade_plans' => 'Pro o Premium',
                ];
                SimpleCache::instance()->set($cacheKey, $result, 300);
                return $cache = $result;
            }
            
            $name = mb_strtolower($planName);
            $isBasic = (bool)preg_match('/b[aá]sic|basic|starter|inicio|essentials/', $name);
            $isFull = (bool)preg_match('/pro|premium|enterprise|empresarial|personal|custom|unlimited/', $name);
            
            if (!$isBasic && !$isFull) {
                $isFull = ((float)($row['monthly_price'] ?? 0)) >= 50;
            }
            
            $result = [
                'tier' => $isFull ? (preg_match('/premium|enterprise/', $name) ? 'premium' : (preg_match('/personal|custom/', $name) ? 'custom' : 'pro')) : 'basic',
                'reports' => $isFull ? 'full' : 'basic',
                'export' => $isFull,
                'plan_name' => $planName,
                'upgrade_plans' => 'Pro o Premium',
            ];
            SimpleCache::instance()->set($cacheKey, $result, 300);
            return $cache = $result;
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
