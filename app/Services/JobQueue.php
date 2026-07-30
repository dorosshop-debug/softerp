<?php

namespace SoftNova\Services;

/**
 * Cola de trabajos asíncronos (backups, import CSV, webhooks).
 * Persistencia en tabla job_queue del tenant.
 */
class JobQueue
{
    public function __construct(private \PDO $db)
    {
        $this->ensureTable();
    }

    public function push(string $type, array $payload = [], int $priority = 100): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO job_queue (type, payload, status, priority, attempts, created_at)
             VALUES (?, ?, 'pending', ?, 0, NOW())"
        );
        $stmt->execute([$type, json_encode($payload, JSON_UNESCAPED_UNICODE), $priority]);
        return (int)$this->db->lastInsertId();
    }

    /** @return array<int, array> */
    public function claim(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->db->query(
            "SELECT * FROM job_queue
             WHERE status = 'pending' AND attempts < 5
             ORDER BY priority ASC, id ASC
             LIMIT {$limit}"
        );
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $claimed = [];
        foreach ($rows as $row) {
            $upd = $this->db->prepare(
                "UPDATE job_queue SET status = 'running', attempts = attempts + 1, updated_at = NOW()
                 WHERE id = ? AND status = 'pending'"
            );
            $upd->execute([(int)$row['id']]);
            if ($upd->rowCount() > 0) {
                $row['payload'] = json_decode((string)$row['payload'], true) ?: [];
                $claimed[] = $row;
            }
        }
        return $claimed;
    }

    public function complete(int $id): void
    {
        $this->db->prepare(
            "UPDATE job_queue SET status = 'done', processed_at = NOW(), updated_at = NOW(), last_error = NULL WHERE id = ?"
        )->execute([$id]);
    }

    public function fail(int $id, string $error): void
    {
        $this->db->prepare(
            "UPDATE job_queue SET status = 'failed', last_error = ?, updated_at = NOW(), processed_at = NOW() WHERE id = ?"
        )->execute([mb_substr($error, 0, 500), $id]);
    }

    public function requeue(int $id, string $error): void
    {
        $this->db->prepare(
            "UPDATE job_queue SET status = 'pending', last_error = ?, updated_at = NOW() WHERE id = ? AND attempts < 5"
        )->execute([mb_substr($error, 0, 500), $id]);
    }

    private function ensureTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS job_queue (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(80) NOT NULL,
                payload LONGTEXT NULL,
                status ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
                priority INT NOT NULL DEFAULT 100,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                last_error VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                processed_at DATETIME NULL,
                KEY idx_job_status_pri (status, priority, id),
                KEY idx_job_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
