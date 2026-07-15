<?php
$layout = 'tenant';
$title = 'Caja - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Control de Caja';
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$openSession = $openSession ?? null;
$movements = $movements ?? [];
$totals = $totals ?? ['incomes' => 0, 'expenses' => 0, 'balance' => 0];
$historySessions = $historySessions ?? [];
?>

<?php echo flashMessage(); ?>

<?php if (!$openSession): ?>
<!-- CAJA CERRADA - Botón para abrir -->
<div class="card neumorphic" style="text-align: center; padding: 40px;">
    <div style="font-size: 64px; margin-bottom: 15px;">🔒</div>
    <h2 style="color: var(--color-primary); margin-bottom: 10px;">Caja Cerrada</h2>
    <p style="color: var(--color-text-secondary); margin-bottom: 20px;">
        No hay ninguna sesión de caja abierta. Abra la caja para comenzar a operar.
    </p>
    <button onclick="document.getElementById('openCashModal').style.display='flex'"
            class="btn btn-primary neumorphic-btn" style="font-size: 16px; padding: 12px 30px;">
        🔓 Abrir Caja
    </button>
</div>

<!-- Modal Abrir Caja -->
<div id="openCashModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Abrir Caja</h3>
            <button onclick="document.getElementById('openCashModal').style.display='none'" class="modal-close">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('app/caja'); ?>?action=open" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>Monto de Apertura ($) *</label>
                    <input type="number" name="opening_amount" class="form-control" 
                           step="0.01" min="0" value="0.00" required autofocus>
                </div>
                <div class="form-group">
                    <label>Notas (opcional)</label>
                    <textarea name="notes" class="form-control" rows="2" 
                              placeholder="Observaciones de apertura..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" 
                        onclick="document.getElementById('openCashModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary">Abrir Caja</button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- CAJA ABIERTA -->
<div class="stats-grid">
    <div class="stat-card neumorphic">
        <h4>Monto Apertura</h4>
        <div class="stat-value">$<?php echo number_format($openSession['opening_amount'], 2); ?></div>
    </div>
    <div class="stat-card neumorphic">
        <h4>Ingresos</h4>
        <div class="stat-value" style="color: #10B981;">+$<?php echo number_format($totals['incomes'], 2); ?></div>
    </div>
    <div class="stat-card neumorphic">
        <h4>Egresos</h4>
        <div class="stat-value" style="color: #DC2626;">-$<?php echo number_format($totals['expenses'], 2); ?></div>
    </div>
    <div class="stat-card neumorphic">
        <h4>Balance Actual</h4>
        <div class="stat-value" style="color: <?php echo $totals['balance'] >= 0 ? '#10B981' : '#DC2626'; ?>;">
            $<?php echo number_format($totals['balance'], 2); ?>
        </div>
    </div>
</div>

<!-- Info de sesión + botones -->
<div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
    <div style="flex: 1; min-width: 200px;">
        <span style="color: var(--color-text-secondary); font-size: 13px;">
            Abierta por: <strong><?php echo htmlspecialchars($openSession['user_name']); ?></strong> · 
            <?php echo date('d/m/Y H:i', strtotime($openSession['opening_date'])); ?>
        </span>
    </div>
    <button onclick="document.getElementById('movementModal').style.display='flex'" 
            class="btn btn-primary neumorphic-btn">+ Registrar Movimiento</button>
    <button onclick="document.getElementById('closeCashModal').style.display='flex'" 
            class="btn btn-danger">Cerrar Caja</button>
</div>

<!-- Tabla de movimientos -->
<div class="card neumorphic">
    <div class="card-header">
        <h3>📋 Movimientos del Día (<?php echo count($movements); ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($movements)): ?>
            <p style="text-align: center; color: var(--color-text-secondary); padding: 20px;">
                No hay movimientos registrados hoy
            </p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $mov): ?>
                            <tr>
                                <td><?php echo date('H:i', strtotime($mov['created_at'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $mov['type'] === 'income' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $mov['type'] === 'income' ? 'Ingreso' : 'Egreso'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($mov['description']); ?></td>
                                <td style="color: <?php echo $mov['type'] === 'income' ? '#10B981' : '#DC2626'; ?>; font-weight: 600;">
                                    <?php echo $mov['type'] === 'income' ? '+' : '-'; ?>$<?php echo number_format($mov['amount'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Registrar Movimiento -->
<div id="movementModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Registrar Movimiento</h3>
            <button onclick="document.getElementById('movementModal').style.display='none'" class="modal-close">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('app/caja'); ?>?action=movement" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="session_id" value="<?php echo $openSession['id']; ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="type" class="form-control" required>
                        <option value="income">Ingreso (+)</option>
                        <option value="expense">Egreso (-)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Descripción *</label>
                    <input type="text" name="description" class="form-control" 
                           placeholder="Ej: Pago a proveedor, venta mostrador..." required>
                </div>
                <div class="form-group">
                    <label>Monto ($) *</label>
                    <input type="number" name="amount" class="form-control" 
                           step="0.01" min="0.01" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" 
                        onclick="document.getElementById('movementModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary">Registrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Cerrar Caja -->
<div id="closeCashModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Cerrar Caja</h3>
            <button onclick="document.getElementById('closeCashModal').style.display='none'" class="modal-close">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('app/caja'); ?>?action=close" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="session_id" value="<?php echo $openSession['id']; ?>">
            <div class="modal-body">
                <p style="margin-bottom: 15px; color: var(--color-text-secondary);">
                    Balance esperado según movimientos: 
                    <strong style="color: #10B981;">$<?php echo number_format($totals['balance'], 2); ?></strong>
                </p>
                <div class="form-group">
                    <label>Monto Final Contado ($) *</label>
                    <input type="number" name="closing_amount" class="form-control" 
                           step="0.01" min="0" value="<?php echo number_format($totals['balance'], 2, '.', ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Notas de cierre</label>
                    <textarea name="notes" class="form-control" rows="2" 
                              placeholder="Observaciones..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" 
                        onclick="document.getElementById('closeCashModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-danger">Cerrar Caja</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Historial de cierres -->
<?php if (!empty($historySessions)): ?>
<div class="card neumorphic" style="margin-top: 20px;">
    <div class="card-header">
        <h3>📦 Historial de Cierres</h3>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Apertura</th>
                        <th>Cierre</th>
                        <th>Usuario</th>
                        <th>Monto Inicial</th>
                        <th>Monto Final</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historySessions as $h): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($h['opening_date'])); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($h['closing_date'])); ?></td>
                            <td><?php echo htmlspecialchars($h['user_name']); ?></td>
                            <td>$<?php echo number_format($h['opening_amount'], 2); ?></td>
                            <td>$<?php echo number_format($h['closing_amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
