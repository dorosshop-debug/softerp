<?php
$layout = 'superadmin';
$title = 'Super Administrador - Configuración';
$pageTitle = 'Configuración';
$userName = $_SESSION['super_admin_name'] ?? 'Super Admin';
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="settings-container">
    <div class="settings-grid">
        <div class="settings-card neumorphic">
            <div class="settings-card-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <h3>Cambiar Contraseña</h3>
            <form method="POST" action="<?php echo $viewInstance->route('superadmin/settings'); ?>?action=change_password" class="settings-form" data-ajax="true" onsubmit="return validatePasswordForm(this)">
                <?php echo \SoftNova\Core\csrf_field(); ?>
                <div class="form-group">
                    <label>Contraseña Actual *</label>
                    <input type="password" name="current_password" required class="neumorphic-input">
                </div>
                <div class="form-group">
                    <label>Nueva Contraseña *</label>
                    <input type="password" name="new_password" required minlength="8" class="neumorphic-input">
                </div>
                <div class="form-group">
                    <label>Confirmar Nueva Contraseña *</label>
                    <input type="password" name="confirm_password" required minlength="8" class="neumorphic-input">
                </div>
                <button type="submit" class="btn btn-primary neumorphic-btn">Cambiar Contraseña</button>
            </form>
        </div>

        <div class="settings-card neumorphic">
            <div class="settings-card-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
            </div>
            <h3>Seguridad</h3>
            <div class="security-info">
                <div class="security-item">
                    <label>Último inicio de sesión</label>
                    <p><?php echo $_SESSION['last_login_at'] ?? 'No disponible'; ?></p>
                </div>
                <div class="security-item">
                    <label>IP de último acceso</label>
                    <p><?php echo $_SESSION['last_login_ip'] ?? 'No disponible'; ?></p>
                </div>
            </div>
        </div>

        <div class="settings-card neumorphic">
            <div class="settings-card-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
            </div>
            <h3>Apariencia</h3>
            <div class="theme-selector">
                <div class="theme-option" onclick="changeTheme('light')" id="themeLight">
                    <div class="theme-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                    </div>
                    <span>Modo Claro</span>
                </div>
                <div class="theme-option" onclick="changeTheme('dark')" id="themeDark">
                    <div class="theme-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                    </div>
                    <span>Modo Oscuro</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function validatePasswordForm(form) {
    const newPassword = form.querySelector('input[name="new_password"]').value;
    const confirmPassword = form.querySelector('input[name="confirm_password"]').value;
    
    if (newPassword !== confirmPassword) {
        alert('Las contraseñas nuevas no coinciden');
        return false;
    }
    
    if (newPassword.length < 8) {
        alert('La contraseña debe tener al menos 8 caracteres');
        return false;
    }
    
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.getElementById('themeDark').classList.add('active');
    } else {
        document.getElementById('themeLight').classList.add('active');
    }
});

function changeTheme(theme) {
    document.getElementById('themeLight').classList.remove('active');
    document.getElementById('themeDark').classList.remove('active');
    
    if (theme === 'dark') {
        document.getElementById('themeDark').classList.add('active');
    } else {
        document.getElementById('themeLight').classList.add('active');
    }
}
</script>
