<?php
/**
 * Actualiza esquemas de tenants existentes.
 * Incluye tablas/columnas legacy y el módulo contable nativo.
 *
 * Uso (CLI): php database/migrate_existing.php
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
$tenants = $masterDb->query(
    "SELECT database_name FROM tenants WHERE status != 'cancelled'"
)->fetchAll();

echo '=== Actualizando ' . count($tenants) . " tenant(s) ===\n\n";

$tenantDb = \SoftNova\Core\TenantDatabase::getInstance();

foreach ($tenants as $tenant) {
    $dbName = $tenant['database_name'];
    echo "Tenant: {$dbName}\n";

    try {
        $pdo = $tenantDb->getTenantConnection($dbName, '', '');

        try {
            $pdo->exec('ALTER TABLE cash_movements ADD COLUMN user_id INT UNSIGNED AFTER reference_id');
            echo "  ✓ user_id agregado a cash_movements\n";
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Duplicate column')) {
                echo "  - user_id ya existe en cash_movements\n";
            } else {
                echo '  ⚠ cash_movements: ' . $e->getMessage() . "\n";
            }
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS sale_payments (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  ✓ sale_payments lista\n";
        } catch (\Exception $e) {
            echo '  ⚠ sale_payments: ' . $e->getMessage() . "\n";
        }

        try {
            $pdo->exec('ALTER TABLE sale_items ADD COLUMN unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unit_price');
            echo "  ✓ unit_cost agregado a sale_items\n";
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Duplicate column')) {
                echo "  - unit_cost ya existe en sale_items\n";
            } else {
                echo '  ⚠ sale_items: ' . $e->getMessage() . "\n";
            }
        }

        try {
            $accounting = new \SoftNova\Services\AccountingService($pdo);
            $count = count($accounting->accounts());
            echo "  ✓ contabilidad lista ({$count} cuentas)\n";
        } catch (\Exception $e) {
            echo '  ⚠ contabilidad: ' . $e->getMessage() . "\n";
        }

        try {
            $integrations = new \SoftNova\Services\Integrations\IntegrationSettingsService($pdo);
            $integrations->ensureSchema();
            echo "  ✓ integration_settings lista\n";
        } catch (\Exception $e) {
            echo '  ⚠ integraciones: ' . $e->getMessage() . "\n";
        }

        try {
            $ai = new \SoftNova\Services\AiService($pdo);
            $ai->ensureHistorySchema();
            echo "  ✓ historial IA listo\n";
        } catch (\Exception $e) {
            echo '  ⚠ historial IA: ' . $e->getMessage() . "\n";
        }

        try {
            $mgr = new \SoftNova\Services\Integrations\IntegrationManager($pdo);
            $mgr->ensureSaleExternalColumns();
            echo "  ✓ columnas FE en sales listas\n";
        } catch (\Exception $e) {
            echo '  ⚠ columnas FE: ' . $e->getMessage() . "\n";
        }

        echo "\n";
    } catch (\Exception $e) {
        echo '  ❌ Error conectando: ' . $e->getMessage() . "\n\n";
    }
}

echo "=== Migración completada ===\n";
