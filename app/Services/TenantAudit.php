<?php

namespace SoftNova\Services;

use SoftNova\Core\Database;

/**
 * Auditoría de acciones del tenant (ventas, usuarios, etc.)
 * Escribe en master.audit_logs y en tenant.activity_logs.
 */
class TenantAudit
{
    public static function log(
        \PDO $tenantDb,
        string $action,
        string $module,
        string $description,
        ?int $entityId = null
    ): void {
        $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
        $userId = (int)($_SESSION['tenant_user_id'] ?? 0);
        $userName = (string)($_SESSION['tenant_user_name'] ?? 'Sistema');
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            self::ensureTable($tenantDb);
            $stmt = $tenantDb->prepare(
                "INSERT INTO activity_logs (user_id, user_name, action, module, entity_id, description, ip_address, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$userId ?: null, $userName, $action, $module, $entityId, $description, $ip]);
        } catch (\Throwable $e) {
            error_log('TenantAudit local: ' . $e->getMessage());
        }

        try {
            AuditService::log($action, $module, $description, $tenantId ?: null, $userId ?: null);
        } catch (\Throwable $e) {
            error_log('TenantAudit master: ' . $e->getMessage());
        }
    }

    public static function ensureTable(\PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS activity_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                user_name VARCHAR(150) NULL,
                action VARCHAR(40) NOT NULL,
                module VARCHAR(60) NOT NULL,
                entity_id INT UNSIGNED NULL,
                description TEXT NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_act_module (module),
                KEY idx_act_user (user_id),
                KEY idx_act_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
