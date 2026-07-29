<?php
/** Integraciones FE dentro de Contabilidad (sin e-commerce). */
$providerMeta = [
    'alegra' => 'Emite factura electrónica DIAN vía Alegra (proveedor autorizado).',
    'siigo' => 'Emite documentos electrónicos vía Siigo Nube API.',
    'factus' => 'API especializada en facturación electrónica Colombia.',
    'dian' => 'Datos fiscales para emisión directa. Requiere ser Proveedor Tecnológico o factura gratuita DIAN.',
];
$testAction = 'integration-test';
$st = $integrationStatuses[$selectedProvider] ?? [];
$form = $st['form'] ?? [];
$routeBase = $viewInstance->route('app/contabilidad');
?>
<div class="alert alert-info" style="margin-bottom:16px;">
    Aquí se configuran las integraciones de <strong>facturación electrónica</strong> (Alegra, Siigo, Factus, DIAN).
    WooCommerce y Mercado Libre están en
    <a href="<?php echo $viewInstance->route('app/configuracion'); ?>?section=ecommerce"><strong>Configuración → E-commerce</strong></a>.
</div>

<div class="card neumorphic" style="margin-bottom:18px;">
    <div class="card-header"><h3>Proveedor activo (facturación electrónica)</h3></div>
    <div class="card-body">
        <?php if ($canEdit): ?>
        <form method="POST" action="<?php echo $routeBase; ?>?action=set-active-provider" data-ajax="true" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="form-group" style="margin:0;min-width:220px;">
                <label>Usar para facturación electrónica</label>
                <select class="form-control" name="provider">
                    <option value="">Ninguno</option>
                    <?php foreach ($integrationStatuses as $code => $row): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $activeProvider === $code ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($row['label'] ?? $code); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Guardar proveedor activo</button>
        </form>
        <?php else: ?>
            <p style="margin:0;">Activo: <strong><?php echo htmlspecialchars($activeProvider ?: 'Ninguno'); ?></strong></p>
        <?php endif; ?>
    </div>
</div>

<h4 style="margin:8px 0 10px;">Facturación electrónica</h4>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
    <?php foreach ($integrationStatuses as $code => $row): ?>
        <a class="btn <?php echo $selectedProvider === $code ? 'btn-primary' : 'btn-secondary'; ?>"
           href="<?php echo $routeBase; ?>?tab=integrations&provider=<?php echo urlencode($code); ?>">
            <?php echo htmlspecialchars($row['label'] ?? $code); ?>
            <?php if (!empty($row['active'])): ?><span class="badge badge-success" style="margin-left:6px;">Activo</span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card neumorphic">
    <div class="card-header">
        <h3><?php echo htmlspecialchars($st['label'] ?? $selectedProvider); ?></h3>
    </div>
    <div class="card-body">
        <p style="margin-top:0;color:var(--color-text-secondary);">
            <?php echo htmlspecialchars($providerMeta[$selectedProvider] ?? ''); ?>
        </p>
        <p>
            Estado:
            <?php if (!empty($st['configured'])): ?>
                <span class="badge badge-success">Listo</span>
            <?php elseif (!empty($st['enabled'])): ?>
                <span class="badge badge-warning">Habilitado, faltan credenciales</span>
            <?php else: ?>
                <span class="badge badge-danger">Deshabilitado</span>
            <?php endif; ?>
        </p>

        <?php if ($canEdit): ?>
        <form method="POST" action="<?php echo $routeBase; ?>?action=save-integration" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="provider" value="<?php echo htmlspecialchars($selectedProvider); ?>">
            <div class="form-grid" style="margin-bottom:12px;">
                <div class="form-group">
                    <label><input type="checkbox" name="enabled" value="1" <?php echo !empty($form['enabled']) && $form['enabled'] !== '0' ? 'checked' : ''; ?>> Habilitar conector</label>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="make_active" value="1" <?php echo $activeProvider === $selectedProvider ? 'checked' : ''; ?>> Marcar como proveedor activo</label>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="sync_sales" value="1" <?php echo !empty($form['sync_sales']) && $form['sync_sales'] !== '0' ? 'checked' : ''; ?>> Sincronizar ventas</label>
                </div>
            </div>

            <?php if ($selectedProvider === 'alegra'): ?>
                <div class="form-grid">
                    <div class="form-group"><label>Email Alegra</label><input class="form-control" name="email" value="<?php echo htmlspecialchars($form['email'] ?? ''); ?>" autocomplete="off"></div>
                    <div class="form-group"><label>Token API <?php if (!empty($form['token_set'])): ?><small>(guardado)</small><?php endif; ?></label><input class="form-control" type="password" name="token" placeholder="<?php echo !empty($form['token_set']) ? 'Dejar vacío para conservar' : 'Token'; ?>" autocomplete="new-password"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Base URL</label><input class="form-control" name="base_url" value="<?php echo htmlspecialchars($form['base_url'] ?? 'https://api.alegra.com/api/v1'); ?>"></div>
                    <div class="form-group"><label>ID impuesto IVA</label><input class="form-control" name="tax_id" value="<?php echo htmlspecialchars($form['tax_id'] ?? ''); ?>"></div>
                    <div class="form-group"><label><input type="checkbox" name="stamp" value="1" <?php echo !empty($form['stamp']) && $form['stamp'] !== '0' ? 'checked' : ''; ?>> Timbrar DIAN</label></div>
                    <div class="form-group"><label><input type="checkbox" name="sync_payments" value="1" <?php echo !empty($form['sync_payments']) && $form['sync_payments'] !== '0' ? 'checked' : ''; ?>> Sync abonos</label></div>
                    <div class="form-group"><label><input type="checkbox" name="sync_expenses" value="1" <?php echo !empty($form['sync_expenses']) && $form['sync_expenses'] !== '0' ? 'checked' : ''; ?>> Sync gastos</label></div>
                </div>
            <?php elseif ($selectedProvider === 'siigo'): ?>
                <div class="form-grid">
                    <div class="form-group"><label>Usuario API</label><input class="form-control" name="username" value="<?php echo htmlspecialchars($form['username'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Access Key <?php if (!empty($form['access_key_set'])): ?><small>(guardado)</small><?php endif; ?></label><input class="form-control" type="password" name="access_key" placeholder="<?php echo !empty($form['access_key_set']) ? 'Dejar vacío para conservar' : 'Access key'; ?>" autocomplete="new-password"></div>
                    <div class="form-group"><label>Partner-Id</label><input class="form-control" name="partner_id" value="<?php echo htmlspecialchars($form['partner_id'] ?? 'SeriERP'); ?>"></div>
                    <div class="form-group"><label>Base URL</label><input class="form-control" name="base_url" value="<?php echo htmlspecialchars($form['base_url'] ?? 'https://api.siigo.com'); ?>"></div>
                    <div class="form-group"><label>ID tipo documento</label><input class="form-control" name="document_id" value="<?php echo htmlspecialchars($form['document_id'] ?? ''); ?>"></div>
                    <div class="form-group"><label>ID vendedor</label><input class="form-control" name="seller_id" value="<?php echo htmlspecialchars($form['seller_id'] ?? ''); ?>"></div>
                    <div class="form-group"><label>ID medio de pago</label><input class="form-control" name="payment_type_id" value="<?php echo htmlspecialchars($form['payment_type_id'] ?? ''); ?>"></div>
                    <div class="form-group"><label>ID impuesto IVA</label><input class="form-control" name="tax_id" value="<?php echo htmlspecialchars($form['tax_id'] ?? ''); ?>"></div>
                    <div class="form-group"><label>ID grupo contable</label><input class="form-control" name="account_group_id" value="<?php echo htmlspecialchars($form['account_group_id'] ?? ''); ?>"></div>
                    <div class="form-group"><label><input type="checkbox" name="stamp" value="1" <?php echo !empty($form['stamp']) && $form['stamp'] !== '0' ? 'checked' : ''; ?>> Timbrar DIAN</label></div>
                </div>
            <?php elseif ($selectedProvider === 'factus'): ?>
                <div class="form-grid">
                    <div class="form-group"><label>Client ID</label><input class="form-control" name="client_id" value="<?php echo htmlspecialchars($form['client_id'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Client Secret <?php if (!empty($form['client_secret_set'])): ?><small>(guardado)</small><?php endif; ?></label><input class="form-control" type="password" name="client_secret" placeholder="<?php echo !empty($form['client_secret_set']) ? 'Dejar vacío para conservar' : 'Secret'; ?>" autocomplete="new-password"></div>
                    <div class="form-group"><label>Usuario</label><input class="form-control" name="username" value="<?php echo htmlspecialchars($form['username'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Password <?php if (!empty($form['password_set'])): ?><small>(guardado)</small><?php endif; ?></label><input class="form-control" type="password" name="password" placeholder="<?php echo !empty($form['password_set']) ? 'Dejar vacío para conservar' : 'Password'; ?>" autocomplete="new-password"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Base URL</label><input class="form-control" name="base_url" value="<?php echo htmlspecialchars($form['base_url'] ?? 'https://api-sandbox.factus.com.co'); ?>"></div>
                    <div class="form-group"><label>ID rango numeración</label><input class="form-control" name="numbering_range_id" value="<?php echo htmlspecialchars($form['numbering_range_id'] ?? ''); ?>"></div>
                    <div class="form-group"><label>% IVA</label><input class="form-control" name="tax_rate" value="<?php echo htmlspecialchars($form['tax_rate'] ?? '19'); ?>"></div>
                    <div class="form-group"><label>ID municipio</label><input class="form-control" name="municipality_id" value="<?php echo htmlspecialchars($form['municipality_id'] ?? ''); ?>"></div>
                </div>
            <?php else: ?>
                <div class="form-grid">
                    <div class="form-group"><label>NIT</label><input class="form-control" name="nit" value="<?php echo htmlspecialchars($form['nit'] ?? ''); ?>"></div>
                    <div class="form-group"><label>DV</label><input class="form-control" name="dv" value="<?php echo htmlspecialchars($form['dv'] ?? ''); ?>" maxlength="1"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Razón social</label><input class="form-control" name="legal_name" value="<?php echo htmlspecialchars($form['legal_name'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Resolución</label><input class="form-control" name="resolution_number" value="<?php echo htmlspecialchars($form['resolution_number'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Prefijo</label><input class="form-control" name="prefix" value="<?php echo htmlspecialchars($form['prefix'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Rango desde</label><input class="form-control" name="range_from" value="<?php echo htmlspecialchars($form['range_from'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Rango hasta</label><input class="form-control" name="range_to" value="<?php echo htmlspecialchars($form['range_to'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Software ID</label><input class="form-control" name="software_id" value="<?php echo htmlspecialchars($form['software_id'] ?? ''); ?>"></div>
                    <div class="form-group"><label>PIN <?php if (!empty($form['pin_set'])): ?><small>(guardado)</small><?php endif; ?></label><input class="form-control" type="password" name="pin" placeholder="<?php echo !empty($form['pin_set']) ? 'Dejar vacío para conservar' : 'PIN'; ?>" autocomplete="new-password"></div>
                    <div class="form-group"><label>Clave técnica <?php if (!empty($form['technical_key_set'])): ?><small>(guardado)</small><?php endif; ?></label><input class="form-control" type="password" name="technical_key" placeholder="<?php echo !empty($form['technical_key_set']) ? 'Dejar vacío para conservar' : 'Clave técnica'; ?>" autocomplete="new-password"></div>
                    <div class="form-group"><label>Ambiente</label>
                        <select class="form-control" name="environment">
                            <option value="habilitacion" <?php echo ($form['environment'] ?? '') === 'habilitacion' ? 'selected' : ''; ?>>Habilitación</option>
                            <option value="produccion" <?php echo ($form['environment'] ?? '') === 'produccion' ? 'selected' : ''; ?>>Producción</option>
                        </select>
                    </div>
                </div>
                <div class="alert alert-warning" style="margin-top:12px;">Sin ser Proveedor Tecnológico DIAN, use Alegra, Siigo o Factus.</div>
            <?php endif; ?>

            <div style="margin-top:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Guardar configuración</button>
                <button type="button" class="btn btn-secondary" onclick="testIntegrationProvider(this, '<?php echo htmlspecialchars($selectedProvider); ?>')">Probar conexión</button>
                <span id="integrationTestResult" style="font-size:13px;"></span>
            </div>
        </form>
        <?php else: ?>
            <p class="dashboard-empty">No tiene permiso para editar integraciones.</p>
        <?php endif; ?>
    </div>
</div>
