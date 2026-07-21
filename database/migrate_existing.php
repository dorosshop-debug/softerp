<?php
/**
 * Script para actualizar esquema de bases de datos de tenants existentes
 * Agrega tablas/columnas faltantes: sale_payments, user_id en cash_movements
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CORE_PATH', ROOT_PATH . '/core');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

$masterDb = \SoftNova\Core\Database::getInstance();

// Obtener todos los tenants activos
$tenants = $masterDb->query(
    "SELECT database_name FROM tenants WHERE status != 'cancelled'"
)->fetchAll();

echo "=== Actualizando " . count($tenants) . " tenant(s) ===\n\n";

$tenantDb = \SoftNova\Core\TenantDatabase::getInstance();

foreach ($tenants as $tenant) {
    $dbName = $tenant['database_name'];
    echo "Tenant: {$dbName}\n";
    
    try {
        $pdo = $tenantDb->getTenantConnection($dbName, '', '');
        
        // 1. Agregar user_id a cash_movements
        try {
            $pdo->exec("ALTER TABLE cash_movements ADD COLUMN user_id INT UNSIGNED AFTER reference_id");
            echo "  ✓ user_id agregado a cash_movements\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "  - user_id ya existe en cash_movements\n";
            } else {
                echo "  ⚠ cash_movements: " . $e->getMessage() . "\n";
            }
        }
        
        // 2. Crear sale_payments
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS sale_payments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sale_id INT UNSIGNED NOT NULL,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                payment_method ENUM('cash','card','transfer','other') DEFAULT 'cash',
                notes VARCHAR(255),
                user_id INT UNSIGNED,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sale (sale_id),
                FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            echo "  ✓ sale_payments creada\n";
        } catch (\Exception $e) {
            echo "  ⚠ sale_payments: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    } catch (\Exception $e) {
        echo "  ❌ Error conectando: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Migración completada ===\n";
