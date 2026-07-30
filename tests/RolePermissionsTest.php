<?php
/**
 * Tests de roles TenantMiddleware (canAccess / canDo).
 * Uso: php tests/RolePermissionsTest.php
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

use SoftNova\Core\TenantMiddleware;

$passed = 0;
$failed = 0;

function assertTrue(bool $cond, string $msg): void
{
    global $passed, $failed;
    if ($cond) {
        echo "  OK  {$msg}\n";
        $passed++;
    } else {
        echo " FAIL {$msg}\n";
        $failed++;
    }
}

function withRole(string $role, ?array $perms, callable $fn): void
{
    $_SESSION = [
        'tenant_authenticated' => true,
        'tenant_user_role' => $role,
        'tenant_permissions' => $perms ?? [],
        'tenant_modules' => [],
    ];
    if ($perms === null) {
        unset($_SESSION['tenant_permissions']);
    }
    $fn();
}

echo "=== RolePermissionsTest ===\n";

withRole('admin', null, function () {
    assertTrue(TenantMiddleware::canAccess('configuracion'), 'admin accede a configuracion');
    assertTrue(TenantMiddleware::canDo('delete', 'ventas'), 'admin puede delete ventas');
    assertTrue(TenantMiddleware::isAdmin(), 'admin isAdmin');
});

withRole('auxiliar', [], function () {
    assertTrue(TenantMiddleware::canAccess('caja'), 'User POS accede a caja');
    assertTrue(TenantMiddleware::canDo('create', 'ventas'), 'User POS crea ventas');
    assertTrue(!TenantMiddleware::canDo('delete', 'ventas'), 'User POS NO elimina ventas');
    assertTrue(!TenantMiddleware::canAccess('configuracion'), 'User POS sin configuracion');
    assertTrue(!TenantMiddleware::canAccess('reportes'), 'User POS sin reportes');
    assertTrue(TenantMiddleware::isPosUser(), 'auxiliar es POS');
    assertTrue(TenantMiddleware::homePath() === '/app/caja', 'home POS = caja');
});

withRole('user', [
    'ventas' => ['view' => true, 'create' => true, 'edit' => false, 'delete' => false],
    'clientes' => ['view' => true],
], function () {
    assertTrue(TenantMiddleware::canAccess('ventas'), 'user custom: ventas');
    assertTrue(TenantMiddleware::canDo('create', 'ventas'), 'user custom: create ventas');
    assertTrue(!TenantMiddleware::canDo('delete', 'ventas'), 'user custom: no delete ventas');
    assertTrue(TenantMiddleware::canAccess('clientes'), 'user custom: clientes');
    assertTrue(!TenantMiddleware::canAccess('inventario'), 'user custom: sin inventario');
    assertTrue(!TenantMiddleware::canAccess('configuracion'), 'user custom: sin config');
});

withRole('user', null, function () {
    // sin key en sesión → defaults manager
    assertTrue(TenantMiddleware::canAccess('inventario'), 'user sin custom: inventario (manager)');
    assertTrue(!TenantMiddleware::canAccess('configuracion'), 'user sin custom: no config');
});

echo "\nResultado: {$passed} ok, {$failed} fallos\n";
exit($failed > 0 ? 1 : 0);
