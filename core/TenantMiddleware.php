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
}
