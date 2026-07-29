<?php
/** E-commerce (WooCommerce / Mercado Libre) en Configuración */
$catalogStatuses = $catalogStatuses ?? [];
$ecomProvider = $ecomProvider ?? 'woocommerce';
if (!isset($catalogStatuses[$ecomProvider])) {
    $ecomProvider = array_key_first($catalogStatuses) ?: 'woocommerce';
}
$st = $catalogStatuses[$ecomProvider] ?? [];
$form = $st['form'] ?? [];
$canEditEcom = \SoftNova\Core\TenantMiddleware::canDo('edit', 'configuracion');
$routeCfg = $viewInstance->route('app/configuracion');
$mlOAuthRedirect = $mlOAuthRedirect ?? ($routeCfg . '?action=ml-oauth-callback');
$meta = [
    'woocommerce' => 'Importa productos de su tienda WooCommerce al inventario.',
    'mercadolibre' => 'Importa publicaciones activas de Mercado Libre al inventario.',
];
?>
<div class="card neumorphic" id="ecommerceSection" style="grid-column:1/-1;">
    <div class="card-header"><h3>E-commerce (catálogo)</h3></div>
    <div class="card-body">
        <p style="margin-top:0;color:var(--color-text-secondary);font-size:13px;">
            Configure WooCommerce o Mercado Libre aquí. Luego importe productos desde Inventario.
            La facturación electrónica sigue en Contabilidad → Integraciones.
        </p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
            <?php foreach ($catalogStatuses as $code => $row): ?>
                <a class="btn <?php echo $ecomProvider === $code ? 'btn-primary' : 'btn-secondary'; ?>"
                   href="<?php echo $routeCfg; ?>?section=ecommerce&provider=<?php echo urlencode($code); ?>#ecommerceSection">
                    <?php echo htmlspecialchars($row['label'] ?? $code); ?>
                    <?php if (!empty($row['enabled']) && !empty($row['configured'])): ?>
                        <span class="badge badge-success" style="margin-left:6px;">Listo</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            <a class="btn btn-secondary" href="<?php echo $viewInstance->route('app/inventario'); ?>">Ir a Inventario</a>
        </div>

        <h4 style="margin:0 0 8px;"><?php echo htmlspecialchars($st['label'] ?? $ecomProvider); ?></h4>
        <p style="color:var(--color-text-secondary);font-size:13px;"><?php echo htmlspecialchars($meta[$ecomProvider] ?? ''); ?></p>
        <p>Estado:
            <?php if (!empty($st['configured'])): ?><span class="badge badge-success">Listo</span>
            <?php elseif (!empty($st['enabled'])): ?><span class="badge badge-warning">Faltan credenciales</span>
            <?php else: ?><span class="badge badge-danger">Deshabilitado</span><?php endif; ?>
        </p>

        <?php if ($canEditEcom): ?>
        <form method="POST" action="<?php echo $routeCfg; ?>?action=save-catalog-integration" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="provider" value="<?php echo htmlspecialchars($ecomProvider); ?>">
            <div class="form-group">
                <label><input type="checkbox" name="enabled" value="1" <?php echo !empty($form['enabled']) && $form['enabled'] !== '0' ? 'checked' : ''; ?>> Habilitar conector</label>
            </div>

            <?php if ($ecomProvider === 'woocommerce'): ?>
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;"><label>URL de la tienda</label><input class="form-control" name="store_url" value="<?php echo htmlspecialchars($form['store_url'] ?? ''); ?>" placeholder="https://mitienda.com"></div>
                    <div class="form-group"><label>Consumer Key</label><input class="form-control" name="consumer_key" value="<?php echo htmlspecialchars($form['consumer_key'] ?? ''); ?>" autocomplete="off"></div>
                    <div class="form-group"><label>Consumer Secret <?php if (!empty($form['consumer_secret_set'])): ?><small>(guardado)</small><?php endif; ?></label><input class="form-control" type="password" name="consumer_secret" placeholder="<?php echo !empty($form['consumer_secret_set']) ? 'Dejar vacío para conservar' : 'Secret'; ?>" autocomplete="new-password"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Política de stock</label>
                        <?php $auth = $form['stock_authority'] ?? 'create_only'; ?>
                        <select class="form-control" name="stock_authority">
                            <option value="create_only" <?php echo $auth === 'create_only' ? 'selected' : ''; ?>>Solo al crear (recomendado)</option>
                            <option value="erp" <?php echo $auth === 'erp' ? 'selected' : ''; ?>>ERP manda</option>
                            <option value="store" <?php echo $auth === 'store' ? 'selected' : ''; ?>>Tienda manda</option>
                        </select>
                    </div>
                </div>
            <?php else: ?>
                <div class="form-grid">
                    <div class="form-group"><label>Client ID</label><input class="form-control" name="client_id" value="<?php echo htmlspecialchars($form['client_id'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Client Secret <?php if (!empty($form['client_secret_set'])): ?><small>(guardado)</small><?php endif; ?></label><input class="form-control" type="password" name="client_secret" placeholder="<?php echo !empty($form['client_secret_set']) ? 'Dejar vacío para conservar' : 'Secret'; ?>" autocomplete="new-password"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Redirect URI (registre esta URL en su app ML)</label>
                        <input class="form-control" readonly value="<?php echo htmlspecialchars($mlOAuthRedirect); ?>">
                    </div>
                    <div class="form-group"><label>Site ID</label><input class="form-control" name="site_id" value="<?php echo htmlspecialchars($form['site_id'] ?? 'MCO'); ?>"></div>
                    <div class="form-group"><label>User ID</label><input class="form-control" name="user_id" value="<?php echo htmlspecialchars($form['user_id'] ?? ''); ?>"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Access Token <?php if (!empty($form['access_token_set'])): ?><small>(guardado)</small><?php endif; ?></label><input class="form-control" type="password" name="access_token" placeholder="<?php echo !empty($form['access_token_set']) ? 'Dejar vacío para conservar' : 'Opcional si usa OAuth'; ?>" autocomplete="new-password"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Refresh Token <?php if (!empty($form['refresh_token_set'])): ?><small>(guardado)</small><?php endif; ?></label><input class="form-control" type="password" name="refresh_token" placeholder="<?php echo !empty($form['refresh_token_set']) ? 'Dejar vacío para conservar' : ''; ?>" autocomplete="new-password"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Política de stock</label>
                        <?php $auth = $form['stock_authority'] ?? 'create_only'; ?>
                        <select class="form-control" name="stock_authority">
                            <option value="create_only" <?php echo $auth === 'create_only' ? 'selected' : ''; ?>>Solo al crear</option>
                            <option value="erp" <?php echo $auth === 'erp' ? 'selected' : ''; ?>>ERP manda</option>
                            <option value="store" <?php echo $auth === 'store' ? 'selected' : ''; ?>>ML manda</option>
                        </select>
                    </div>
                </div>
                <p style="margin:10px 0;">
                    <a class="btn btn-secondary" href="<?php echo $routeCfg; ?>?action=ml-oauth-start">Conectar con Mercado Libre (OAuth)</a>
                </p>
            <?php endif; ?>

            <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn btn-secondary" onclick="testCatalogProvider(this,'<?php echo htmlspecialchars($ecomProvider); ?>')">Probar conexión</button>
                <span id="catalogTestResult" style="font-size:13px;"></span>
            </div>
        </form>
        <script>
        function testCatalogProvider(btn, provider) {
            var result = document.getElementById('catalogTestResult');
            btn.disabled = true;
            result.textContent = 'Probando...';
            fetch(<?php echo json_encode($routeCfg); ?> + '?action=catalog-test&provider=' + encodeURIComponent(provider), {
                headers: {'X-Requested-With':'XMLHttpRequest'}
            }).then(function(r){return r.json();}).then(function(d){
                result.textContent = d.message || (d.success ? 'OK' : 'Error');
                result.style.color = d.success ? '#10B981' : '#DC2626';
            }).catch(function(){
                result.textContent = 'No se pudo probar';
                result.style.color = '#DC2626';
            }).finally(function(){ btn.disabled = false; });
        }
        </script>
        <?php else: ?>
            <p style="color:var(--color-text-secondary);">No tiene permiso para editar integraciones.</p>
        <?php endif; ?>
    </div>
</div>
