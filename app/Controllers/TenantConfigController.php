<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

/**
 * Controlador de Configuración del Tenant
 * Permite al cliente configurar moneda, impuestos, datos de empresa
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
        $action = $this->request->get('action');
        
        if ($action === 'save' && $this->request->method() === 'POST') {
            $this->saveSettings();
            return;
        }
        
        // Cargar settings actuales
        $settings = [];
        $rows = $this->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $currencies = [
            'COP' => ['symbol' => '$', 'name' => 'Peso Colombiano (COP)'],
            'USD' => ['symbol' => 'US$', 'name' => 'Dólar Estadounidense (USD)'],
            'EUR' => ['symbol' => '€', 'name' => 'Euro (EUR)'],
        ];
        
        $this->view('tenant.config', [
            'settings' => $settings,
            'currencies' => $currencies,
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
        
        $fields = ['currency', 'company_name', 'tax_name', 'tax_rate', 'invoice_prefix', 'low_stock_alert'];
        
        foreach ($fields as $field) {
            $value = $this->request->post($field);
            if ($value !== null) {
                // Upsert: insertar o actualizar
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
}
