<?php
/**
 * Procesa la cola de jobs de un tenant (o todos).
 *
 * CLI:
 *   php database/process_jobs.php
 *   php database/process_jobs.php --tenant=mi_bd --limit=10
 *
 * Web (admin):
 *   /app/configuracion?action=processJobs
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CORE_PATH', ROOT_PATH . '/core');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

$limit = 5;
$onlyTenant = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int)substr($arg, 8));
    }
    if (str_starts_with($arg, '--tenant=')) {
        $onlyTenant = substr($arg, 9);
    }
}

$masterDb = \SoftNova\Core\Database::getInstance();
$sql = "SELECT database_name, database_user, database_password FROM tenants WHERE status = 'active'";
$params = [];
if ($onlyTenant) {
    $sql .= ' AND database_name = ?';
    $params[] = $onlyTenant;
}
$tenants = $masterDb->query($sql, $params)->fetchAll();
$tenantDb = \SoftNova\Core\TenantDatabase::getInstance();

echo 'Procesando jobs en ' . count($tenants) . " tenant(s)\n";
foreach ($tenants as $t) {
    $dbName = $t['database_name'];
    echo "- {$dbName}: ";
    try {
        $pdo = $tenantDb->getTenantConnection(
            $dbName,
            (string)($t['database_user'] ?? ''),
            (string)($t['database_password'] ?? '')
        );
        $_SESSION['tenant_db_name'] = $dbName;
        $results = (new \SoftNova\Services\JobRunner($pdo))->process($limit);
        echo count($results) . " job(s)\n";
        foreach ($results as $r) {
            echo '    #' . $r['id'] . ' ' . (!empty($r['ok']) ? 'OK' : ('FAIL ' . ($r['error'] ?? ''))) . "\n";
        }
    } catch (\Throwable $e) {
        echo 'ERROR ' . $e->getMessage() . "\n";
    }
}
echo "Listo.\n";
