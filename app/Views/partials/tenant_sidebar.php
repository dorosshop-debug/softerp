<?php
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
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
            <li>
                <a href="<?php echo $viewInstance->route('app/dashboard'); ?>" class="<?php echo str_ends_with($currentUri, '/app/dashboard') ? 'active' : ''; ?>">
                    <span class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                    </span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $viewInstance->route('app/caja'); ?>" class="<?php echo str_contains($currentUri, '/app/caja') ? 'active' : ''; ?>">
                    <span class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                            <line x1="2" y1="10" x2="22" y2="10"></line>
                        </svg>
                    </span>
                    <span class="nav-text">Caja</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $viewInstance->route('app/ventas'); ?>" class="<?php echo str_contains($currentUri, '/app/ventas') ? 'active' : ''; ?>">
                    <span class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                    </span>
                    <span class="nav-text">Ventas</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $viewInstance->route('app/inventario'); ?>" class="<?php echo str_contains($currentUri, '/app/inventario') ? 'active' : ''; ?>">
                    <span class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        </svg>
                    </span>
                    <span class="nav-text">Inventario</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $viewInstance->route('app/clientes'); ?>" class="<?php echo str_contains($currentUri, '/app/clientes') ? 'active' : ''; ?>">
                    <span class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                        </svg>
                    </span>
                    <span class="nav-text">Clientes</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $viewInstance->route('app/proveedores'); ?>" class="<?php echo str_contains($currentUri, '/app/proveedores') ? 'active' : ''; ?>">
                    <span class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </span>
                    <span class="nav-text">Proveedores</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $viewInstance->route('app/gastos'); ?>" class="<?php echo str_contains($currentUri, '/app/gastos') ? 'active' : ''; ?>">
                    <span class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline>
                            <polyline points="17 6 23 6 23 12"></polyline>
                        </svg>
                    </span>
                    <span class="nav-text">Gastos</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $viewInstance->route('app/reportes'); ?>" class="<?php echo str_contains($currentUri, '/app/reportes') ? 'active' : ''; ?>">
                    <span class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                    </span>
                    <span class="nav-text">Reportes</span>
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
