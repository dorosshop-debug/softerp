<?php
$layout = 'tenant';
$title = 'Gastos - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Gastos';
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$expenses = $expenses ?? [];
$suppliers = $suppliers ?? [];
$monthTotal = $monthTotal ?? 0;
$currency = $currency ?? ['symbol' => '$', 'decimals' => 2, 'decimal' => ',', 'thousands' => '.'];
$pagination = $pagination ?? null;

function fmtG(float $a, array $c): string
{
    return $c['symbol'] . ' ' . number_format($a, $c['decimals'], $c['decimal'] ?? ',', $c['thousands'] ?? '.');
}
?>
<?php echo flashMessage(); ?>

<div class="stats-grid">
    <div class="stat-card neumorphic"><h4>Gastos mes</h4><div class="stat-value" style="color:#DC2626;"><?php echo fmtG((float)$monthTotal, $currency); ?></div></div>
    <div class="stat-card neumorphic"><h4>Registros</h4><div class="stat-value"><?php echo (int)($pagination['total'] ?? count($expenses)); ?></div></div>
</div>

<div style="display:flex;gap:15px;margin-bottom:20px;flex-wrap:wrap;">
    <button onclick="document.getElementById('expenseModal').style.display='flex'" class="btn btn-primary neumorphic-btn" title="Nuevo gasto">Nuevo Gasto</button>
    <a href="<?php echo $viewInstance->route('app/gastos'); ?>?action=export" class="btn btn-secondary" title="Exportar CSV">Exportar CSV</a>
</div>

<div class="card neumorphic">
    <div class="card-header"><h3>Listado de Gastos</h3></div>
    <div class="card-body">
        <?php if (empty($expenses)): ?>
            <p style="text-align:center;color:var(--color-text-secondary);padding:20px;">No hay gastos registrados</p>
        <?php else: ?>
            <div class="table-container"><table>
                <thead><tr><th>Fecha</th><th>Descripcion</th><th>Categoria</th><th>Proveedor</th><th>Monto</th><th>Metodo</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($expenses as $exp): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($exp['expense_date'])); ?></td>
                        <td><?php echo htmlspecialchars($exp['description']); ?></td>
                        <td><?php echo htmlspecialchars($exp['category'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($exp['supplier_name'] ?? '-'); ?></td>
                        <td style="font-weight:600;color:#DC2626;"><?php echo fmtG((float)$exp['amount'], $currency); ?></td>
                        <td><?php echo htmlspecialchars($exp['payment_method'] ?? ''); ?></td>
                        <td class="table-actions">
                            <form method="POST" action="<?php echo $viewInstance->route('app/gastos'); ?>?action=delete" style="display:inline;" data-ajax="true">
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
            $paginationBaseUrl = $viewInstance->route('app/gastos');
            $paginationQuery = [];
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
        <form method="POST" action="<?php echo $viewInstance->route('app/gastos'); ?>?action=create" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>Descripcion * <span class="field-tip" data-tip="Detalle claro del gasto (ej. compra de insumos, arriendo, servicios).">?</span></label>
                    <input type="text" name="description" class="form-control" required>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label>Monto * <span class="field-tip" data-tip="Valor total del gasto en la moneda del sistema.">?</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha <span class="field-tip" data-tip="Fecha en que se realizó o se registra el gasto.">?</span></label>
                        <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label>Categoria <span class="field-tip" data-tip="Clasificación del gasto para reportes (General, Servicios, Nómina, etc.).">?</span></label>
                        <input type="text" name="category" class="form-control" value="General">
                    </div>
                    <div class="form-group">
                        <label>Metodo de pago <span class="field-tip" data-tip="Cómo se pagó este gasto.">?</span></label>
                        <select name="payment_method" class="form-control">
                            <option value="cash">Efectivo</option>
                            <option value="card">Tarjeta</option>
                            <option value="transfer">Transferencia</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Proveedor <span class="field-tip" data-tip="Proveedor asociado al gasto, si aplica.">?</span></label>
                    <select name="supplier_id" class="form-control">
                        <option value="">Sin proveedor</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Comprobante <span class="field-tip" data-tip="Número de factura, ticket o referencia del comprobante.">?</span></label>
                    <input type="text" name="receipt_number" class="form-control">
                </div>
                <div class="form-group">
                    <label>Notas</label>
                    <textarea name="notes" class="form-control" rows="2" title="Notas"></textarea>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="affect_cash" value="1" checked title="Registrar en caja">
                        Registrar egreso en caja abierta
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
