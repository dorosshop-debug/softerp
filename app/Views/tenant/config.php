<?php
$layout = 'tenant';
$title = 'Configuración - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Configuración del Sistema';
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$settings = $settings ?? [];
$currencies = $currencies ?? [];
?>

<?php echo flashMessage(); ?>

<div class="settings-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px;">
    
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
    
    <!-- Apariencia -->
    <div class="card neumorphic">
        <div class="card-header">
            <h3>🎨 Apariencia</h3>
        </div>
        <div class="card-body">
            <p style="margin-bottom: 15px; color: var(--color-text-secondary);">Seleccione el tema visual del sistema.</p>
            <div style="display: flex; gap: 15px;">
                <button onclick="setTheme('light')" id="btnLight" class="btn btn-primary neumorphic-btn" style="flex:1;">
                    ☀️ Modo Claro
                </button>
                <button onclick="setTheme('dark')" id="btnDark" class="btn btn-secondary" style="flex:1;">
                    🌙 Modo Oscuro
                </button>
            </div>
            <script>
                function setTheme(theme) {
                    if (theme === 'dark') {
                        document.body.classList.add('dark-mode');
                        document.getElementById('btnDark').className = 'btn btn-primary neumorphic-btn';
                        document.getElementById('btnLight').className = 'btn btn-secondary';
                    } else {
                        document.body.classList.remove('dark-mode');
                        document.getElementById('btnLight').className = 'btn btn-primary neumorphic-btn';
                        document.getElementById('btnDark').className = 'btn btn-secondary';
                    }
                    localStorage.setItem('theme', theme);
                }
                document.addEventListener('DOMContentLoaded', function() {
                    if (localStorage.getItem('theme') === 'dark') {
                        setTheme('dark');
                    } else {
                        document.getElementById('btnLight').className = 'btn btn-primary neumorphic-btn';
                    }
                });
            </script>
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
