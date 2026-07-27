<?php

namespace SoftNova\Services;

/**
 * Asegura tablas/columnas operativas: compras, trazabilidad, canales e-commerce.
 * Idempotente por conexión PDO.
 */
class TenantOpsSchema
{
    public static function ensure(\PDO $db): void
    {
        static $done = [];
        $oid = spl_object_id($db);
        if (isset($done[$oid])) {
            return;
        }
        $done[$oid] = true;

        $db->exec(
            "CREATE TABLE IF NOT EXISTS purchases (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                purchase_number VARCHAR(50) NOT NULL,
                supplier_id INT UNSIGNED NULL,
                user_id INT UNSIGNED NULL,
                purchase_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                tax DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                payment_method VARCHAR(50) DEFAULT 'cash',
                payment_status ENUM('paid','pending','partial') DEFAULT 'paid',
                notes TEXT NULL,
                status ENUM('completed','cancelled') DEFAULT 'completed',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_purchase_number (purchase_number),
                KEY idx_supplier (supplier_id),
                KEY idx_date (purchase_date),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS purchase_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                purchase_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NULL,
                product_name VARCHAR(255) NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_purchase (purchase_id),
                KEY idx_product (product_id),
                CONSTRAINT fk_purchase_items_purchase FOREIGN KEY (purchase_id)
                    REFERENCES purchases(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::addColumnIfMissing($db, 'stock_movements', 'movement_date',
            'DATETIME NULL DEFAULT NULL AFTER created_at');
        // Rellenar movement_date con created_at donde falte
        try {
            $db->exec('UPDATE stock_movements SET movement_date = created_at WHERE movement_date IS NULL');
        } catch (\Throwable $e) {
            // ignore
        }

        self::addColumnIfMissing($db, 'products', 'source_channel',
            "VARCHAR(40) NOT NULL DEFAULT 'manual' AFTER status");
        self::addColumnIfMissing($db, 'products', 'external_source',
            'VARCHAR(40) NULL AFTER source_channel');
        self::addColumnIfMissing($db, 'products', 'external_id',
            'VARCHAR(120) NULL AFTER external_source');
        self::addColumnIfMissing($db, 'sale_items', 'unit_cost',
            'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unit_price');

        // Unificar payment_method a VARCHAR (evita ENUM frágil en hosting restringido)
        self::paymentMethodToVarchar($db, 'sales');
        self::paymentMethodToVarchar($db, 'expenses');
    }

    private static function addColumnIfMissing(\PDO $db, string $table, string $column, string $definition): void
    {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $db->quote($column));
            if ($stmt && $stmt->fetch()) {
                return;
            }
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (\Throwable $e) {
            // silencioso
        }
    }

    private static function paymentMethodToVarchar(\PDO $db, string $table): void
    {
        try {
            $col = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'payment_method'")->fetch();
            if (!$col) {
                return;
            }
            $type = strtolower((string)($col['Type'] ?? ''));
            if (str_starts_with($type, 'varchar')) {
                return;
            }
            $db->exec(
                "ALTER TABLE `{$table}` MODIFY COLUMN payment_method VARCHAR(50) DEFAULT 'cash'"
            );
        } catch (\Throwable $e) {
            // Fallback: ampliar ENUM si no se puede convertir a VARCHAR
            try {
                $db->exec(
                    "ALTER TABLE `{$table}` MODIFY COLUMN payment_method
                     ENUM('cash','card','debit_card','credit_card','dataphone','payment_link','transfer','credit','other')
                     DEFAULT 'cash'"
                );
            } catch (\Throwable $e2) {
                // silencioso
            }
        }
    }
}
