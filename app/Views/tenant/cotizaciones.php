<?php
$layout = 'tenant';
$title = 'Cotizaciones - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Gestión de Cotizaciones';
$tenantName = $tenantName ?? 'Mi Empresa'; $userName = $userName ?? 'Usuario';
$quotes = $quotes ?? []; $products = $products ?? []; $customers = $customers ?? [];
$currency = $currency ?? ['symbol'=>'$','decimals'=>0];
function fmtQ(float $a, array $c): string { return $c['symbol'].' '.number_format($a, $c['decimals'], $c['decimal']??',', $c['thousands']??'.'); }
?>
<meta name="currency-symbol" content="<?php echo $currency['symbol']; ?>">
<meta name="currency-decimals" content="<?php echo $currency['decimals']; ?>">
<?php echo flashMessage(); ?>

<div style="display:flex;gap:15px;margin-bottom:20px;">
    <button onclick="openQuoteModal()" class="btn btn-primary neumorphic-btn">📝 Nueva Cotización</button>
</div>

<div class="card neumorphic">
    <div class="card-header"><h3>📋 Cotizaciones</h3></div>
    <div class="card-body">
        <?php if (empty($quotes)): ?>
            <p style="text-align:center;color:var(--color-text-secondary);padding:20px;">No hay cotizaciones</p>
        <?php else: ?>
            <div class="table-container"><table>
                <thead><tr><th>Número</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Válido hasta</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($quotes as $q): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($q['quote_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($q['customer_name'] ?? 'General'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($q['quote_date'])); ?></td>
                        <td style="font-weight:600;"><?php echo fmtQ($q['total'], $currency); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($q['valid_until'])); ?></td>
                        <td>
                            <span class="badge <?php echo $q['status']==='pending'?'badge-warning':($q['status']==='accepted'?'badge-success':($q['status']==='converted'?'badge-info':'badge-danger')); ?>">
                                <?php echo $q['status']==='pending'?'Pendiente':($q['status']==='accepted'?'Aceptada':($q['status']==='converted'?'Convertida':'Rechazada')); ?>
                            </span>
                        </td>
                        <td class="table-actions">
                            <button onclick="viewQuoteDetail(<?php echo $q['id']; ?>)" class="btn btn-sm btn-info">👁️</button>
                            <?php if (in_array($q['status'], ['pending', 'accepted'])): ?>
                                <button onclick="openConvertModal(<?php echo $q['id']; ?>,<?php echo $q['total']; ?>)" class="btn btn-sm btn-success">💰 Convertir</button>
                            <?php endif; ?>
                            <form method="POST" action="<?php echo $viewInstance->route('app/cotizaciones'); ?>?action=delete" style="display:inline;" data-ajax="true">
                                <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                <button type="submit" onclick="return confirm('¿Eliminar?')" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nueva Cotización -->
<div id="quoteModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:750px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header"><h3>Nueva Cotización</h3><button onclick="closeQuoteModal()" class="modal-close">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/cotizaciones'); ?>?action=create" data-ajax="true" onsubmit="return prepareQuoteItems()">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label>Cliente</label>
                    <select name="customer_id" id="customerSelect" class="form-control">
                        <option value="">Consumidor Final</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['first_name']??$c['name']); ?> <?php echo htmlspecialchars($c['last_name']??''); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="(function(){var m=document.getElementById('quickCustomerModal');if(m)m.style.display='flex';})()" style="margin-top:5px;font-size:12px;color:var(--color-primary);background:none;border:none;cursor:pointer;text-decoration:underline;">+ Nuevo Cliente</button>
                </div>
                <div class="form-group"><label>Válido hasta</label><input type="date" name="valid_until" class="form-control" value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>"></div>
            </div>
            <div class="form-group"><label>Notas</label><input type="text" name="notes" class="form-control" placeholder="Observaciones..."></div>
            <div class="form-group"><label>Productos</label>
                <div style="display:flex;gap:8px;margin-bottom:8px;">
                    <select id="productSelect" class="form-control" style="flex:1;"><option value="">Seleccionar...</option><?php foreach($products as $p): ?><option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['sale_price']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?> (<?php echo fmtQ($p['sale_price'],$currency); ?>)</option><?php endforeach; ?></select>
                    <input type="number" id="productQty" value="1" min="1" class="form-control" style="width:80px;">
                    <button type="button" class="btn btn-primary" onclick="addQuoteProduct()">+</button>
                </div>
                <table id="itemsTable" style="width:100%;margin-top:10px;display:none;"><thead><tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Subtotal</th><th></th></tr></thead><tbody></tbody><tfoot><tr><td colspan="3" style="text-align:right;font-weight:600;">Total:</td><td id="quoteTotal" style="font-weight:700;color:#10B981;">0</td><td></td></tr></tfoot></table>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeQuoteModal()">Cancelar</button><button type="submit" class="btn btn-primary" id="submitQuoteBtn">Crear Cotización</button></div>
        </form>
    </div>
</div>

<!-- Modal Quick Customer (encima del modal de cotización) -->
<div id="quickCustomerModal" class="modal-overlay" style="display:none;z-index:1100;">
    <div class="modal-content neumorphic" style="max-width:450px;">
        <div class="modal-header">
            <h3>Nuevo Cliente</h3>
            <button onclick="closeQuickCustomer()" class="modal-close">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('app/clientes'); ?>?action=create" id="quickCustomerForm" onsubmit="return submitQuickCustomer(this)">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="modal-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group"><label>Nombre *</label><input type="text" name="first_name" class="form-control" required placeholder="Nombres"></div>
                    <div class="form-group"><label>Apellido</label><input type="text" name="last_name" class="form-control" placeholder="Apellidos"></div>
                    <div class="form-group"><label>Tipo Doc.</label><select name="document_type" class="form-control"><option value="CC">C.C</option><option value="CE">C.E</option><option value="NIT">NIT</option><option value="PPT">PPT</option><option value="OTROS">OTROS</option></select></div>
                    <div class="form-group"><label>N° Documento</label><input type="text" name="document_number" class="form-control" placeholder="Número"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" placeholder="correo@ejemplo.com"></div>
                    <div class="form-group"><label>Teléfono</label><input type="text" name="phone" class="form-control" placeholder="Teléfono"></div>
                </div>
                <div class="form-group"><label>Dirección</label><textarea name="address" class="form-control" rows="2" placeholder="Dirección"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeQuickCustomer()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear y Seleccionar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Convertir a Venta -->
<div id="convertModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:450px;">
        <div class="modal-header"><h3>Convertir a Venta</h3><button onclick="document.getElementById('convertModal').style.display='none'" class="modal-close">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/cotizaciones'); ?>?action=convert" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="id" id="convertQuoteId">
            <p id="convertInfo" style="margin-bottom:10px;"></p>
            <div class="form-group"><label>Tipo de Pago</label><select name="payment_type" class="form-control" onchange="toggleConvertCredit()"><option value="full">Pago Completo</option><option value="credit">A Crédito</option></select></div>
            <div class="form-group"><label>Método</label><select name="payment_method" class="form-control"><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option></select></div>
            <div class="form-group" id="convertInitialGroup" style="display:none;"><label>Abono Inicial</label><input type="number" name="initial_payment" class="form-control" step="0.01" min="0" placeholder="0"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="document.getElementById('convertModal').style.display='none'">Cancelar</button><button type="submit" class="btn btn-success">Convertir a Venta</button></div>
        </form>
    </div>
</div>

<!-- Modal Detalle -->
<div id="quoteDetailModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:650px;max-height:85vh;overflow-y:auto;">
        <div class="modal-header"><h3>Detalle de Cotización</h3><button onclick="document.getElementById('quoteDetailModal').style.display='none'" class="modal-close">&times;</button></div>
        <div class="modal-body" id="quoteDetailContent"></div>
    </div>
</div>

<script>
window.quoteRouteCreate = '<?php echo $viewInstance->route('app/cotizaciones'); ?>?action=create';
window.quoteRouteDetail = '<?php echo $viewInstance->route('app/cotizaciones'); ?>?action=detail';
</script>
<script src="<?php echo $viewInstance->asset('js/cotizaciones.js'); ?>"></script>
