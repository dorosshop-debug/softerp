<?php
$layout = 'tenant';
$title = 'Gastos - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Gastos';
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$expenses = $expenses ?? [];
$suppliers = $suppliers ?? [];
$monthTotal = $monthTotal ?? 0;
$monthFixed = $monthFixed ?? 0;
$monthFinancial = $monthFinancial ?? 0;
$monthOperating = $monthOperating ?? 0;
$groupFilter = $groupFilter ?? '';
$currency = $currency ?? ['symbol' => '$', 'decimals' => 2, 'decimal' => ',', 'thousands' => '.'];
$pagination = $pagination ?? null;
$suggestAmount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
$suggestCategory = (string)($_GET['category'] ?? 'dataphone_commission');
$suggestDescription = (string)($_GET['description'] ?? '');
$openSuggest = !empty($_GET['suggest']) && $suggestAmount > 0;
$gastosBase = $viewInstance->route('app/gastos');

function fmtG(float $a, array $c): string
{
    return $c['symbol'] . ' ' . number_format($a, $c['decimals'], $c['decimal'] ?? ',', $c['thousands'] ?? '.');
}
?>
<?php echo flashMessage(); ?>

<div class="stats-grid">
    <div class="stat-card neumorphic"><h4>Total mes</h4><div class="stat-value" style="color:#DC2626;"><?php echo fmtG((float)$monthTotal, $currency); ?></div></div>
    <div class="stat-card neumorphic"><h4>Gastos fijos</h4><div class="stat-value"><?php echo fmtG((float)$monthFixed, $currency); ?></div><small>Arriendo, servicios, nómina…</small></div>
    <div class="stat-card neumorphic"><h4>Gastos financieros</h4><div class="stat-value" style="color:#B45309;"><?php echo fmtG((float)$monthFinancial, $currency); ?></div><small>Comisiones, retenciones, bancos…</small></div>
    <div class="stat-card neumorphic"><h4>Otros / operativos</h4><div class="stat-value"><?php echo fmtG((float)$monthOperating, $currency); ?></div></div>
</div>

<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
    <button onclick="document.getElementById('expenseModal').style.display='flex'" class="btn btn-primary neumorphic-btn" title="Nuevo gasto">Nuevo Gasto</button>
    <a href="<?php echo $gastosBase; ?>?action=export" class="btn btn-secondary" title="Exportar CSV">Exportar CSV</a>
    <a class="btn <?php echo $groupFilter === '' ? 'btn-primary' : 'btn-secondary'; ?>" href="<?php echo $gastosBase; ?>">Todos</a>
    <a class="btn <?php echo $groupFilter === 'fixed' ? 'btn-primary' : 'btn-secondary'; ?>" href="<?php echo $gastosBase; ?>?group=fixed">Solo fijos</a>
    <a class="btn <?php echo $groupFilter === 'financial' ? 'btn-primary' : 'btn-secondary'; ?>" href="<?php echo $gastosBase; ?>?group=financial">Solo financieros</a>
    <a class="btn <?php echo $groupFilter === 'operating' ? 'btn-primary' : 'btn-secondary'; ?>" href="<?php echo $gastosBase; ?>?group=operating">Solo operativos</a>
</div>

<div class="card neumorphic">
    <div class="card-header"><h3>Listado de Gastos</h3></div>
    <div class="card-body">
        <?php if (empty($expenses)): ?>
            <p style="text-align:center;color:var(--color-text-secondary);padding:20px;">No hay gastos registrados</p>
        <?php else: ?>
            <div class="table-container"><table>
                <thead><tr><th>Fecha</th><th>Descripcion</th><th>Grupo</th><th>Tipo</th><th>Proveedor</th><th>Monto</th><th>Metodo</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($expenses as $exp): ?>
                    <?php
                    $cat = (string)($exp['category'] ?? 'general');
                    $grp = \SoftNova\Core\expense_category_group($cat);
                    $grpBadge = match ($grp) {
                        'financial' => 'badge-warning',
                        'fixed' => 'badge-info',
                        default => 'badge-secondary',
                    };
                    ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($exp['expense_date'])); ?></td>
                        <td><?php echo htmlspecialchars($exp['description']); ?></td>
                        <td><span class="badge <?php echo $grpBadge; ?>"><?php echo htmlspecialchars(\SoftNova\Core\expense_group_label($grp)); ?></span></td>
                        <td><?php echo htmlspecialchars(\SoftNova\Core\expense_category_label($cat)); ?></td>
                        <td><?php echo htmlspecialchars($exp['supplier_name'] ?? '-'); ?></td>
                        <td style="font-weight:600;color:#DC2626;"><?php echo fmtG((float)$exp['amount'], $currency); ?></td>
                        <td><?php echo htmlspecialchars(\SoftNova\Core\payment_method_label((string)($exp['payment_method'] ?? 'cash'))); ?></td>
                        <td class="table-actions">
                            <form method="POST" action="<?php echo $gastosBase; ?>?action=delete" style="display:inline;" data-ajax="true">
                                <?php echo \SoftNova\Core\csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$exp['id']; ?>">
                                <button type="submit" onclick="return confirm('Eliminar gasto?')" class="btn btn-sm btn-danger" title="Eliminar">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php
            $paginationBaseUrl = $gastosBase;
            $paginationQuery = $groupFilter !== '' ? ['group' => $groupFilter] : [];
            $viewInstance->partial('pagination', compact('pagination', 'paginationBaseUrl', 'paginationQuery'));
            ?>
        <?php endif; ?>
    </div>
</div>

<div id="expenseModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:560px;">
        <div class="modal-header">
            <h3>Nuevo Gasto</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('expenseModal').style.display='none'" title="Cerrar">&times;</button>
        </div>
        <form method="POST" action="<?php echo $gastosBase; ?>?action=create" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>Descripcion * <span class="field-tip" data-tip="Detalle claro del gasto (ej. arriendo, comisión datáfono, retefuente).">?</span></label>
                    <input type="text" name="description" class="form-control" required value="<?php echo htmlspecialchars($suggestDescription); ?>">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label>Monto *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="<?php echo $suggestAmount > 0 ? htmlspecialchars((string)$suggestAmount) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha</label>
                        <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label>Tipo de gasto *</label>
                        <select name="category" class="form-control" required>
                            <?php echo \SoftNova\Core\expense_category_options($suggestCategory !== '' ? $suggestCategory : 'fixed'); ?>
                        </select>
                        <small style="color:var(--color-text-secondary);">Fijos → 510505 · Financieros (comisiones/retenciones) → 530505</small>
                    </div>
                    <div class="form-group">
                        <label>Metodo de pago</label>
                        <select name="payment_method" class="form-control">
                            <?php echo \SoftNova\Core\payment_method_options($openSuggest ? 'transfer' : 'cash'); ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Proveedor</label>
                    <select name="supplier_id" class="form-control">
                        <option value="">Sin proveedor</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Comprobante</label>
                    <input type="text" name="receipt_number" class="form-control">
                </div>
                <div class="form-group">
                    <label>Notas</label>
                    <textarea name="notes" class="form-control" rows="2" title="Notas"></textarea>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="affect_cash" value="1" <?php echo $openSuggest ? '' : 'checked'; ?> title="Registrar en caja">
                        Registrar egreso en caja abierta (solo efectivo)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('expenseModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary" title="Guardar gasto">Guardar</button>
            </div>
        </form>
    </div>
</div>
<?php if ($openSuggest): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('expenseModal');
    if (modal) modal.style.display = 'flex';
    var cash = modal && modal.querySelector('input[name="affect_cash"]');
    if (cash) cash.checked = false;
});
</script>
<?php endif; ?>
