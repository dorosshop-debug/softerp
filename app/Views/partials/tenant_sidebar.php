<?php
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

// Consultar módulos del plan desde BD maestra (siempre actualizado)
$allowedModules = [];
$showAll = true;
$tenantId = $_SESSION['tenant_id'] ?? 0;

if ($tenantId > 0) {
    $masterDb = \SoftNova\Core\Database::getInstance();
    $plan = $masterDb->query(
        "SELECT sp.modules FROM tenants t
         JOIN subscription_plans sp ON t.subscription_plan_id = sp.id
         WHERE t.id = ? LIMIT 1",
        [$tenantId]
    )->fetch();
    
    if ($plan && !empty($plan['modules'])) {
        $allowedModules = json_decode($plan['modules'], true) ?: [];
    }
}

// Filtrar siempre por plan (incluso admins solo ven lo que su plan permite)
$showAll = empty($allowedModules);

// Definición de módulos disponibles con sus rutas e iconos
$allModules = [
    'dashboard' => ['route' => 'app/dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
    'caja'      => ['route' => 'app/caja', 'label' => 'Caja', 'icon' => 'cash'],
    'ventas'    => ['route' => 'app/ventas', 'label' => 'Ventas', 'icon' => 'cart'],
    'inventario'=> ['route' => 'app/inventario', 'label' => 'Inventario', 'icon' => 'box'],
    'clientes'  => ['route' => 'app/clientes', 'label' => 'Clientes', 'icon' => 'users'],
    'proveedores'=>['route' => 'app/proveedores', 'label' => 'Proveedores', 'icon' => 'truck'],
    'soporte'   => ['route' => 'app/soporte', 'label' => 'Soporte', 'icon' => 'help'],
    'reportes'  => ['route' => 'app/reportes', 'label' => 'Reportes', 'icon' => 'file'],
    'cotizaciones'=>['route' => 'app/cotizaciones', 'label' => 'Cotizaciones', 'icon' => 'file'],
    'contabilidad'=>['route' => 'app/contabilidad', 'label' => 'Contabilidad', 'icon' => 'dollar'],
    'nomina'    => ['route' => 'app/nomina', 'label' => 'Nómina', 'icon' => 'users'],
];

function tenantIcon(string $name): string {
    return match ($name) {
        'home' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
        'cash' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>',
        'cart' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
        'box' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>',
        'users' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>',
        'truck' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
        'file' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>',
        'dollar' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
        'help' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
        default => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>',
    };
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h1><?php echo htmlspecialchars($tenantName); ?></h1>
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <span id="sidebarToggleIcon">«</span>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <?php foreach ($allModules as $key => $mod): ?>
                <?php if ($showAll || in_array($key, $allowedModules)): ?>
                    <li>
                        <a href="<?php echo $viewInstance->route($mod['route']); ?>" 
                           class="<?php echo str_contains($currentUri, '/app/' . $key) || ($key === 'dashboard' && str_ends_with($currentUri, '/app/dashboard')) ? 'active' : ''; ?>">
                            <span class="nav-icon"><?php echo tenantIcon($mod['icon']); ?></span>
                            <span class="nav-text"><?php echo $mod['label']; ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <!-- Configuración siempre visible -->
            <li>
                <a href="<?php echo $viewInstance->route('app/configuracion'); ?>" 
                   class="<?php echo str_contains($currentUri, '/app/configuracion') ? 'active' : ''; ?>">
                    <span class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                    </span>
                    <span class="nav-text">Configuración</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <p style="color: rgba(255,255,255,0.5); font-size: 11px; text-align: center;">
            <?php echo htmlspecialchars($userName); ?>
        </p>
    </div>
</aside>
