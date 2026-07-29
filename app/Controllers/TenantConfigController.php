<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;
use SoftNova\Core\Security;
use SoftNova\Services\TenantBackupService;

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
        
        if ($action === 'backupDownload' && $this->request->method() === 'POST') {
            \SoftNova\Core\TenantMiddleware::authorize('configuracion', 'edit');
            $this->backupDownload();
            return;
        }
        
        if ($action === 'backupRestore' && $this->request->method() === 'POST') {
            \SoftNova\Core\TenantMiddleware::authorize('configuracion', 'edit');
            $this->backupRestore();
            return;
        }
        
        if ($action === 'backupSchedule' && $this->request->method() === 'POST') {
            \SoftNova\Core\TenantMiddleware::authorize('configuracion', 'edit');
            $this->backupSchedule();
            return;
        }
        
        if ($action === 'backupGetFile' && in_array($this->request->method(), ['GET', 'POST'], true)) {
            \SoftNova\Core\TenantMiddleware::authorize('configuracion', 'edit');
            $this->backupGetFile();
            return;
        }
        
        if ($action === 'backupDelete' && $this->request->method() === 'POST') {
            \SoftNova\Core\TenantMiddleware::authorize('configuracion', 'edit');
            $this->backupDelete();
            return;
        }

        if ($action === 'save-catalog-integration' && $this->request->method() === 'POST') {
            \SoftNova\Core\TenantMiddleware::authorize('configuracion', 'edit');
            $this->saveCatalogIntegration();
            return;
        }
        if ($action === 'catalog-test') {
            $this->testCatalogIntegration();
            return;
        }
        if ($action === 'ml-oauth-start') {
            \SoftNova\Core\TenantMiddleware::authorize('configuracion', 'edit');
            $this->mlOAuthStart();
            return;
        }
        if ($action === 'ml-oauth-callback') {
            \SoftNova\Core\TenantMiddleware::authorize('configuracion', 'edit');
            $this->mlOAuthCallback();
            return;
        }
        
        // Cargar settings actuales
        $settings = [];
        $rows = $this->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Auto-backup lazy al entrar a configuracion
        $this->runScheduledBackupIfDue($settings);
        // Recargar settings por si se actualizo last_run
        $settings = [];
        $rows = $this->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $backupService = new TenantBackupService();
        $backupPage = max(1, (int)($this->request->get('backup_page') ?? 1));
        $backupList = $backupService->listBackups($backupPage, 7);
        $backups = $backupList['items'];
        $backupsAll = $backupList['all'];
        $backupPagination = $backupList['pagination'];
        
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

        $catalogStatuses = [];
        try {
            $catalogStatuses = (new \SoftNova\Services\Integrations\CatalogSyncService($this->db))->statuses();
        } catch (\Throwable $e) {
            $catalogStatuses = [];
        }
        $ecomProvider = (string)$this->request->get('provider', 'woocommerce');
        
        $this->view('tenant.config', [
            'settings' => $settings,
            'currencies' => $currencies,
            'languages' => $languages,
            'currentUser' => $currentUser,
            'subscription' => $subscription,
            'backups' => $backups,
            'backupsAll' => $backupsAll,
            'backupPagination' => $backupPagination,
            'catalogStatuses' => $catalogStatuses,
            'ecomProvider' => $ecomProvider,
            'mlOAuthRedirect' => \SoftNova\Core\route('app/configuracion') . '?action=ml-oauth-callback',
            'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa',
            'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
        ]);
    }

    private function saveCatalogIntegration(): void
    {
        if (!$this->validateCsrfOrFail('/app/configuracion?section=ecommerce')) {
            return;
        }
        $provider = (string)$this->request->post('provider', '');
        try {
            $svc = new \SoftNova\Services\Integrations\CatalogSyncService($this->db);
            $svc->saveProvider($provider, [
                'enabled' => $this->request->post('enabled', '0'),
                'store_url' => $this->request->post('store_url', ''),
                'consumer_key' => $this->request->post('consumer_key', ''),
                'consumer_secret' => $this->request->post('consumer_secret', ''),
                'access_token' => $this->request->post('access_token', ''),
                'refresh_token' => $this->request->post('refresh_token', ''),
                'client_id' => $this->request->post('client_id', ''),
                'client_secret' => $this->request->post('client_secret', ''),
                'user_id' => $this->request->post('user_id', ''),
                'site_id' => $this->request->post('site_id', 'MCO'),
                'base_url' => $this->request->post('base_url', ''),
                'stock_authority' => $this->request->post('stock_authority', 'create_only'),
            ]);
            $this->respond(true, 'Integración e-commerce guardada', '/app/configuracion?section=ecommerce&provider=' . urlencode($provider));
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), '/app/configuracion?section=ecommerce');
        }
    }

    private function testCatalogIntegration(): void
    {
        $provider = (string)$this->request->get('provider', '');
        $this->json((new \SoftNova\Services\Integrations\CatalogSyncService($this->db))->test($provider));
    }

    private function mlOAuthStart(): void
    {
        $svc = new \SoftNova\Services\Integrations\CatalogSyncService($this->db);
        $ml = $svc->mercadoLibre();
        if (!$ml) {
            $this->respond(false, 'Conector ML no disponible', '/app/configuracion?section=ecommerce&provider=mercadolibre');
            return;
        }
        $st = $ml->status();
        if (empty($st['oauth_ready'])) {
            $this->respond(false, 'Guarde primero Client ID y Client Secret', '/app/configuracion?section=ecommerce&provider=mercadolibre');
            return;
        }
        $state = bin2hex(random_bytes(16));
        $_SESSION['ml_oauth_state'] = $state;
        $redirect = \SoftNova\Core\route('app/configuracion') . '?action=ml-oauth-callback';
        header('Location: ' . $ml->authorizationUrl($redirect, $state));
        exit;
    }

    private function mlOAuthCallback(): void
    {
        $error = (string)$this->request->get('error', '');
        if ($error !== '') {
            $this->respond(false, 'OAuth ML cancelado: ' . $error, '/app/configuracion?section=ecommerce&provider=mercadolibre');
            return;
        }
        $state = (string)$this->request->get('state', '');
        $expected = (string)($_SESSION['ml_oauth_state'] ?? '');
        unset($_SESSION['ml_oauth_state']);
        if ($expected === '' || !hash_equals($expected, $state)) {
            $this->respond(false, 'Estado OAuth inválido. Intente conectar de nuevo.', '/app/configuracion?section=ecommerce&provider=mercadolibre');
            return;
        }
        $code = (string)$this->request->get('code', '');
        if ($code === '') {
            $this->respond(false, 'Sin código de autorización', '/app/configuracion?section=ecommerce&provider=mercadolibre');
            return;
        }
        try {
            $svc = new \SoftNova\Services\Integrations\CatalogSyncService($this->db);
            $ml = $svc->mercadoLibre();
            if (!$ml) {
                throw new \RuntimeException('Conector ML no disponible');
            }
            $redirect = \SoftNova\Core\route('app/configuracion') . '?action=ml-oauth-callback';
            $ml->exchangeToken('authorization_code', [
                'code' => $code,
                'redirect_uri' => $redirect,
            ]);
            $svc->settings()->set('mercadolibre', 'enabled', '1', false);
            $this->respond(true, 'Mercado Libre conectado', '/app/configuracion?section=ecommerce&provider=mercadolibre');
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), '/app/configuracion?section=ecommerce&provider=mercadolibre');
        }
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
                $this->upsertSetting($field, (string)$value);
            }
        }

        $primaryColor = $this->request->post('primary_color');
        if ($primaryColor !== null) {
            $primaryColor = trim((string)$primaryColor);
            if ($primaryColor === '' || preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
                $this->upsertSetting('primary_color', strtoupper($primaryColor));
            }
        }
        
        $this->respond(true, 'Configuración guardada exitosamente', '/app/configuracion');
    }
    
    private function upsertSetting(string $key, string $value): void
    {
        $existing = $this->query(
            "SELECT id FROM settings WHERE setting_key = ?",
            [$key]
        )->fetch();
        
        if ($existing) {
            $this->query(
                "UPDATE settings SET setting_value = ? WHERE setting_key = ?",
                [$value, $key]
            );
        } else {
            $this->query(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)",
                [$key, $value]
            );
        }
    }
    
    private function requireAdmin(): bool
    {
        if (($_SESSION['tenant_user_role'] ?? '') !== 'admin') {
            $this->respond(false, 'Solo el administrador puede gestionar copias de seguridad');
            return false;
        }
        return true;
    }
    
    private function backupDownload(): void
    {
        if (!$this->validateCsrfOrFail('/app/configuracion')) {
            return;
        }
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->verifyAdminPassword((string)($this->request->post('confirm_password') ?? ''))) {
            $this->respond(false, 'Contraseña incorrecta. La descarga fue cancelada.');
            return;
        }
        
        $dbName = (string)($_SESSION['tenant_db_name'] ?? '');
        if ($dbName === '') {
            $this->respond(false, 'No se encontro la base de datos del tenant');
            return;
        }
        
        $svc = new TenantBackupService();
        $result = $svc->createBackup($dbName, 'manual');
        if (empty($result['success'])) {
            $this->respond(false, $result['message'] ?? 'Error al crear backup');
            return;
        }
        
        $this->upsertSetting('backup_last_run', date('Y-m-d H:i:s'));
        
        $_SESSION['backup_dl'] = [
            'file' => $result['filename'],
            'until' => time() + 120,
        ];
        
        $redirect = 'app/configuracion?action=backupGetFile&file=' . rawurlencode($result['filename']);
        $this->respond(true, 'Copia creada. Descargando...', $redirect);
    }
    
    private function backupGetFile(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        
        $file = (string)($this->request->method() === 'POST'
            ? ($this->request->post('file') ?? '')
            : ($this->request->get('file') ?? ''));
        
        $allowed = false;
        $token = $_SESSION['backup_dl'] ?? null;
        if (is_array($token)
            && ($token['file'] ?? '') === $file
            && (int)($token['until'] ?? 0) >= time()
        ) {
            $allowed = true;
            unset($_SESSION['backup_dl']);
        }
        
        if (!$allowed) {
            if ($this->request->method() !== 'POST') {
                $this->respond(false, 'Para descargar debe confirmar con su contraseña', '/app/configuracion');
                return;
            }
            if (!$this->validateCsrfOrFail('/app/configuracion')) {
                return;
            }
            if (!$this->verifyAdminPassword((string)($this->request->post('confirm_password') ?? ''))) {
                $this->respond(false, 'Contraseña incorrecta. La descarga fue cancelada.', '/app/configuracion');
                return;
            }
        }
        
        $svc = new TenantBackupService();
        $path = $svc->resolvePath($file);
        if (!$path) {
            $this->respond(false, 'Archivo no encontrado', '/app/configuracion');
            return;
        }
        
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }
    
    private function verifyAdminPassword(string $password): bool
    {
        if ($password === '') {
            return false;
        }
        $userId = (int)($_SESSION['tenant_user_id'] ?? 0);
        $user = $this->query("SELECT password FROM users WHERE id = ?", [$userId])->fetch();
        return $user && Security::verifyPassword($password, $user['password']);
    }
    
    private function backupDelete(): void
    {
        if (!$this->validateCsrfOrFail('/app/configuracion')) {
            return;
        }
        if (!$this->requireAdmin()) {
            return;
        }
        
        $file = (string)($this->request->post('file') ?? '');
        $svc = new TenantBackupService();
        $result = $svc->deleteBackup($file);
        
        if (empty($result['success'])) {
            $this->respond(false, $result['message'] ?? 'No se pudo eliminar', '/app/configuracion');
            return;
        }
        
        $this->respond(true, 'Copia de seguridad eliminada', '/app/configuracion');
    }
    
    private function backupRestore(): void
    {
        if (!$this->validateCsrfOrFail('/app/configuracion')) {
            return;
        }
        if (!$this->requireAdmin()) {
            return;
        }
        
        $password = (string)($this->request->post('confirm_password') ?? '');
        if (!$this->verifyAdminPassword($password)) {
            $this->respond(false, 'Contraseña incorrecta. La restauracion fue cancelada.');
            return;
        }
        
        $dbName = (string)($_SESSION['tenant_db_name'] ?? '');
        if ($dbName === '') {
            $this->respond(false, 'No se encontro la base de datos del tenant');
            return;
        }
        
        $svc = new TenantBackupService();
        
        $path = null;
        $fromFile = (string)($this->request->post('backup_file') ?? '');
        if ($fromFile !== '') {
            $path = $svc->resolvePath($fromFile);
        }
        
        if (!$path && isset($_FILES['backup_upload']) && $_FILES['backup_upload']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['backup_upload']['tmp_name'];
            $name = basename((string)$_FILES['backup_upload']['name']);
            if (!preg_match('/\.sql$/i', $name)) {
                $this->respond(false, 'Solo se aceptan archivos .sql');
                return;
            }
            if ((int)$_FILES['backup_upload']['size'] > 80 * 1024 * 1024) {
                $this->respond(false, 'El archivo supera el limite de 80MB');
                return;
            }
            $dest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore_' . (int)($_SESSION['tenant_user_id'] ?? 0) . '_' . time() . '.sql';
            if (!move_uploaded_file($tmp, $dest)) {
                $this->respond(false, 'No se pudo recibir el archivo');
                return;
            }
            $path = $dest;
        }
        
        if (!$path) {
            $this->respond(false, 'Seleccione un backup existente o suba un archivo .sql');
            return;
        }
        
        $result = $svc->restoreBackup($dbName, $path);
        if (str_starts_with((string)$path, sys_get_temp_dir())) {
            @unlink($path);
        }
        
        if (empty($result['success'])) {
            $this->respond(false, $result['message'] ?? 'Error al restaurar');
            return;
        }
        
        $this->respond(true, 'Restauracion completada.', '/app/configuracion');
    }
    
    private function backupSchedule(): void
    {
        if (!$this->validateCsrfOrFail('/app/configuracion')) {
            return;
        }
        if (!$this->requireAdmin()) {
            return;
        }
        
        $enabled = $this->request->post('backup_enabled') ? '1' : '0';
        $time = (string)($this->request->post('backup_time') ?? '02:00');
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time)) {
            $this->respond(false, 'Hora invalida. Use formato HH:MM');
            return;
        }
        
        $this->upsertSetting('backup_enabled', $enabled);
        $this->upsertSetting('backup_time', $time);
        
        $this->respond(true, 'Programacion de backup guardada', '/app/configuracion');
    }
    
    private function runScheduledBackupIfDue(array &$settings): void
    {
        try {
            $dbName = (string)($_SESSION['tenant_db_name'] ?? '');
            if ($dbName === '') {
                return;
            }
            $svc = new TenantBackupService();
            $result = $svc->maybeAutoBackup($dbName, $settings);
            $hints = TenantBackupService::consumeSettingHints();
            foreach ($hints as $k => $v) {
                $this->upsertSetting((string)$k, (string)$v);
                $settings[(string)$k] = (string)$v;
            }
            if ($result && !empty($result['success'])) {
                $now = date('Y-m-d H:i:s');
                $this->upsertSetting('backup_last_run', $now);
                $settings['backup_last_run'] = $now;
            }
        } catch (\Throwable $e) {
            error_log('config auto-backup: ' . $e->getMessage());
        }
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
        
        if (strlen($newPassword) < 8) {
            $this->respond(false, 'La contraseña debe tener al menos 8 caracteres');
            return;
        }
        
        // Verificar contraseña actual
        $user = $this->query("SELECT password, email FROM users WHERE id = ?", [$userId])->fetch();
        if (!$user || !Security::verifyPassword($currentPassword, $user['password'])) {
            $this->respond(false, 'La contraseña actual es incorrecta');
            return;
        }
        
        // Actualizar contraseña en BD tenant y en master (login usa tenant_users)
        $hashed = Security::hashPassword($newPassword);
        $this->query("UPDATE users SET password = ? WHERE id = ?", [$hashed, $userId]);
        
        try {
            $masterDb = \SoftNova\Core\Database::getInstance();
            $masterUserId = (int)($_SESSION['tenant_master_user_id'] ?? 0);
            $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
            $email = (string)($_SESSION['tenant_user_email'] ?? $user['email'] ?? '');
            
            if ($masterUserId > 0) {
                $masterDb->query(
                    "UPDATE tenant_users SET password = ? WHERE id = ? AND tenant_id = ?",
                    [$hashed, $masterUserId, $tenantId]
                );
            } elseif ($email !== '' && $tenantId > 0) {
                $masterDb->query(
                    "UPDATE tenant_users SET password = ? WHERE email = ? AND tenant_id = ?",
                    [$hashed, $email, $tenantId]
                );
            }
        } catch (\Throwable $e) {
            error_log('changePassword master sync: ' . $e->getMessage());
            $this->respond(false, 'Contraseña local actualizada, pero no se pudo sincronizar el login. Contacte soporte.');
            return;
        }
        
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
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if ($file['size'] > $maxSize) {
            $this->respond(false, 'La imagen no debe superar 2MB');
            return;
        }
        
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file['tmp_name']) ?: '';
        if (!in_array($realMime, $allowedMimes, true)) {
            $this->respond(false, 'Formato no permitido. Use JPG, PNG, GIF o WebP');
            return;
        }
        
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $ext = $mimeToExt[$realMime] ?? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $this->respond(false, 'Extension de archivo no permitida');
            return;
        }
        
        // Crear directorio si no existe
        $uploadDir = ROOT_PATH . '/public/uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->respond(false, 'Error al guardar la imagen');
            return;
        }
        
        $avatarUrl = \SoftNova\Core\base_url('uploads/avatars/' . $filename);
        
        $this->upsertSetting('user_avatar', $avatarUrl);
        
        $this->respond(true, 'Imagen de perfil actualizada');
    }
}
