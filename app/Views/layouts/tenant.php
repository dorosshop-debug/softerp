<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Seri ERP'); ?></title>
    <link rel="stylesheet" href="<?php echo $viewInstance->asset('css/style.css'); ?>">
</head>
<body>
    <div class="app-layout">
        <?php $viewInstance->partial('tenant_sidebar', [
            'tenantName' => $tenantName ?? 'Sistema',
            'userName' => $userName ?? 'Usuario',
        ]); ?>
        
        <div class="main-content" id="mainContent">
            <header class="header">
                <div class="header-left">
                    <h2><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h2>
                </div>
                <div class="header-search" id="globalSearch">
                    <div class="search-input-wrapper">
                        <span class="search-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </span>
                        <input type="text" class="search-input" id="globalSearchInput" placeholder="Buscar en Seri ERP... (clientes, productos, ventas)" autocomplete="off" data-search-url="<?php echo $viewInstance->route('app/dashboard'); ?>">
                        <button type="button" id="searchClear" class="search-clear" style="display:none;" onclick="clearSearch()">&times;</button>
                        <div class="search-results" id="searchResults" style="display:none;"></div>
                    </div>
                </div>
                <div class="header-right">
                    <a href="<?php echo $viewInstance->route('app/soporte'); ?>" class="header-icon-btn" title="Soporte Técnico">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span class="header-icon-label">Soporte</span>
                    </a>
                    <a href="<?php echo $viewInstance->route('app/ia'); ?>" class="header-icon-btn" title="Asistente IA">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                        <span class="header-icon-label">Asistente IA</span>
                    </a>
                    <div class="datetime-info">
                        <div class="datetime-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <div class="datetime-text">
                            <span id="currentDate"></span>
                            <span id="currentTime"></span>
                        </div>
                    </div>
                    <div class="user-dropdown" id="userDropdown">
                        <button class="user-dropdown-btn" onclick="toggleUserMenu()">
                            <span class="user-avatar">
                                <?php
                                $avatarUrl = null;
                                try {
                                    $avatarDb = \SoftNova\Core\TenantMiddleware::getDb();
                                    $av = $avatarDb->prepare("SELECT setting_value FROM settings WHERE setting_key = 'user_avatar'");
                                    $av->execute();
                                    $avatarUrl = $av->fetchColumn() ?: null;
                                } catch (\Exception $e) { /* silencioso */ }
                                if ($avatarUrl): ?>
                                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($userName ?? 'U', 0, 1)); ?>
                                <?php endif; ?>
                            </span>
                            <span class="user-name"><?php echo htmlspecialchars($userName ?? 'Usuario'); ?></span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <div class="user-dropdown-menu" id="userMenu" style="display:none;">
                            <a href="<?php echo $viewInstance->route('app/configuracion'); ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                                Configuración
                            </a>
                            <a href="<?php echo $viewInstance->route('app/logout'); ?>" style="color:var(--color-error);">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                Cerrar Sesión
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            
            <div class="content">
                <?php if (!empty($pageTitle)): ?>
                <div class="breadcrumbs">
                    <a href="<?php echo $viewInstance->route('app/dashboard'); ?>">Dashboard</a>
                    <span class="separator">›</span>
                    <span class="current"><?php echo htmlspecialchars($pageTitle); ?></span>
                </div>
                <?php endif; ?>
                <?php echo $content; ?>
            </div>
            
            <footer class="app-footer">
                <p>Seri ERP &copy; 2026 | Osgo Support</p>
            </footer>
        </div>
    </div>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleIcon = document.getElementById('sidebarToggleIcon');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            toggleIcon.textContent = sidebar.classList.contains('collapsed') ? '»' : '«';
        }
        
        function updateDateTime() {
            const now = new Date();
            const optionsDate = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const optionsTime = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            if (dateEl) dateEl.textContent = now.toLocaleDateString('es-ES', optionsDate);
            if (timeEl) timeEl.textContent = now.toLocaleTimeString('es-ES', optionsTime);
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        function toggleUserMenu() {
            var menu = document.getElementById('userMenu');
            if (menu) menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }
        
        document.addEventListener('click', function(e) {
            var dd = document.getElementById('userDropdown');
            if (dd && !dd.contains(e.target)) {
                var menu = document.getElementById('userMenu');
                if (menu) menu.style.display = 'none';
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') document.body.classList.add('dark-mode');
        });
    </script>
    <script src="<?php echo $viewInstance->asset('js/app.js'); ?>"></script>
</body>
</html>
