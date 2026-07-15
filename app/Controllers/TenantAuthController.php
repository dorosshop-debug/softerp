<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\Database;
use SoftNova\Core\TenantDatabase;
use function SoftNova\Core\redirect;

/**
 * Controlador de autenticación para usuarios de tenants
 */
class TenantAuthController extends Controller
{
    /**
     * Mostrar formulario de login para tenant
     */
    public function login(): void
    {
        $this->view('tenant.login');
    }
    
    /**
     * Procesar login de usuario tenant
     */
    public function authenticate(): void
    {
        if (!$this->validateCsrf()) {
            $_SESSION['tenant_error'] = 'Token de seguridad invalido o expirado';
            redirect('/app/login');
            return;
        }
        
        $email = $this->request->post('email');
        $password = $this->request->post('password');
        
        if (empty($email) || empty($password)) {
            $_SESSION['tenant_error'] = 'Por favor ingrese email y contraseña';
            redirect('/app/login');
            return;
        }
        
        // Buscar el usuario en la tabla tenant_users de la BD maestra
        $masterDb = Database::getInstance();
        
        $user = $masterDb->query(
            "SELECT tu.*, t.company_name, t.database_name, t.database_user, t.database_password, 
                    t.status as tenant_status, t.id as tenant_id, t.company_name as tenant_name
             FROM tenant_users tu
             JOIN tenants t ON tu.tenant_id = t.id
             WHERE tu.email = ? AND tu.status = 'active' AND t.status = 'active'
             LIMIT 1",
            [$email]
        )->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            $_SESSION['tenant_error'] = 'Credenciales incorrectas o cuenta suspendida';
            redirect('/app/login');
            return;
        }
        
        // Verificar que la BD del tenant existe
        try {
            $tenantDb = TenantDatabase::getInstance();
            $tenantDb->getTenantConnection(
                $user['database_name'],
                $user['database_user'],
                $user['database_password']
            );
        } catch (\Exception $e) {
            $_SESSION['tenant_error'] = 'Error de conexión con la base de datos del sistema';
            redirect('/app/login');
            return;
        }
        
        // Guardar sesión del tenant
        $_SESSION['tenant_user_id'] = $user['id'];
        $_SESSION['tenant_user_name'] = $user['name'];
        $_SESSION['tenant_user_email'] = $user['email'];
        $_SESSION['tenant_user_role'] = $user['role'];
        $_SESSION['tenant_id'] = $user['tenant_id'];
        $_SESSION['tenant_name'] = $user['company_name'];
        $_SESSION['tenant_db_name'] = $user['database_name'];
        $_SESSION['tenant_db_user'] = $user['database_user'];
        $_SESSION['tenant_db_pass'] = $user['database_password'];
        $_SESSION['tenant_authenticated'] = true;
        
        // Actualizar último login
        $masterDb->query(
            "UPDATE tenant_users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
            [$this->request->ip(), $user['id']]
        );
        
        redirect('/app/dashboard');
    }
    
    /**
     * Cerrar sesión del tenant
     */
    public function logout(): void
    {
        unset(
            $_SESSION['tenant_user_id'],
            $_SESSION['tenant_user_name'],
            $_SESSION['tenant_user_email'],
            $_SESSION['tenant_user_role'],
            $_SESSION['tenant_id'],
            $_SESSION['tenant_name'],
            $_SESSION['tenant_db_name'],
            $_SESSION['tenant_db_user'],
            $_SESSION['tenant_db_pass'],
            $_SESSION['tenant_authenticated']
        );
        
        redirect('/app/login');
    }
}
