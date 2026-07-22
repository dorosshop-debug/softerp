<?php
$layout = 'tenant';
$title = 'Caja - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Control de Caja';
$tenantName = $tenantName ?? 'Mi Empresa'; $userName = $userName ?? 'Usuario';
$openSession = $openSession ?? null; $movements = $movements ?? [];
$todaySales = $todaySales ?? [];
$totals = $totals ?? ['incomes'=>0,'expenses'=>0,'balance'=>0];
$historySessions = $historySessions ?? [];
$currency = $currency ?? ['symbol'=>'$','name'=>'Peso','decimals'=>0];
function fmt(float $a, array $c): string { return $c['symbol'].' '.number_format($a,$c['decimals'],$c['decimal']??',',$c['thousands']??'.'); }
function hoursOpen(string $date): float { return round((time()-strtotime($date))/3600, 1); }
?>
<?php echo flashMessage(); ?>

<?php if (!$openSession): ?>
<div class="card neumorphic" style="text-align:center;padding:40px;">
    <div style="font-size:64px;margin-bottom:15px;">🔒</div>
    <h2 style="color:var(--color-primary);margin-bottom:10px;">Caja Cerrada</h2>
    <p style="color:var(--color-text-secondary);margin-bottom:20px;">No hay ninguna sesión de caja abierta.</p>
    <button onclick="document.getElementById('openCashModal').style.display='flex'" class="btn btn-primary neumorphic-btn" style="font-size:16px;padding:12px 30px;">🔓 Abrir Caja</button>
</div>

<div id="openCashModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:450px;">
        <div class="modal-header"><h3>Abrir Caja</h3><button onclick="document.getElementById('openCashModal').style.display='none'" class="modal-close">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/caja'); ?>?action=open" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="modal-body">
                <div class="form-group"><label>Monto de Apertura (<?php echo $currency['symbol']; ?>) *</label><input type="number" name="opening_amount" class="form-control" step="0.01" min="0" placeholder="0" required autofocus></div>
                <div class="form-group"><label>Notas</label><textarea name="notes" class="form-control" rows="2" placeholder="Observaciones..."></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="document.getElementById('openCashModal').style.display='none'">Cancelar</button><button type="submit" class="btn btn-primary">Abrir Caja</button></div>
        </form>
    </div>
</div>

<?php else: ?>
<?php $hours = hoursOpen($openSession['opening_date']); $warningClass = $hours > 20 ? 'badge-danger' : ($hours > 16 ? 'badge-warning' : 'badge-success'); ?>
<!-- CAJA ABIERTA -->
<div class="stats-grid">
    <div class="stat-card neumorphic"><h4>Monto Apertura</h4><div class="stat-value"><?php echo fmt($openSession['opening_amount'], $currency); ?></div></div>
    <div class="stat-card neumorphic"><h4>Ingresos</h4><div class="stat-value" style="color:#10B981;">+<?php echo fmt($totals['incomes'], $currency); ?></div></div>
    <div class="stat-card neumorphic"><h4>Egresos</h4><div class="stat-value" style="color:#DC2626;">-<?php echo fmt($totals['expenses'], $currency); ?></div></div>
    <div class="stat-card neumorphic"><h4>Balance Actual</h4><div class="stat-value" style="color:<?php echo $totals['balance']>=0?'#10B981':'#DC2626';?>;"><?php echo fmt($totals['balance'], $currency); ?></div></div>
</div>

<div style="display:flex;gap:15px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
    <div style="flex:1;min-width:200px;">
        <span style="color:var(--color-text-secondary);font-size:13px;">
            Abierta por: <strong><?php echo htmlspecialchars($openSession['user_name']); ?></strong> · <?php echo date('d/m/Y H:i', strtotime($openSession['opening_date'])); ?>
        </span>
        <span class="badge <?php echo $warningClass; ?>" style="margin-left:8px;">
            ⏱️ <?php echo $hours; ?>h abierta
            <?php if ($hours > 20): ?> ⚠️ ¡Cierre antes de 24h!<?php endif; ?>
        </span>
        <?php if ($hours > 22): ?>
        <div class="alert alert-error" style="margin-top:8px;font-size:13px;">
            ⚠️ <strong>¡Atención!</strong> La caja lleva más de 22 horas abierta. Debe cerrarse antes de 24 horas.
        </div>
        <?php endif; ?>
    </div>
    <button onclick="document.getElementById('movementModal').style.display='flex'" class="btn btn-primary neumorphic-btn">+ Registrar Movimiento</button>
    <button onclick="document.getElementById('closeCashModal').style.display='flex'" class="btn btn-danger">Cerrar Caja</button>
</div>

<!-- Ventas del Día -->
<?php if (!empty($todaySales)): ?>
<div class="card neumorphic" style="margin-bottom:20px;">
    <div class="card-header"><h3>🛒 Ventas del Día (<?php echo count($todaySales); ?>)</h3></div>
    <div class="card-body">
        <div class="table-container"><table>
            <thead><tr><th>Factura</th><th>Cliente</th><th>Hora</th><th>Método</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach($todaySales as $sale): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($sale['invoice_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Cliente general'); ?></td>
                    <td><?php echo date('H:i', strtotime($sale['sale_date'])); ?></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($sale['payment_method'] ?? 'cash'); ?></span></td>
                    <td style="color:#10B981;font-weight:600;">+<?php echo fmt((float)$sale['total'], $currency); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>
<?php endif; ?>

<div class="card neumorphic">
    <div class="card-header"><h3>📋 Movimientos del Día (<?php echo count($movements); ?>)</h3></div>
    <div class="card-body">
        <?php if (empty($movements)): ?>
            <p style="text-align:center;color:var(--color-text-secondary);padding:20px;">No hay movimientos</p>
        <?php else: ?>
            <div class="table-container"><table>
                <thead><tr><th>Hora</th><th>Tipo</th><th>Descripción</th><th>Monto</th></tr></thead>
                <tbody><?php foreach($movements as $mov): ?>
                    <tr><td><?php echo date('H:i',strtotime($mov['created_at'])); ?></td><td><span class="badge <?php echo $mov['type']==='income'?'badge-success':'badge-danger';?>"><?php echo $mov['type']==='income'?'Ingreso':'Egreso';?></span></td><td><?php echo htmlspecialchars($mov['description']);?></td><td style="color:<?php echo $mov['type']==='income'?'#10B981':'#DC2626';?>;font-weight:600;"><?php echo $mov['type']==='income'?'+':'-';?><?php echo fmt($mov['amount'],$currency);?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </div>
</div>

<!-- Modales de movimiento y cierre -->
<div id="movementModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:450px;">
        <div class="modal-header"><h3>Registrar Movimiento</h3><button onclick="document.getElementById('movementModal').style.display='none'" class="modal-close">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/caja'); ?>?action=movement" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="session_id" value="<?php echo $openSession['id']; ?>">
            <div class="modal-body">
                <div class="form-group"><label>Tipo *</label><select name="type" class="form-control" required><option value="income">Ingreso (+)</option><option value="expense">Egreso (-)</option></select></div>
                <div class="form-group"><label>Descripción *</label><input type="text" name="description" class="form-control" placeholder="Ej: Pago proveedor..." required></div>
                <div class="form-group"><label>Monto (<?php echo $currency['symbol']; ?>) *</label><input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="0" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="document.getElementById('movementModal').style.display='none'">Cancelar</button><button type="submit" class="btn btn-primary">Registrar</button></div>
        </form>
    </div>
</div>

<div id="closeCashModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:450px;">
        <div class="modal-header"><h3>Cerrar Caja</h3><button onclick="document.getElementById('closeCashModal').style.display='none'" class="modal-close">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/caja'); ?>?action=close" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="session_id" value="<?php echo $openSession['id']; ?>">
            <div class="modal-body">
                <p style="margin-bottom:15px;color:var(--color-text-secondary);">Balance esperado: <strong style="color:#10B981;"><?php echo fmt($totals['balance'],$currency);?></strong></p>
                <div class="form-group"><label>Monto Final Contado (<?php echo $currency['symbol'];?>) *</label><input type="number" name="closing_amount" class="form-control" step="0.01" min="0" placeholder="0" required></div>
                <div class="form-group"><label>Notas de cierre</label><textarea name="notes" class="form-control" rows="2" placeholder="Observaciones..."></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="document.getElementById('closeCashModal').style.display='none'">Cancelar</button><button type="submit" class="btn btn-danger">Cerrar Caja</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Historial de cierres -->
<?php if (!empty($historySessions)): ?>
<div class="card neumorphic" style="margin-top:20px;">
    <div class="card-header"><h3>📦 Historial de Cierres</h3></div>
    <div class="card-body">
        <div class="table-container"><table>
            <thead><tr><th>Apertura</th><th>Cierre</th><th>Usuario</th><th>Monto Ini.</th><th>Monto Fin</th><th>Dif.</th><th>PDF</th></tr></thead>
            <tbody><?php foreach($historySessions as $h): ?>
                <?php $diff = ($h['closing_amount']??0)-($h['opening_amount']??0); ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i',strtotime($h['opening_date']));?></td>
                    <td><?php echo date('d/m/Y H:i',strtotime($h['closing_date']));?></td>
                    <td><?php echo htmlspecialchars($h['user_name']);?></td>
                    <td><?php echo fmt($h['opening_amount'],$currency);?></td>
                    <td><?php echo fmt($h['closing_amount']??0,$currency);?></td>
                    <td style="color:<?php echo $diff>=0?'#10B981':'#DC2626';?>;"><?php echo ($diff>=0?'+':'').fmt($diff,$currency);?></td>
                    <td><a href="<?php echo $viewInstance->route('app/caja'); ?>?action=pdf&id=<?php echo $h['id']; ?>" class="btn btn-sm btn-info" target="_blank" title="Descargar PDF"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg></a></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
</div>
<?php endif; ?>

<script>
function printClosing(id) {
    const row = event.target.closest('tr');
    const cells = row.querySelectorAll('td');
    const printWin = window.open('','_blank','width=400,height=600');
    printWin.document.write(`<!DOCTYPE html><html><head><title>Cierre de Caja</title>
        <style>body{font-family:Arial;padding:20px;font-size:12px;} h2{text-align:center;} table{width:100%;border-collapse:collapse;} td{padding:6px;border-bottom:1px solid #ddd;} .right{text-align:right;} .green{color:green;} .red{color:red;}</style></head>
        <body><h2>Cierre de Caja #${id}</h2><p style="text-align:center;color:#666;">${getTenantName()} — ${new Date().toLocaleDateString()}</p>
        <table><tr><td>Apertura:</td><td class="right">${cells[0].textContent}</td></tr>
        <tr><td>Cierre:</td><td class="right">${cells[1].textContent}</td></tr>
        <tr><td>Usuario:</td><td>${cells[2].textContent}</td></tr>
        <tr><td>Monto Inicial:</td><td class="right">${cells[3].textContent}</td></tr>
        <tr><td>Monto Final:</td><td class="right">${cells[4].textContent}</td></tr>
        <tr><td>Diferencia:</td><td class="right ${cells[5].style.color}">${cells[5].textContent}</td></tr></table>
        <p style="text-align:center;margin-top:20px;color:#999;">SoftNova ERP — Osgo Support 2026</p></body></html>`);
    printWin.document.close();
    setTimeout(()=>printWin.print(),300);
}
function getTenantName(){return '<?php echo htmlspecialchars($tenantName);?>';}
</script>
