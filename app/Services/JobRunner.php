<?php

namespace SoftNova\Services;

use SoftNova\Services\Integrations\CatalogSyncService;
use SoftNova\Services\TenantBackupService;

/**
 * Ejecuta trabajos de la cola del tenant.
 */
class JobRunner
{
    public function __construct(private \PDO $db)
    {
    }

    public function process(int $limit = 5): array
    {
        $queue = new JobQueue($this->db);
        $jobs = $queue->claim($limit);
        $results = [];
        foreach ($jobs as $job) {
            $id = (int)$job['id'];
            $type = (string)$job['type'];
            $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
            try {
                match ($type) {
                    'backup' => $this->runBackup($payload),
                    'import_csv' => $this->runImportCsv($payload),
                    'webhook' => $this->runWebhook($payload),
                    default => throw new \RuntimeException('Tipo de job desconocido: ' . $type),
                };
                $queue->complete($id);
                $results[] = ['id' => $id, 'ok' => true];
            } catch (\Throwable $e) {
                if ((int)($job['attempts'] ?? 1) >= 5) {
                    $queue->fail($id, $e->getMessage());
                } else {
                    $queue->requeue($id, $e->getMessage());
                }
                $results[] = ['id' => $id, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    private function runBackup(array $payload): void
    {
        $dbName = (string)($payload['db_name'] ?? ($_SESSION['tenant_db_name'] ?? ''));
        if ($dbName === '') {
            throw new \RuntimeException('db_name requerido para backup');
        }
        $svc = new TenantBackupService();
        $svc->createBackup($dbName, (string)($payload['label'] ?? 'job-auto'));
    }

    private function runImportCsv(array $payload): void
    {
        $path = (string)($payload['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new \RuntimeException('Archivo CSV no encontrado para importación');
        }
        (new ProductCsvImporter($this->db))->importFile($path);
        @unlink($path);
    }

    private function runWebhook(array $payload): void
    {
        $url = (string)($payload['url'] ?? '');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('URL de webhook inválida');
        }
        $body = json_encode($payload['payload'] ?? [], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false || $code >= 400) {
            throw new \RuntimeException('Webhook HTTP ' . $code . ' ' . $err);
        }
    }
}
