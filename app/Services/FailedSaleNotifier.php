<?php

namespace SoftNova\Services;

/**
 * Notifica ventas fallidas/canceladas (webhook + fila push local).
 */
class FailedSaleNotifier
{
    public function __construct(private \PDO $db)
    {
        $this->ensureTable();
    }

    public function notify(int $saleId, string $invoiceNumber, string $reason, float $total = 0.0): void
    {
        $payload = [
            'event' => 'sale.failed',
            'sale_id' => $saleId,
            'invoice_number' => $invoiceNumber,
            'reason' => $reason,
            'total' => $total,
            'tenant_id' => (int)($_SESSION['tenant_id'] ?? 0),
            'at' => date('c'),
        ];

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO push_notifications (event_type, title, body, payload, status, created_at)
                 VALUES ('sale_failed', ?, ?, ?, 'pending', NOW())"
            );
            $stmt->execute([
                'Venta fallida/cancelada: ' . $invoiceNumber,
                $reason,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            error_log('push_notifications: ' . $e->getMessage());
        }

        $webhook = $this->setting('failed_sale_webhook_url');
        if ($webhook !== '') {
            try {
                (new JobQueue($this->db))->push('webhook', [
                    'url' => $webhook,
                    'payload' => $payload,
                ], 50);
            } catch (\Throwable $e) {
                error_log('failed sale webhook queue: ' . $e->getMessage());
            }
        }
    }

    private function setting(string $key): string
    {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $v = $stmt->fetchColumn();
            return is_string($v) ? trim($v) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function ensureTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS push_notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(60) NOT NULL,
                title VARCHAR(255) NOT NULL,
                body TEXT NULL,
                payload LONGTEXT NULL,
                status ENUM('pending','sent','failed','read') NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at DATETIME NULL,
                KEY idx_push_status (status),
                KEY idx_push_type (event_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
