<?php
$layout = 'tenant';
$title = 'Contabilidad - Seri ERP';
$pageTitle = 'Contabilidad';
$currency = $currency ?? ['symbol' => '$', 'decimals' => 2, 'decimal' => ',', 'thousands' => '.'];
$tab = $tab ?? 'dashboard';
$dateFrom = $dateFrom ?? date('Y-m-01');
$dateTo = $dateTo ?? date('Y-m-d');
$accounts = $accounts ?? [];
$entries = $entries ?? [];
$trialBalance = $trialBalance ?? [];
$statements = $statements ?? [];
$ledger = $ledger ?? [];
$periods = $periods ?? [];
$selectedAccountId = $selectedAccountId ?? 0;
$integrationStatuses = $integrationStatuses ?? [];
$activeProvider = $activeProvider ?? null;
$selectedProvider = (string)($_GET['provider'] ?? ($activeProvider ?: 'alegra'));
if (!isset($integrationStatuses[$selectedProvider])) {
    $selectedProvider = array_key_first($integrationStatuses) ?: 'alegra';
}
$canCreate = \SoftNova\Core\TenantMiddleware::canDo('create', 'contabilidad');
$canEdit = \SoftNova\Core\TenantMiddleware::canDo('edit', 'contabilidad');
$canExport = \SoftNova\Core\TenantMiddleware::canDo('export', 'contabilidad');

$purchaseSummary = $purchaseSummary ?? ['cnt' => 0, 'total' => 0];
$expenseByType = $expenseByType ?? [];
$traceMovements = $traceMovements ?? [];
$accountAudit = $accountAudit ?? ['settings' => [], 'notes' => [], 'fixed' => [], 'missing' => []];

function acctFmt(float $amount, array $currency): string
{
    return ($currency['symbol'] ?? '$') . ' ' . number_format(
        $amount,
        (int)($currency['decimals'] ?? 2),
        $currency['decimal'] ?? ',',
        $currency['thousands'] ?? '.'
    );
}

$types = [
    'asset' => 'Activo',
    'liability' => 'Pasivo',
    'equity' => 'Patrimonio',
    'revenue' => 'Ingreso',
    'expense' => 'Gasto',
];
$tabs = [
    'dashboard' => 'Resumen',
    'compras' => 'Compras',
    'gastos' => 'Gastos',
    'trace' => 'Trazabilidad',
    'entries' => 'Libro diario',
    'ledger' => 'Libro mayor',
    'trial' => 'Balance de prueba',
    'statements' => 'Estados financieros',
    'accounts' => 'Plan de cuentas',
    'commissions' => 'Comisiones',
    'periods' => 'Periodos',
    'integrations' => 'Integraciones',
];
$commissionCfg = $commissionCfg ?? [];
$commissionList = $commissionList ?? ['rows' => [], 'pending' => 0, 'paid' => 0, 'cancelled' => 0];
$commissionUsers = $commissionUsers ?? [];
$kindFilter = (string)($_GET['kind'] ?? '');
$statusFilterComm = (string)($_GET['status'] ?? '');
$accountOptions = '';
foreach ($accounts as $account) {
    if ($account['status'] !== 'active' || empty($account['accepts_entries'])) {
        continue;
    }
    $accountOptions .= '<option value="' . htmlspecialchars($account['code']) . '">'
        . htmlspecialchars($account['code'] . ' - ' . $account['name']) . '</option>';
}
?>

<?php echo flashMessage(); ?>

<div class="alert alert-info" style="margin-bottom:16px;">
    <strong>Contabilidad nativa - Fase 1.</strong>
    El catálogo y las reglas automáticas son una plantilla operativa. Antes de usar estos libros como información oficial,
    un contador debe validar las cuentas, impuestos, retenciones y políticas aplicables a la empresa.
</div>

<div class="card neumorphic" style="margin-bottom:18px;">
    <div class="card-body" style="padding:12px 16px;">
        <div class="accounting-tabs">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="btn btn-sm <?php echo $tab === $key ? 'btn-primary' : 'btn-secondary'; ?>"
                   href="<?php echo $viewInstance->route('app/contabilidad'); ?>?tab=<?php echo $key; ?>&from=<?php echo urlencode($dateFrom); ?>&to=<?php echo urlencode($dateTo); ?>">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card neumorphic" style="margin-bottom:18px;">
    <div class="card-body" style="padding:14px 18px;">
        <form method="GET" action="<?php echo $viewInstance->route('app/contabilidad'); ?>"
              style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
            <?php if ($tab === 'ledger'): ?>
                <div class="form-group" style="margin:0;min-width:280px;flex:1;">
                    <label>Cuenta</label>
                    <select name="account_id" class="form-control" required>
                        <option value="">Seleccione una cuenta</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo (int)$account['id']; ?>" <?php echo (int)$account['id'] === (int)$selectedAccountId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($account['code'] . ' - ' . $account['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-group" style="margin:0;"><label>Desde</label><input class="form-control" type="date" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>"></div>
            <div class="form-group" style="margin:0;"><label>Hasta</label><input class="form-control" type="date" name="to" value="<?php echo htmlspecialchars($dateTo); ?>"></div>
            <button class="btn btn-primary" type="submit">Aplicar</button>
        </form>
    </div>
</div>

<?php if ($tab === 'dashboard'): ?>
    <?php
    $totals = $statements['totals'] ?? [];
    $profit = (float)($statements['profit'] ?? 0);
    ?>
    <?php if ($canCreate): ?>
        <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
            <form method="POST" action="<?php echo $viewInstance->route('app/contabilidad'); ?>?action=sync" data-ajax="true">
                <?php echo \SoftNova\Core\csrf_field(); ?>
                <button type="submit" class="btn btn-secondary" onclick="return confirm('¿Contabilizar ventas y gastos anteriores que aún no tienen asiento?')">
                    Sincronizar operaciones existentes
                </button>
            </form>
        </div>
    <?php endif; ?>
    <div class="stats-grid">
        <div class="stat-card neumorphic"><h4>Activos</h4><div class="stat-value"><?php echo acctFmt((float)($totals['assets'] ?? 0), $currency); ?></div></div>
        <div class="stat-card neumorphic"><h4>Pasivos</h4><div class="stat-value"><?php echo acctFmt((float)($totals['liabilities'] ?? 0), $currency); ?></div></div>
        <div class="stat-card neumorphic"><h4>Ingresos</h4><div class="stat-value" style="color:#10B981;"><?php echo acctFmt((float)($totals['revenue'] ?? 0), $currency); ?></div></div>
        <div class="stat-card neumorphic"><h4>Resultado</h4><div class="stat-value" style="color:<?php echo $profit >= 0 ? '#10B981' : '#DC2626'; ?>;"><?php echo acctFmt($profit, $currency); ?></div></div>
    </div>
    <div class="card neumorphic">
        <div class="card-header"><h3>Últimos comprobantes</h3></div>
        <div class="card-body">
            <?php $dashboardEntries = array_slice($entries, 0, 8); ?>
            <?php if (!$dashboardEntries): ?>
                <p class="dashboard-empty">No hay movimientos contables en el periodo.</p>
            <?php else: ?>
                <div class="table-container"><table>
                    <thead><tr><th>Comprobante</th><th>Fecha</th><th>Concepto</th><th>Debe</th><th>Haber</th></tr></thead>
                    <tbody><?php foreach ($dashboardEntries as $entry): ?><tr>
                        <td><button type="button" class="btn btn-sm btn-secondary" onclick="showAccountingEntry(<?php echo (int)$entry['id']; ?>)"><?php echo htmlspecialchars($entry['entry_number']); ?></button></td>
                        <td><?php echo date('d/m/Y', strtotime($entry['entry_date'])); ?></td>
                        <td><?php echo htmlspecialchars($entry['description']); ?></td>
                        <td><?php echo acctFmt((float)$entry['total_debit'], $currency); ?></td>
                        <td><?php echo acctFmt((float)$entry['total_credit'], $currency); ?></td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($tab === 'entries'): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:10px;flex-wrap:wrap;">
        <h3 style="margin:0;">Libro diario</h3>
        <div style="display:flex;gap:8px;">
            <?php if ($canExport): ?>
                <a class="btn btn-secondary" href="<?php echo $viewInstance->route('app/contabilidad'); ?>?action=export&report=journal&from=<?php echo urlencode($dateFrom); ?>&to=<?php echo urlencode($dateTo); ?>">Exportar CSV</a>
            <?php endif; ?>
            <?php if ($canCreate): ?><button type="button" class="btn btn-primary" onclick="openModal('manualEntryModal')">Nuevo comprobante</button><?php endif; ?>
        </div>
    </div>
    <div class="card neumorphic"><div class="card-body">
        <div class="table-container"><table>
            <thead><tr><th>Comprobante</th><th>Fecha</th><th>Concepto</th><th>Origen</th><th>Debe</th><th>Haber</th><th>Estado</th></tr></thead>
            <tbody>
            <?php if (!$entries): ?><tr><td colspan="7" class="dashboard-empty">Sin comprobantes</td></tr><?php endif; ?>
            <?php foreach ($entries as $entry): ?><tr>
                <td><button type="button" class="btn btn-sm btn-secondary" onclick="showAccountingEntry(<?php echo (int)$entry['id']; ?>)"><?php echo htmlspecialchars($entry['entry_number']); ?></button></td>
                <td><?php echo date('d/m/Y', strtotime($entry['entry_date'])); ?></td>
                <td><?php echo htmlspecialchars($entry['description']); ?></td>
                <td><?php echo htmlspecialchars($entry['source_type'] ?: 'Manual'); ?></td>
                <td><?php echo acctFmt((float)$entry['total_debit'], $currency); ?></td>
                <td><?php echo acctFmt((float)$entry['total_credit'], $currency); ?></td>
                <td><span class="badge <?php echo $entry['status'] === 'posted' ? 'badge-success' : 'badge-warning'; ?>"><?php echo htmlspecialchars($entry['status']); ?></span></td>
            </tr><?php endforeach; ?>
            </tbody>
        </table></div>
    </div></div>

<?php elseif ($tab === 'ledger'): ?>
    <div class="card neumorphic"><div class="card-header"><h3>Libro mayor</h3></div><div class="card-body">
        <?php if (!$selectedAccountId): ?>
            <p class="dashboard-empty">Seleccione una cuenta para consultar sus movimientos.</p>
        <?php else: ?>
            <?php $running = 0.0; ?>
            <div class="table-container"><table>
                <thead><tr><th>Fecha</th><th>Comprobante</th><th>Concepto</th><th>Tercero</th><th>Debe</th><th>Haber</th><th>Saldo</th></tr></thead>
                <tbody>
                <?php if (!$ledger): ?><tr><td colspan="7" class="dashboard-empty">Sin movimientos en el periodo</td></tr><?php endif; ?>
                <?php foreach ($ledger as $line): $running += (float)$line['debit'] - (float)$line['credit']; ?><tr>
                    <td><?php echo date('d/m/Y', strtotime($line['entry_date'])); ?></td>
                    <td><button type="button" class="btn btn-sm btn-secondary" onclick="showAccountingEntry(<?php echo (int)$line['entry_id']; ?>)"><?php echo htmlspecialchars($line['entry_number']); ?></button></td>
                    <td><?php echo htmlspecialchars($line['description'] ?: $line['entry_description']); ?></td>
                    <td><?php echo htmlspecialchars($line['third_party_name'] ?: '-'); ?></td>
                    <td><?php echo acctFmt((float)$line['debit'], $currency); ?></td>
                    <td><?php echo acctFmt((float)$line['credit'], $currency); ?></td>
                    <td><?php echo acctFmt($running, $currency); ?></td>
                </tr><?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div></div>

<?php elseif ($tab === 'trial'): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <h3 style="margin:0;">Balance de prueba</h3>
        <?php if ($canExport): ?><a class="btn btn-secondary" href="<?php echo $viewInstance->route('app/contabilidad'); ?>?action=export&report=trial-balance&from=<?php echo urlencode($dateFrom); ?>&to=<?php echo urlencode($dateTo); ?>">Exportar CSV</a><?php endif; ?>
    </div>
    <div class="card neumorphic"><div class="card-body"><div class="table-container"><table>
        <thead><tr><th>Código</th><th>Cuenta</th><th>Saldo inicial</th><th>Debe</th><th>Haber</th><th>Saldo final</th></tr></thead>
        <tbody>
        <?php $sumDebit = $sumCredit = 0.0; ?>
        <?php if (!$trialBalance): ?><tr><td colspan="6" class="dashboard-empty">Sin movimientos</td></tr><?php endif; ?>
        <?php foreach ($trialBalance as $row): $sumDebit += (float)$row['debit']; $sumCredit += (float)$row['credit']; ?><tr>
            <td><?php echo htmlspecialchars($row['code']); ?></td><td><?php echo htmlspecialchars($row['name']); ?></td>
            <td><?php echo acctFmt((float)$row['opening_balance'], $currency); ?></td>
            <td><?php echo acctFmt((float)$row['debit'], $currency); ?></td>
            <td><?php echo acctFmt((float)$row['credit'], $currency); ?></td>
            <td><?php echo acctFmt((float)$row['closing_balance'], $currency); ?></td>
        </tr><?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="3">Totales del periodo</th><th><?php echo acctFmt($sumDebit, $currency); ?></th><th><?php echo acctFmt($sumCredit, $currency); ?></th><th></th></tr></tfoot>
    </table></div></div></div>

<?php elseif ($tab === 'statements'): ?>
    <div class="accounting-statements">
        <div class="card neumorphic"><div class="card-header"><h3>Estado de situación financiera</h3></div><div class="card-body">
            <?php foreach (['assets' => 'Activos', 'liabilities' => 'Pasivos', 'equity' => 'Patrimonio'] as $group => $label): ?>
                <h4><?php echo $label; ?></h4>
                <?php foreach (($statements[$group] ?? []) as $row): ?>
                    <div class="accounting-statement-row"><span><?php echo htmlspecialchars($row['code'] . ' ' . $row['name']); ?></span><strong><?php echo acctFmt((float)$row['display_balance'], $currency); ?></strong></div>
                <?php endforeach; ?>
                <div class="accounting-statement-total"><span>Total <?php echo strtolower($label); ?></span><strong><?php echo acctFmt((float)($statements['totals'][$group] ?? 0), $currency); ?></strong></div>
            <?php endforeach; ?>
        </div></div>
        <div class="card neumorphic"><div class="card-header"><h3>Estado de resultados</h3></div><div class="card-body">
            <h4>Ingresos</h4>
            <?php foreach (($statements['revenue'] ?? []) as $row): ?><div class="accounting-statement-row"><span><?php echo htmlspecialchars($row['code'] . ' ' . $row['name']); ?></span><strong><?php echo acctFmt((float)$row['display_balance'], $currency); ?></strong></div><?php endforeach; ?>
            <div class="accounting-statement-total"><span>Total ingresos</span><strong><?php echo acctFmt((float)($statements['totals']['revenue'] ?? 0), $currency); ?></strong></div>
            <h4 style="margin-top:18px;">Gastos y costos</h4>
            <?php foreach (($statements['expenses'] ?? []) as $row): ?><div class="accounting-statement-row"><span><?php echo htmlspecialchars($row['code'] . ' ' . $row['name']); ?></span><strong><?php echo acctFmt((float)$row['display_balance'], $currency); ?></strong></div><?php endforeach; ?>
            <div class="accounting-statement-total"><span>Total gastos</span><strong><?php echo acctFmt((float)($statements['totals']['expenses'] ?? 0), $currency); ?></strong></div>
            <div class="accounting-statement-total" style="margin-top:18px;color:<?php echo ($statements['profit'] ?? 0) >= 0 ? '#10B981' : '#DC2626'; ?>;"><span>Resultado del periodo</span><strong><?php echo acctFmt((float)($statements['profit'] ?? 0), $currency); ?></strong></div>
        </div></div>
    </div>

<?php elseif ($tab === 'accounts'): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <h3 style="margin:0;">Plan de cuentas</h3>
        <?php if ($canEdit): ?><button type="button" class="btn btn-primary" onclick="newAccountingAccount()">Nueva cuenta</button><?php endif; ?>
    </div>
    <div class="card neumorphic" style="margin-bottom:14px;">
        <div class="card-header"><h3>Auditoría contable (CxP y comisiones)</h3></div>
        <div class="card-body">
            <p style="font-size:13px;color:var(--color-text-secondary);margin-bottom:10px;">
                Cuentas críticas: <code>530505</code> gastos financieros/comisiones,
                <code>220505</code> proveedores (CxP en compras a crédito),
                <code>143501</code> inventario.
            </p>
            <ul style="font-size:13px;margin:0 0 12px 18px;">
                <?php foreach (($accountAudit['notes'] ?? []) as $note): ?>
                    <li><?php echo htmlspecialchars($note); ?></li>
                <?php endforeach; ?>
            </ul>
            <p style="font-size:13px;margin:0;">
                Las comisiones de vendedor y pasarela (datáfono, tarjetas, link) se parametrizan y liquidan en
                <a href="<?php echo $viewInstance->route('app/contabilidad'); ?>?tab=commissions">Contabilidad → Comisiones</a>.
            </p>
        </div>
    </div>
    <div class="card neumorphic"><div class="card-body"><div class="table-container"><table>
        <thead><tr><th>Código</th><th>Cuenta</th><th>Tipo</th><th>Naturaleza</th><th>Estado</th><th>Origen</th><th>Acción</th></tr></thead>
        <tbody><?php foreach ($accounts as $account): ?><tr>
            <td><strong><?php echo htmlspecialchars($account['code']); ?></strong></td>
            <td><?php echo htmlspecialchars($account['name']); ?></td>
            <td><?php echo htmlspecialchars($types[$account['account_type']] ?? $account['account_type']); ?></td>
            <td><?php echo $account['nature'] === 'debit' ? 'Débito' : 'Crédito'; ?></td>
            <td><span class="badge <?php echo $account['status'] === 'active' ? 'badge-success' : 'badge-warning'; ?>"><?php echo $account['status'] === 'active' ? 'Activa' : 'Inactiva'; ?></span></td>
            <td><?php echo !empty($account['is_system']) ? 'Sistema' : 'Usuario'; ?></td>
            <td><?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-secondary" onclick='editAccountingAccount(<?php echo json_encode($account, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Editar</button><?php endif; ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div></div></div>

<?php elseif ($tab === 'commissions'): ?>
    <?php
    function fmtComm(float $a, array $c): string {
        return ($c['symbol'] ?? '$') . ' ' . number_format($a, (int)($c['decimals'] ?? 2), $c['decimal'] ?? ',', $c['thousands'] ?? '.');
    }
    ?>
    <div class="stats-grid" style="margin-bottom:16px;">
        <div class="stat-card neumorphic"><h4>Pendientes</h4><div class="stat-value" style="color:#F59E0B;"><?php echo fmtComm((float)$commissionList['pending'], $currency); ?></div></div>
        <div class="stat-card neumorphic"><h4>Pagadas / registradas</h4><div class="stat-value" style="color:#10B981;"><?php echo fmtComm((float)$commissionList['paid'], $currency); ?></div></div>
        <div class="stat-card neumorphic"><h4>Canceladas</h4><div class="stat-value"><?php echo fmtComm((float)$commissionList['cancelled'], $currency); ?></div></div>
    </div>

    <div class="card neumorphic" style="margin-bottom:16px;">
        <div class="card-header"><h3>Parámetros de comisiones</h3></div>
        <div class="card-body">
            <?php if ($canEdit): ?>
            <form method="POST" action="<?php echo $viewInstance->route('app/contabilidad'); ?>?action=save-commission-config" data-ajax="true">
                <?php echo \SoftNova\Core\csrf_field(); ?>
                <h4 style="margin:0 0 10px;">Comisión de vendedor</h4>
                <div class="form-grid">
                    <div class="form-group"><label><input type="checkbox" name="seller_enabled" value="1" <?php echo !empty($commissionCfg['seller_enabled']) ? 'checked' : ''; ?>> Activar comisión de vendedor</label></div>
                    <div class="form-group"><label><input type="checkbox" name="seller_auto_expense" value="1" <?php echo !empty($commissionCfg['seller_auto_expense']) ? 'checked' : ''; ?>> Generar gasto/asiento al instante</label></div>
                    <div class="form-group"><label>% global (si el usuario no tiene tasa propia)</label>
                        <input class="form-control" type="number" step="0.01" min="0" max="100" name="seller_rate" value="<?php echo htmlspecialchars((string)($commissionCfg['seller_rate'] ?? 3)); ?>">
                    </div>
                    <div class="form-group"><label>Base de cálculo</label>
                        <select class="form-control" name="seller_base">
                            <option value="total" <?php echo ($commissionCfg['seller_base'] ?? '') === 'total' ? 'selected' : ''; ?>>Total de la venta / abono</option>
                            <option value="subtotal" <?php echo ($commissionCfg['seller_base'] ?? '') === 'subtotal' ? 'selected' : ''; ?>>Subtotal (sin IVA)</option>
                            <option value="profit" <?php echo ($commissionCfg['seller_base'] ?? '') === 'profit' ? 'selected' : ''; ?>>Utilidad (total − costo)</option>
                        </select>
                    </div>
                    <div class="form-group"><label>¿Cuándo se genera?</label>
                        <select class="form-control" name="seller_trigger">
                            <option value="on_payment" <?php echo ($commissionCfg['seller_trigger'] ?? '') === 'on_payment' ? 'selected' : ''; ?>>Al cobrar (recomendado)</option>
                            <option value="on_sale" <?php echo ($commissionCfg['seller_trigger'] ?? '') === 'on_sale' ? 'selected' : ''; ?>>Al crear la venta (sobre el total)</option>
                        </select>
                    </div>
                </div>
                <h4 style="margin:18px 0 10px;">Comisión de pasarela (datáfono / tarjetas / link)</h4>
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;"><label><input type="checkbox" name="gateway_auto" value="1" <?php echo !empty($commissionCfg['gateway_auto']) ? 'checked' : ''; ?>> Generar automáticamente al registrar el pago</label></div>
                    <div class="form-group"><label>% Datáfono</label><input class="form-control" type="number" step="0.01" name="dataphone_rate" value="<?php echo htmlspecialchars((string)($commissionCfg['dataphone_rate'] ?? 2.5)); ?>"></div>
                    <div class="form-group"><label>% Link de pago</label><input class="form-control" type="number" step="0.01" name="payment_link_rate" value="<?php echo htmlspecialchars((string)($commissionCfg['payment_link_rate'] ?? 2.5)); ?>"></div>
                    <div class="form-group"><label>% Débito</label><input class="form-control" type="number" step="0.01" name="debit_card_rate" value="<?php echo htmlspecialchars((string)($commissionCfg['debit_card_rate'] ?? 1.5)); ?>"></div>
                    <div class="form-group"><label>% Crédito</label><input class="form-control" type="number" step="0.01" name="credit_card_rate" value="<?php echo htmlspecialchars((string)($commissionCfg['credit_card_rate'] ?? 2.8)); ?>"></div>
                    <div class="form-group"><label>% Tarjeta genérica</label><input class="form-control" type="number" step="0.01" name="card_rate" value="<?php echo htmlspecialchars((string)($commissionCfg['card_rate'] ?? 2.8)); ?>"></div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:12px;">Guardar parámetros</button>
            </form>
            <?php else: ?>
                <p class="dashboard-empty">Sin permiso de edición.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card neumorphic" style="margin-bottom:16px;">
        <div class="card-header"><h3>Tasas por vendedor (usuario)</h3></div>
        <div class="card-body"><div class="table-container"><table>
            <thead><tr><th>Usuario</th><th>Rol</th><th>% comisión</th><th>Activa</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($commissionUsers as $u): ?>
                <tr>
                    <td><?php echo htmlspecialchars($u['name']); ?><br><small><?php echo htmlspecialchars($u['email'] ?? ''); ?></small></td>
                    <td><?php echo htmlspecialchars($u['role'] ?? ''); ?></td>
                    <?php if ($canEdit): ?>
                    <td colspan="3">
                        <form method="POST" action="<?php echo $viewInstance->route('app/contabilidad'); ?>?action=save-user-commission" data-ajax="true" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <?php echo \SoftNova\Core\csrf_field(); ?>
                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                            <input class="form-control" style="width:100px;" type="number" step="0.01" min="0" max="100" name="rate" value="<?php echo htmlspecialchars((string)($u['rate'] ?? ($commissionCfg['seller_rate'] ?? 0))); ?>">
                            <label><input type="checkbox" name="enabled" value="1" <?php echo !empty($u['rate_enabled']) ? 'checked' : ''; ?>> Usar tasa</label>
                            <button class="btn btn-sm btn-secondary" type="submit">Guardar</button>
                        </form>
                    </td>
                    <?php else: ?>
                    <td><?php echo $u['rate'] !== null ? (float)$u['rate'] . '%' : 'Global'; ?></td>
                    <td><?php echo !empty($u['rate_enabled']) ? 'Sí' : '—'; ?></td>
                    <td></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p style="font-size:12px;color:var(--color-text-secondary);margin-top:8px;">Sin «Usar tasa» → aplica el % global. Con tasa 0% activa → ese usuario no genera comisión.</p>
        </div>
    </div>

    <div class="card neumorphic">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h3 style="margin:0;">Movimientos de comisiones</h3>
            <form method="GET" action="<?php echo $viewInstance->route('app/contabilidad'); ?>" style="display:flex;gap:6px;flex-wrap:wrap;">
                <input type="hidden" name="tab" value="commissions">
                <input type="hidden" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                <input type="hidden" name="to" value="<?php echo htmlspecialchars($dateTo); ?>">
                <select name="kind" class="form-control" style="width:auto;">
                    <option value="">Todos los tipos</option>
                    <option value="seller" <?php echo $kindFilter === 'seller' ? 'selected' : ''; ?>>Vendedor</option>
                    <option value="gateway" <?php echo $kindFilter === 'gateway' ? 'selected' : ''; ?>>Pasarela</option>
                </select>
                <select name="status" class="form-control" style="width:auto;">
                    <option value="">Todos los estados</option>
                    <option value="pending" <?php echo $statusFilterComm === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="paid" <?php echo $statusFilterComm === 'paid' ? 'selected' : ''; ?>>Pagada</option>
                    <option value="cancelled" <?php echo $statusFilterComm === 'cancelled' ? 'selected' : ''; ?>>Cancelada</option>
                </select>
                <button class="btn btn-secondary" type="submit">Filtrar</button>
            </form>
        </div>
        <div class="card-body">
            <?php if ($canEdit): ?>
            <form method="POST" action="<?php echo $viewInstance->route('app/contabilidad'); ?>?action=settle-commissions" data-ajax="true">
                <?php echo \SoftNova\Core\csrf_field(); ?>
            <?php endif; ?>
            <div class="table-container"><table>
                <thead><tr>
                    <?php if ($canEdit): ?><th></th><?php endif; ?>
                    <th>Fecha</th><th>Tipo</th><th>Venta</th><th>Vendedor / medio</th><th>Base</th><th>%</th><th>Monto</th><th>Estado</th>
                </tr></thead>
                <tbody>
                <?php if (empty($commissionList['rows'])): ?>
                    <tr><td colspan="9" class="dashboard-empty">Sin comisiones en el periodo. Active los parámetros y registre ventas.</td></tr>
                <?php endif; ?>
                <?php foreach ($commissionList['rows'] as $c): ?>
                    <tr>
                        <?php if ($canEdit): ?>
                        <td><?php if (($c['status'] ?? '') === 'pending'): ?>
                            <input type="checkbox" name="ids[]" value="<?php echo (int)$c['id']; ?>">
                        <?php endif; ?></td>
                        <?php endif; ?>
                        <td><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></td>
                        <td><?php echo ($c['commission_kind'] ?? '') === 'gateway' ? 'Pasarela' : 'Vendedor'; ?></td>
                        <td><?php echo htmlspecialchars($c['invoice_number'] ?? ('#' . $c['sale_id'])); ?></td>
                        <td><?php echo htmlspecialchars($c['seller_name'] ?? ($c['payment_method'] ?? '—')); ?></td>
                        <td><?php echo fmtComm((float)$c['base_amount'], $currency); ?></td>
                        <td><?php echo (float)$c['rate']; ?>%</td>
                        <td><strong><?php echo fmtComm((float)$c['amount'], $currency); ?></strong></td>
                        <td><span class="badge"><?php echo htmlspecialchars($c['status']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php if ($canEdit): ?>
                <button type="submit" class="btn btn-primary" style="margin-top:12px;" onclick="return confirm('¿Liquidar seleccionadas (crear gasto + asiento)?')">Liquidar seleccionadas</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($tab === 'periods'): ?>
    <div class="card neumorphic"><div class="card-header"><h3>Cierre de periodos</h3></div><div class="card-body">
        <?php if ($canEdit): ?>
            <form method="POST" action="<?php echo $viewInstance->route('app/contabilidad'); ?>?action=period" data-ajax="true" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:18px;">
                <?php echo \SoftNova\Core\csrf_field(); ?>
                <div class="form-group" style="margin:0;"><label>Año</label><input class="form-control" type="number" name="year" min="2000" max="2100" value="<?php echo date('Y'); ?>" required></div>
                <div class="form-group" style="margin:0;"><label>Mes</label><select class="form-control" name="month"><?php for ($m=1;$m<=12;$m++): ?><option value="<?php echo $m; ?>" <?php echo $m === (int)date('n') ? 'selected' : ''; ?>><?php echo $m; ?></option><?php endfor; ?></select></div>
                <div class="form-group" style="margin:0;"><label>Estado</label><select class="form-control" name="status"><option value="closed">Cerrar</option><option value="open">Reabrir</option></select></div>
                <div class="form-group" style="margin:0;min-width:220px;"><label>Notas</label><input class="form-control" name="notes"></div>
                <button class="btn btn-primary" type="submit">Actualizar periodo</button>
            </form>
        <?php endif; ?>
        <div class="table-container"><table><thead><tr><th>Periodo</th><th>Estado</th><th>Cerrado por</th><th>Fecha</th><th>Notas</th></tr></thead><tbody>
            <?php if (!$periods): ?><tr><td colspan="5" class="dashboard-empty">Todos los periodos están abiertos</td></tr><?php endif; ?>
            <?php foreach ($periods as $period): ?><tr>
                <td><?php echo str_pad((string)$period['month'], 2, '0', STR_PAD_LEFT) . '/' . $period['year']; ?></td>
                <td><span class="badge <?php echo $period['status'] === 'closed' ? 'badge-danger' : 'badge-success'; ?>"><?php echo $period['status'] === 'closed' ? 'Cerrado' : 'Abierto'; ?></span></td>
                <td><?php echo htmlspecialchars($period['closed_by_name'] ?: '-'); ?></td>
                <td><?php echo $period['closed_at'] ? date('d/m/Y H:i', strtotime($period['closed_at'])) : '-'; ?></td>
                <td><?php echo htmlspecialchars($period['notes'] ?: '-'); ?></td>
            </tr><?php endforeach; ?>
        </tbody></table></div>
    </div></div>

<?php elseif ($tab === 'compras'): ?>
    <div class="card neumorphic" style="margin-bottom:16px;">
        <div class="card-header"><h3>Compras del periodo</h3></div>
        <div class="card-body">
            <p><strong><?php echo (int)$purchaseSummary['cnt']; ?></strong> compras · Total
                <strong><?php echo acctFmt((float)$purchaseSummary['total'], $currency); ?></strong></p>
            <p style="color:var(--color-text-secondary);">Las compras cargan inventario y generan asiento (Inventario / Proveedores o caja-banco).</p>
            <a class="btn btn-primary" href="<?php echo $viewInstance->route('app/compras'); ?>">Ir al módulo Compras</a>
        </div>
    </div>

<?php elseif ($tab === 'gastos'): ?>
    <div class="card neumorphic">
        <div class="card-header"><h3>Gastos tipificados del periodo</h3></div>
        <div class="card-body">
            <?php if (empty($expenseByType)): ?>
                <p style="color:var(--color-text-secondary);">Sin gastos en el periodo.</p>
            <?php else: ?>
                <div class="table-container"><table>
                    <thead><tr><th>Tipo</th><th>Cantidad</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($expenseByType as $row): ?>
                        <?php
                        $cat = (string)$row['category'];
                        $grp = \SoftNova\Core\expense_category_group($cat);
                        ?>
                        <tr>
                            <td>
                                <span class="badge badge-info" style="margin-right:6px;"><?php echo htmlspecialchars(\SoftNova\Core\expense_group_label($grp)); ?></span>
                                <?php echo htmlspecialchars(\SoftNova\Core\expense_category_label($cat)); ?>
                            </td>
                            <td><?php echo (int)$row['cnt']; ?></td>
                            <td><?php echo acctFmt((float)$row['total'], $currency); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
            <a class="btn btn-secondary" style="margin-top:12px;" href="<?php echo $viewInstance->route('app/gastos'); ?>">Gestionar gastos</a>
        </div>
    </div>

<?php elseif ($tab === 'trace'): ?>
    <div class="card neumorphic">
        <div class="card-header"><h3>Trazabilidad de inventario (periodo)</h3></div>
        <div class="card-body">
            <?php if (empty($traceMovements)): ?>
                <p style="color:var(--color-text-secondary);">Sin movimientos en el periodo.</p>
            <?php else: ?>
                <div class="table-container"><table>
                    <thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cant.</th><th>Origen</th></tr></thead>
                    <tbody>
                    <?php foreach ($traceMovements as $m): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($m['movement_date'] ?? $m['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($m['product_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($m['type']); ?></td>
                            <td><?php echo (int)$m['quantity']; ?></td>
                            <td><?php echo htmlspecialchars(($m['reference_type'] ?? '') . (!empty($m['reference_id']) ? ' #'.$m['reference_id'] : '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
            <a class="btn btn-secondary" style="margin-top:12px;" href="<?php echo $viewInstance->route('app/inventario'); ?>?action=traceability">Trazabilidad completa</a>
        </div>
    </div>

<?php elseif ($tab === 'integrations'): ?>
    <?php require APP_PATH . '/Views/partials/contabilidad_fe_integrations.php'; ?>
<?php endif; ?>

<?php if ($canCreate): ?>
<div id="manualEntryModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:950px;">
        <div class="modal-header"><h3>Nuevo comprobante manual</h3><button type="button" class="modal-close" onclick="closeModal('manualEntryModal')">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/contabilidad'); ?>?action=manual-entry" data-ajax="true" onsubmit="return validateAccountingEntry()">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group"><label>Fecha *</label><input class="form-control" type="date" name="entry_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="form-group"><label>Concepto *</label><input class="form-control" name="description" maxlength="255" required></div>
                </div>
                <div class="table-container"><table id="manualEntryLines">
                    <thead><tr><th>Cuenta</th><th>Detalle</th><th>Debe</th><th>Haber</th><th></th></tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><th colspan="2">Totales</th><th id="manualDebitTotal">0</th><th id="manualCreditTotal">0</th><th></th></tr></tfoot>
                </table></div>
                <button type="button" class="btn btn-secondary" onclick="addAccountingLine()">Agregar línea</button>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('manualEntryModal')">Cancelar</button><button type="submit" class="btn btn-primary">Contabilizar</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<div id="accountModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header"><h3 id="accountModalTitle">Cuenta contable</h3><button type="button" class="modal-close" onclick="closeModal('accountModal')">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/contabilidad'); ?>?action=save-account" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="id" id="accountId">
            <div class="modal-body"><div class="form-grid">
                <div class="form-group"><label>Código *</label><input class="form-control" name="code" id="accountCode" maxlength="30" required></div>
                <div class="form-group"><label>Nombre *</label><input class="form-control" name="name" id="accountName" maxlength="180" required></div>
                <div class="form-group"><label>Tipo *</label><select class="form-control" name="account_type" id="accountType" required><?php foreach ($types as $value => $label): ?><option value="<?php echo $value; ?>"><?php echo $label; ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Naturaleza *</label><select class="form-control" name="nature" id="accountNature"><option value="debit">Débito</option><option value="credit">Crédito</option></select></div>
                <div class="form-group"><label>Estado</label><select class="form-control" name="status" id="accountStatus"><option value="active">Activa</option><option value="inactive">Inactiva</option></select></div>
                <div class="form-group"><label><input type="checkbox" name="accepts_entries" id="accountAccepts" value="1" checked> Recibe movimientos</label></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('accountModal')">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<div id="entryDetailModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:900px;">
        <div class="modal-header"><h3 id="entryDetailTitle">Comprobante</h3><button type="button" class="modal-close" onclick="closeModal('entryDetailModal')">&times;</button></div>
        <div class="modal-body" id="entryDetailBody"><p class="dashboard-empty">Cargando...</p></div>
    </div>
</div>

<style>
.accounting-tabs{display:flex;gap:7px;flex-wrap:wrap}
.accounting-statements{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.accounting-statement-row,.accounting-statement-total{display:flex;justify-content:space-between;gap:15px;padding:7px 0;border-bottom:1px solid var(--color-border)}
.accounting-statement-total{font-weight:700;border-top:2px solid var(--color-border);border-bottom:0}
@media(max-width:900px){.accounting-statements{grid-template-columns:1fr}}
</style>

<script>
var accountingAccountOptions = <?php echo json_encode($accountOptions); ?>;
var accountingCurrency = <?php echo json_encode($currency); ?>;

function accountingMoney(value) {
    return (accountingCurrency.symbol || '$') + ' ' + Number(value || 0).toLocaleString('es-CO', {
        minimumFractionDigits: accountingCurrency.decimals || 2,
        maximumFractionDigits: accountingCurrency.decimals || 2
    });
}

function addAccountingLine() {
    var tbody = document.querySelector('#manualEntryLines tbody');
    if (!tbody) return;
    var row = document.createElement('tr');
    row.innerHTML = '<td><select name="account_code[]" class="form-control" required><option value="">Seleccione</option>' + accountingAccountOptions + '</select></td>' +
        '<td><input name="line_description[]" class="form-control" maxlength="255"></td>' +
        '<td><input name="debit[]" type="number" min="0" step="0.01" class="form-control accounting-debit" value="0" oninput="updateAccountingTotals()"></td>' +
        '<td><input name="credit[]" type="number" min="0" step="0.01" class="form-control accounting-credit" value="0" oninput="updateAccountingTotals()"></td>' +
        '<td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest(\'tr\').remove();updateAccountingTotals()">X</button></td>';
    tbody.appendChild(row);
}

function updateAccountingTotals() {
    var debit = 0, credit = 0;
    document.querySelectorAll('.accounting-debit').forEach(function(el){ debit += Number(el.value || 0); });
    document.querySelectorAll('.accounting-credit').forEach(function(el){ credit += Number(el.value || 0); });
    document.getElementById('manualDebitTotal').textContent = accountingMoney(debit);
    document.getElementById('manualCreditTotal').textContent = accountingMoney(credit);
}

function validateAccountingEntry() {
    var debit = 0, credit = 0;
    document.querySelectorAll('.accounting-debit').forEach(function(el){ debit += Number(el.value || 0); });
    document.querySelectorAll('.accounting-credit').forEach(function(el){ credit += Number(el.value || 0); });
    if (debit <= 0 || Math.abs(debit - credit) > 0.009) {
        showAlert('El comprobante debe tener Debe = Haber y valores mayores a cero', 'error');
        return false;
    }
    return true;
}

function newAccountingAccount() {
    document.getElementById('accountModalTitle').textContent = 'Nueva cuenta';
    document.getElementById('accountId').value = '';
    document.getElementById('accountCode').value = '';
    document.getElementById('accountName').value = '';
    document.getElementById('accountType').value = 'asset';
    document.getElementById('accountNature').value = 'debit';
    document.getElementById('accountStatus').value = 'active';
    document.getElementById('accountAccepts').checked = true;
    openModal('accountModal');
}

function editAccountingAccount(account) {
    document.getElementById('accountModalTitle').textContent = 'Editar cuenta';
    document.getElementById('accountId').value = account.id;
    document.getElementById('accountCode').value = account.code;
    document.getElementById('accountName').value = account.name;
    document.getElementById('accountType').value = account.account_type;
    document.getElementById('accountNature').value = account.nature;
    document.getElementById('accountStatus').value = account.status;
    document.getElementById('accountAccepts').checked = Number(account.accepts_entries) === 1;
    openModal('accountModal');
}

function showAccountingEntry(id) {
    var url = <?php echo json_encode($viewInstance->route('app/contabilidad')); ?> + '?action=entry&id=' + encodeURIComponent(id);
    document.getElementById('entryDetailBody').innerHTML = '<p class="dashboard-empty">Cargando...</p>';
    openModal('entryDetailModal');
    fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(response){ return response.json(); })
        .then(function(data){
            if (!data.success) throw new Error(data.message || 'No se pudo cargar');
            var entry = data.entry;
            document.getElementById('entryDetailTitle').textContent = entry.entry_number + ' · ' + entry.description;
            var html = '<p><strong>Fecha:</strong> ' + entry.entry_date + ' &nbsp; <strong>Estado:</strong> ' + entry.status + '</p>';
            html += '<div class="table-container"><table><thead><tr><th>Cuenta</th><th>Detalle</th><th>Tercero</th><th>Debe</th><th>Haber</th></tr></thead><tbody>';
            entry.lines.forEach(function(line){
                html += '<tr><td>' + escapeAccounting(line.account_code + ' - ' + line.account_name) + '</td><td>' + escapeAccounting(line.description || '') + '</td><td>' + escapeAccounting(line.third_party_name || '-') + '</td><td>' + accountingMoney(line.debit) + '</td><td>' + accountingMoney(line.credit) + '</td></tr>';
            });
            html += '</tbody></table></div>';
            document.getElementById('entryDetailBody').innerHTML = html;
        })
        .catch(function(error){
            document.getElementById('entryDetailBody').innerHTML = '<p class="dashboard-empty" style="color:#DC2626;">' + escapeAccounting(error.message) + '</p>';
        });
}

function escapeAccounting(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}

function testIntegrationProvider(btn, provider) {
    var result = document.getElementById('integrationTestResult');
    btn.disabled = true;
    result.textContent = 'Probando...';
    result.style.color = 'var(--color-text-secondary)';
    var url = <?php echo json_encode($viewInstance->route('app/contabilidad')); ?>
        + '?action=' + encodeURIComponent(<?php echo json_encode($testAction ?? 'integration-test'); ?>)
        + '&provider=' + encodeURIComponent(provider);
    fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(response){ return response.json(); })
        .then(function(data){
            result.textContent = data.message || (data.success ? 'Conexión OK' : 'Error');
            result.style.color = data.success ? '#10B981' : '#DC2626';
        })
        .catch(function(){
            result.textContent = 'No se pudo probar la conexión';
            result.style.color = '#DC2626';
        })
        .finally(function(){ btn.disabled = false; });
}

document.addEventListener('DOMContentLoaded', function(){
    var tbody = document.querySelector('#manualEntryLines tbody');
    if (tbody && !tbody.children.length) {
        addAccountingLine();
        addAccountingLine();
    }
});
</script>
