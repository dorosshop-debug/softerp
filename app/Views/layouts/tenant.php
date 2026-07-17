<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'EVA ERP'); ?></title>
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
                <div class="header-right">
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
                    <span class="user-name"><?php echo htmlspecialchars($userName ?? 'Usuario'); ?></span>
                    <a href="<?php echo $viewInstance->route('app/logout'); ?>" class="logout-btn">Cerrar Sesión</a>
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
                <p>EVA ERP &copy; 2026 | Osgo Support</p>
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
        
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') document.body.classList.add('dark-mode');
        });
    </script>
    <script src="<?php echo $viewInstance->asset('js/app.js'); ?>"></script>
</body>
</html>
