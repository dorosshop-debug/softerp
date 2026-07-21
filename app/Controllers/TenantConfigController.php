<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;
use SoftNova\Core\Security;

/**
 * Controlador de Configuración del Tenant
 * Permite al cliente configurar moneda, impuestos, datos de empresa, perfil, idioma
 */
class TenantConfigController extends Controller
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
    
    /**
     * Página de configuración
     */
    public function index(): void
    {
        \SoftNova\Core\TenantMiddleware::authorize('configuracion');
        
        $action = $this->request->get('action');
        
        if ($action === 'save' && $this->request->method() === 'POST') {
            \SoftNova\Core\TenantMiddleware::authorize('configuracion', 'edit');
            $this->saveSettings();
            return;
        }
        
        if ($action === 'changePassword' && $this->request->method() === 'POST') {
            $this->changePassword();
            return;
        }
        
        if ($action === 'uploadAvatar' && $this->request->method() === 'POST') {
            $this->uploadAvatar();
            return;
        }
        
        // Cargar settings actuales
        $settings = [];
        $rows = $this->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Datos del usuario actual
        $userId = $_SESSION['tenant_user_id'] ?? 0;
        $currentUser = null;
        if ($userId > 0) {
            $currentUser = $this->query("SELECT id, name, email, role, last_login_at FROM users WHERE id = ?", [$userId])->fetch();
        }
        
        // Info de suscripción desde BD maestra
        $tenantId = $_SESSION['tenant_id'] ?? 0;
        $subscription = null;
        if ($tenantId > 0) {
            $masterDb = \SoftNova\Core\Database::getInstance();
            $sub = $masterDb->query(
                "SELECT t.status as subscription_status, t.subscription_end_date, sp.name as plan_name, sp.monthly_price, sp.annual_price
                 FROM tenants t
                 JOIN subscription_plans sp ON t.subscription_plan_id = sp.id
                 WHERE t.id = ? LIMIT 1",
                [$tenantId]
            )->fetch();
            if ($sub) {
                $subscription = $sub;
            }
        }
        
        $currencies = [
            'COP' => ['symbol' => '$', 'name' => 'Peso Colombiano (COP)'],
            'USD' => ['symbol' => 'US$', 'name' => 'Dólar Estadounidense (USD)'],
            'EUR' => ['symbol' => '€', 'name' => 'Euro (EUR)'],
            'MXN' => ['symbol' => 'MX$', 'name' => 'Peso Mexicano (MXN)'],
            'ARS' => ['symbol' => 'AR$', 'name' => 'Peso Argentino (ARS)'],
            'PEN' => ['symbol' => 'S/', 'name' => 'Sol Peruano (PEN)'],
            'CLP' => ['symbol' => 'CL$', 'name' => 'Peso Chileno (CLP)'],
        ];
        
        $languages = [
            'es' => ['flag' => '🇪🇸', 'name' => 'Español'],
            'en' => ['flag' => '🇺🇸', 'name' => 'English'],
            'pt' => ['flag' => '🇧🇷', 'name' => 'Português'],
            'fr' => ['flag' => '🇫🇷', 'name' => 'Français'],
            'de' => ['flag' => '🇩🇪', 'name' => 'Deutsch'],
            'it' => ['flag' => '🇮🇹', 'name' => 'Italiano'],
            'zh' => ['flag' => '🇨🇳', 'name' => '中文'],
            'ja' => ['flag' => '🇯🇵', 'name' => '日本語'],
        ];
        
        $this->view('tenant.config', [
            'settings' => $settings,
            'currencies' => $currencies,
            'languages' => $languages,
            'currentUser' => $currentUser,
            'subscription' => $subscription,
            'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa',
            'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
        ]);
    }
    
    /**
     * Guardar configuración
     */
    private function saveSettings(): void
    {
        if (!$this->validateCsrfOrFail('/app/configuracion')) {
            return;
        }
        
        $fields = ['currency', 'company_name', 'tax_name', 'tax_rate', 'invoice_prefix', 'low_stock_alert', 'language'];
        
        foreach ($fields as $field) {
            $value = $this->request->post($field);
            if ($value !== null) {
                $existing = $this->query(
                    "SELECT id FROM settings WHERE setting_key = ?",
                    [$field]
                )->fetch();
                
                if ($existing) {
                    $this->query(
                        "UPDATE settings SET setting_value = ? WHERE setting_key = ?",
                        [$value, $field]
                    );
                } else {
                    $this->query(
                        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)",
                        [$field, $value]
                    );
                }
            }
        }
        
        $this->respond(true, 'Configuración guardada exitosamente', '/app/configuracion');
    }
    
    /**
     * Cambiar contraseña del usuario
     */
    private function changePassword(): void
    {
        if (!$this->validateCsrfOrFail('/app/configuracion')) {
            return;
        }
        
        $userId = $_SESSION['tenant_user_id'] ?? 0;
        $currentPassword = $this->request->post('current_password') ?? '';
        $newPassword = $this->request->post('new_password') ?? '';
        $confirmPassword = $this->request->post('confirm_password') ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $this->respond(false, 'Todos los campos son requeridos');
            return;
        }
        
        if ($newPassword !== $confirmPassword) {
            $this->respond(false, 'Las contraseñas nuevas no coinciden');
            return;
        }
        
        if (strlen($newPassword) < 6) {
            $this->respond(false, 'La contraseña debe tener al menos 6 caracteres');
            return;
        }
        
        // Verificar contraseña actual
        $user = $this->query("SELECT password FROM users WHERE id = ?", [$userId])->fetch();
        if (!$user || !Security::verifyPassword($currentPassword, $user['password'])) {
            $this->respond(false, 'La contraseña actual es incorrecta');
            return;
        }
        
        // Actualizar contraseña
        $hashed = Security::hashPassword($newPassword);
        $this->query("UPDATE users SET password = ? WHERE id = ?", [$hashed, $userId]);
        
        $this->respond(true, 'Contraseña actualizada exitosamente');
    }
    
    /**
     * Subir avatar/imagen de perfil
     */
    private function uploadAvatar(): void
    {
        if (!$this->validateCsrfOrFail('/app/configuracion')) {
            return;
        }
        
        $userId = $_SESSION['tenant_user_id'] ?? 0;
        
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $this->respond(false, 'Error al subir la imagen');
            return;
        }
        
        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            $this->respond(false, 'Formato no permitido. Use JPG, PNG, GIF o WebP');
            return;
        }
        
        if ($file['size'] > $maxSize) {
            $this->respond(false, 'La imagen no debe superar 2MB');
            return;
        }
        
        // Crear directorio si no existe
        $uploadDir = ROOT_PATH . '/public/uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->respond(false, 'Error al guardar la imagen');
            return;
        }
        
        $avatarUrl = \SoftNova\Core\base_url('uploads/avatars/' . $filename);
        
        // Guardar en settings
        $existing = $this->query("SELECT id FROM settings WHERE setting_key = 'user_avatar'")->fetch();
        if ($existing) {
            $this->query("UPDATE settings SET setting_value = ? WHERE setting_key = 'user_avatar'", [$avatarUrl]);
        } else {
            $this->query("INSERT INTO settings (setting_key, setting_value) VALUES ('user_avatar', ?)", [$avatarUrl]);
        }
        
        $this->respond(true, 'Imagen de perfil actualizada');
    }
}
