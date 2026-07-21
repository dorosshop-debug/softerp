<?php
$layout = 'tenant';
$title = 'Configuración - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Configuración del Sistema';
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$settings = $settings ?? [];
$currencies = $currencies ?? [];
$languages = $languages ?? [];
$currentUser = $currentUser ?? null;
$subscription = $subscription ?? null;
$currentLang = $settings['language'] ?? 'es';
$avatarUrl = $settings['user_avatar'] ?? null;
?>

<?php echo flashMessage(); ?>

<div class="settings-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px;">
    
    <!-- Perfil de Usuario -->
    <div class="card neumorphic">
        <div class="card-header">
            <h3>👤 Perfil de Usuario</h3>
        </div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:20px;margin-bottom:20px;">
                <div style="position:relative;">
                    <div id="avatarPreview" style="width:80px;height:80px;border-radius:50%;background-color:var(--color-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:32px;font-weight:700;overflow:hidden;">
                        <?php if ($avatarUrl): ?>
                            <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($userName, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <label for="avatarInput" style="position:absolute;bottom:0;right:0;width:28px;height:28px;background:var(--color-primary);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;font-size:14px;border:2px solid var(--bg-card);" title="Cambiar foto">📷</label>
                    <form id="avatarForm" method="POST" action="<?php echo $viewInstance->route('app/configuracion'); ?>?action=uploadAvatar" enctype="multipart/form-data" style="display:none;">
                        <?php echo \SoftNova\Core\csrf_field(); ?>
                        <input type="file" id="avatarInput" name="avatar" accept="image/*" onchange="uploadAvatar(this)">
                    </form>
                </div>
                <div>
                    <strong style="font-size:16px;"><?php echo htmlspecialchars($currentUser['name'] ?? $userName); ?></strong>
                    <p style="color:var(--color-text-secondary);font-size:13px;margin:4px 0;"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></p>
                    <span class="badge badge-info" style="font-size:11px;"><?php echo ucfirst($currentUser['role'] ?? 'admin'); ?></span>
                    <?php if (!empty($currentUser['last_login_at'])): ?>
                        <p style="color:var(--color-text-secondary);font-size:11px;margin-top:4px;">Último acceso: <?php echo date('d/m/Y H:i', strtotime($currentUser['last_login_at'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <hr style="border-color:var(--color-border);margin:15px 0;">
            <h4 style="margin-bottom:12px;">🔒 Cambiar Contraseña</h4>
            <form method="POST" action="<?php echo $viewInstance->route('app/configuracion'); ?>?action=changePassword" data-ajax="true">
                <?php echo \SoftNova\Core\csrf_field(); ?>
                <div class="form-group">
                    <label>Contraseña Actual</label>
                    <input type="password" name="current_password" class="form-control" required placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label>Nueva Contraseña</label>
                    <input type="password" name="new_password" class="form-control" required placeholder="Mínimo 6 caracteres" minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirmar Nueva Contraseña</label>
                    <input type="password" name="confirm_password" class="form-control" required placeholder="Repetir contraseña" minlength="6">
                </div>
                <button type="submit" class="btn btn-primary">Actualizar Contraseña</button>
            </form>
        </div>
    </div>

    <!-- Datos de Empresa -->
    <div class="card neumorphic">
        <div class="card-header">
            <h3>🏢 Datos de la Empresa</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo $viewInstance->route('app/configuracion'); ?>?action=save" data-ajax="true">
                <?php echo \SoftNova\Core\csrf_field(); ?>
                <div class="form-group">
                    <label>Nombre de la Empresa</label>
                    <input type="text" name="company_name" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" 
                           placeholder="Mi Empresa">
                </div>
                <div class="form-group">
                    <label>Nombre del Impuesto</label>
                    <input type="text" name="tax_name" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['tax_name'] ?? 'IVA'); ?>">
                </div>
                <div class="form-group">
                    <label>Tasa de Impuesto (%)</label>
                    <input type="number" name="tax_rate" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '19'); ?>" 
                           step="0.1" min="0" max="100" placeholder="19">
                </div>
                <button type="submit" class="btn btn-primary">Guardar Datos</button>
            </form>
        </div>
    </div>
    
    <!-- Moneda -->
    <div class="card neumorphic">
        <div class="card-header">
            <h3>💱 Moneda del Sistema</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo $viewInstance->route('app/configuracion'); ?>?action=save" data-ajax="true">
                <?php echo \SoftNova\Core\csrf_field(); ?>
                <div class="form-group">
                    <label>Seleccione la moneda principal</label>
                    <select name="currency" class="form-control" required>
                        <?php foreach ($currencies as $code => $cur): ?>
                            <option value="<?php echo $code; ?>" <?php echo ($settings['currency'] ?? 'COP') === $code ? 'selected' : ''; ?>>
                                <?php echo $cur['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--color-text-secondary);">Esta moneda se usará en ventas, caja y reportes.</small>
                </div>
                <button type="submit" class="btn btn-primary">Guardar Moneda</button>
            </form>
        </div>
    </div>
    
    <!-- Apariencia e Idioma -->
    <div class="card neumorphic">
        <div class="card-header"><h3>🎨 Apariencia e Idioma</h3></div>
        <div class="card-body">
            <p style="margin-bottom:12px;color:var(--color-text-secondary);font-size:13px;">Tema visual</p>
            <div style="display:flex;gap:10px;margin-bottom:20px;">
                <button onclick="setTheme('light')" id="btnLight" class="btn btn-secondary" style="flex:1;">☀️ Claro</button>
                <button onclick="setTheme('dark')" id="btnDark" class="btn btn-secondary" style="flex:1;">🌙 Oscuro</button>
            </div>
            
            <p style="margin-bottom:12px;color:var(--color-text-secondary);font-size:13px;">🌐 Idioma del Sistema</p>
            <form method="POST" action="<?php echo $viewInstance->route('app/configuracion'); ?>?action=save" data-ajax="true" id="langForm">
                <?php echo \SoftNova\Core\csrf_field(); ?>
                <input type="hidden" name="language" id="langInput" value="<?php echo $currentLang; ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <?php foreach ($languages as $code => $lang): ?>
                        <button type="button" 
                                onclick="setLang('<?php echo $code; ?>')" 
                                id="btnLang<?php echo $code; ?>"
                                class="btn <?php echo $currentLang === $code ? 'btn-primary neumorphic-btn' : 'btn-secondary'; ?>"
                                style="font-size:13px;text-align:left;">
                            <?php echo $lang['flag']; ?> <?php echo $lang['name']; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Suscripción -->
    <div class="card neumorphic">
        <div class="card-header"><h3>💳 Suscripción</h3></div>
        <div class="card-body">
            <?php if ($subscription): ?>
                <div style="margin-bottom:15px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span style="color:var(--color-text-secondary);">Plan:</span>
                        <strong><?php echo htmlspecialchars($subscription['plan_name']); ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span style="color:var(--color-text-secondary);">Precio Mensual:</span>
                        <strong>$<?php echo number_format($subscription['monthly_price'] ?? 0, 2); ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span style="color:var(--color-text-secondary);">Precio Anual:</span>
                        <strong>$<?php echo number_format($subscription['annual_price'] ?? 0, 2); ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span style="color:var(--color-text-secondary);">Estado:</span>
                        <span class="badge <?php echo $subscription['subscription_status'] === 'active' ? 'badge-success' : ($subscription['subscription_status'] === 'pending' ? 'badge-warning' : 'badge-danger'); ?>">
                            <?php echo $subscription['subscription_status'] === 'active' ? 'Activo' : ($subscription['subscription_status'] === 'pending' ? 'Pendiente' : ucfirst($subscription['subscription_status'])); ?>
                        </span>
                    </div>
                    <?php if (!empty($subscription['subscription_end_date'])): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="color:var(--color-text-secondary);">Vence:</span>
                            <strong><?php echo date('d/m/Y', strtotime($subscription['subscription_end_date'])); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
                <hr style="border-color:var(--color-border);margin:15px 0;">
            <?php endif; ?>
            <p style="color:var(--color-text-secondary);font-size:13px;margin-bottom:12px;">¿Necesita renovar o cambiar de plan? Contáctenos para gestionar su suscripción.</p>
            <a href="<?php echo $viewInstance->route('app/soporte'); ?>" class="btn btn-primary neumorphic-btn" style="width:100%;">📞 Solicitar Soporte / Renovación</a>
        </div>
    </div>
    
    <!-- Preferencias -->
    <div class="card neumorphic">
        <div class="card-header">
            <h3>⚙️ Preferencias</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo $viewInstance->route('app/configuracion'); ?>?action=save" data-ajax="true">
                <?php echo \SoftNova\Core\csrf_field(); ?>
                <div class="form-group">
                    <label>Prefijo de Factura</label>
                    <input type="text" name="invoice_prefix" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['invoice_prefix'] ?? 'FAC-'); ?>">
                </div>
                <div class="form-group">
                    <label>Alerta de Stock Bajo</label>
                    <input type="number" name="low_stock_alert" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['low_stock_alert'] ?? '5'); ?>" 
                           min="1" placeholder="5">
                    <small style="color: var(--color-text-secondary);">Se alertará cuando el stock esté por debajo de este valor.</small>
                </div>
                <button type="submit" class="btn btn-primary">Guardar Preferencias</button>
            </form>
        </div>
    </div>
</div>

<script>
function setTheme(t){
    if(t==='dark'){document.body.classList.add('dark-mode');document.getElementById('btnDark').className='btn btn-primary neumorphic-btn';document.getElementById('btnLight').className='btn btn-secondary';}
    else{document.body.classList.remove('dark-mode');document.getElementById('btnLight').className='btn btn-primary neumorphic-btn';document.getElementById('btnDark').className='btn btn-secondary';}
    localStorage.setItem('theme',t);
}

function setLang(l){
    // Actualizar botones visualmente
    document.querySelectorAll('[id^="btnLang"]').forEach(function(b){ b.className = 'btn btn-secondary'; });
    var activeBtn = document.getElementById('btnLang' + l);
    if (activeBtn) activeBtn.className = 'btn btn-primary neumorphic-btn';
    
    // Guardar en localStorage y enviar al servidor
    localStorage.setItem('lang', l);
    document.getElementById('langInput').value = l;
    
    var fd = new FormData(document.getElementById('langForm'));
    fetch(document.getElementById('langForm').action, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) showAlert('Idioma guardado. Recargue la página para aplicar los cambios.', 'success');
    });
}

function uploadAvatar(input) {
    if (!input.files || !input.files[0]) return;
    var form = document.getElementById('avatarForm');
    var fd = new FormData(form);
    fetch(form.action, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            showAlert(d.message, 'success');
            // Actualizar preview
            var reader = new FileReader();
            reader.onload = function(e) {
                var preview = document.getElementById('avatarPreview');
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">';
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            showAlert(d.message || 'Error al subir imagen', 'error');
        }
    }).catch(function() {
        showAlert('Error de conexión', 'error');
    });
}

(function(){
    if(localStorage.getItem('theme')==='dark') setTheme('dark'); else document.getElementById('btnLight').className='btn btn-primary neumorphic-btn';
    var savedLang = localStorage.getItem('lang') || '<?php echo $currentLang; ?>';
    var activeBtn = document.getElementById('btnLang' + savedLang);
    if (activeBtn) activeBtn.className = 'btn btn-primary neumorphic-btn';
})();
</script>
