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
            $_SESSION['tenant_db_name'],
            $_SESSION['tenant_db_user'],
            $_SESSION['tenant_db_pass']
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
        return [
            'admin' => [
                'modules' => ['dashboard', 'caja', 'ventas', 'inventario', 'clientes', 'proveedores', 'cotizaciones', 'reportes', 'configuracion'],
                'actions' => ['create', 'edit', 'delete', 'view', 'export'],
            ],
            'manager' => [
                'modules' => ['dashboard', 'caja', 'ventas', 'inventario', 'clientes', 'proveedores', 'cotizaciones', 'reportes'],
                'actions' => ['create', 'edit', 'view', 'export'],
            ],
            'cashier' => [
                'modules' => ['dashboard', 'caja', 'ventas', 'clientes'],
                'actions' => ['create', 'view'],
            ],
            'viewer' => [
                'modules' => ['dashboard', 'reportes'],
                'actions' => ['view'],
            ],
        ];
    }
    
    /**
     * Obtener el rol del usuario actual
     */
    public static function getRole(): string
    {
        return $_SESSION['tenant_user_role'] ?? 'viewer';
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
        
        return in_array($module, $perms[$role]['modules']);
    }
    
    /**
     * Verificar si el usuario puede realizar una acción
     */
    public static function canDo(string $action): bool
    {
        $role = self::getRole();
        $perms = self::rolePermissions();
        
        if (!isset($perms[$role])) {
            return false;
        }
        
        return in_array($action, $perms[$role]['actions']);
    }
    
    /**
     * Verificar acceso a módulo y acción, redirigir si no tiene permiso
     */
    public static function authorize(string $module, string $action = 'view'): bool
    {
        if (!self::canAccess($module)) {
            redirect('/app/dashboard');
            return false;
        }
        
        if (!self::canDo($action)) {
            $_SESSION['error'] = 'No tienes permisos para realizar esta acción';
            redirect('/app/' . $module);
            return false;
        }
        
        return true;
    }
}
