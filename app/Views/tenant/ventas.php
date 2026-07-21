<?php
$layout = 'tenant';
$title = 'Ventas - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Punto de Venta';
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$sales = $sales ?? [];
$todayTotal = $todayTotal ?? 0;
$products = $products ?? [];
$customers = $customers ?? [];
$currency = $currency ?? ['symbol' => '$', 'decimals' => 0];

function fmtV(float $a, array $c): string
{
    return $c['symbol'] . ' ' . number_format($a, $c['decimals'], $c['decimal'] ?? ',', $c['thousands'] ?? '.');
}
?>
<meta name="currency-symbol" content="<?php echo $currency['symbol']; ?>">
<meta name="currency-decimals" content="<?php echo $currency['decimals']; ?>">
<?php echo flashMessage(); ?>

<div class="stats-grid">
    <div class="stat-card neumorphic"><h4>Ventas Hoy</h4><div class="stat-value"><?php echo count($sales); ?></div></div>
    <div class="stat-card neumorphic"><h4>Total Hoy</h4><div class="stat-value" style="color:#10B981;"><?php echo fmtV($todayTotal, $currency); ?></div></div>
</div>

<div style="display:flex;gap:15px;margin-bottom:20px;flex-wrap:wrap;">
    <button onclick="openSaleModal()" class="btn btn-primary neumorphic-btn">🛒 Nueva Venta</button>
</div>

<div class="card neumorphic">
    <div class="card-header"><h3>📋 Últimas Ventas</h3></div>
    <div class="card-body">
        <?php if (empty($sales)): ?>
            <p style="text-align:center;color:var(--color-text-secondary);padding:20px;">No hay ventas registradas</p>
        <?php else: ?>
            <div class="table-container"><table>
                <thead><tr><th>Factura</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Pagado</th><th>Pago</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($sale['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'General'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($sale['sale_date'])); ?></td>
                        <td style="font-weight:600;"><?php echo fmtV($sale['total'], $currency); ?></td>
                        <td><?php echo $sale['payment_status'] === 'paid' ? fmtV($sale['total'], $currency) : fmtV($sale['paid_amount'] ?? 0, $currency); ?></td>
                        <td>
                            <span class="badge <?php echo $sale['payment_method'] === 'cash' ? 'badge-success' : ($sale['payment_method'] === 'card' ? 'badge-info' : 'badge-warning'); ?>">
                                <?php echo $sale['payment_method'] === 'cash' ? 'Efectivo' : ($sale['payment_method'] === 'card' ? 'Tarjeta' : ucfirst($sale['payment_method'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $sale['payment_status'] === 'paid' ? 'badge-success' : ($sale['payment_status'] === 'partial' ? 'badge-warning' : ($sale['status'] === 'cancelled' ? 'badge-danger' : 'badge-info')); ?>">
                                <?php echo $sale['payment_status'] === 'paid' ? 'Pagado' : ($sale['payment_status'] === 'partial' ? 'Parcial' : ($sale['status'] === 'cancelled' ? 'Cancelado' : 'Pendiente')); ?>
                            </span>
                        </td>
                        <td class="table-actions">
                            <button onclick="viewDetail(<?php echo $sale['id']; ?>)" class="btn btn-sm btn-info">👁️</button>
                            <button onclick="printInvoice(<?php echo $sale['id']; ?>)" class="btn btn-sm btn-secondary">🖨️</button>
                            <?php if (in_array($sale['payment_status'], ['pending', 'partial'])): ?>
                                <button onclick="openPaymentModal(<?php echo $sale['id']; ?>,<?php echo $sale['total']; ?>,<?php echo ($sale['paid_amount'] ?? 0); ?>)" class="btn btn-sm btn-success">💰</button>
                            <?php endif; ?>
                            <?php if ($sale['status'] !== 'cancelled'): ?>
                                <form method="POST" action="<?php echo $viewInstance->route('app/ventas'); ?>?action=cancel" style="display:inline;" data-ajax="true">
                                    <?php echo \SoftNova\Core\csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo $sale['id']; ?>">
                                    <button type="submit" onclick="return confirm('¿Cancelar?')" class="btn btn-sm btn-danger">↩️</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nueva Venta -->
<div id="saleModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:750px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header"><h3>Nueva Venta</h3><button onclick="closeSaleModal()" class="modal-close">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/ventas'); ?>?action=create" data-ajax="true" onsubmit="return prepareSaleItems()">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group">
                    <label>Cliente</label>
                    <select name="customer_id" id="customerSelect" class="form-control">
                        <option value="">Consumidor Final</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['first_name'] ?? $c['name']); ?> <?php echo htmlspecialchars($c['last_name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="(function(){var m=document.getElementById('quickCustomerModal');if(m)m.style.display='flex';else alert('Modal no encontrado');})()" style="margin-top:5px;font-size:12px;color:var(--color-primary);background:none;border:none;cursor:pointer;text-decoration:underline;">+ Nuevo Cliente</button>
                </div>
                <div class="form-group">
                    <label>Tipo de Pago</label>
                    <select name="payment_type" id="paymentType" class="form-control" onchange="toggleCredit()">
                        <option value="full">Pago Completo</option>
                        <option value="credit">A Crédito</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Método de Pago</label>
                    <select name="payment_method" class="form-control">
                        <option value="cash">Efectivo</option>
                        <option value="card">Tarjeta</option>
                        <option value="transfer">Transferencia</option>
                        <option value="other">Otro</option>
                    </select>
                </div>
                <div class="form-group" id="initialPaymentGroup" style="display:none;">
                    <label>Abono Inicial</label>
                    <input type="number" name="initial_payment" class="form-control" step="0.01" min="0" placeholder="0">
                </div>
            </div>
            <div class="form-group">
                <label>Notas</label>
                <input type="text" name="notes" class="form-control" placeholder="Observaciones...">
            </div>
            <div class="form-group">
                <label>Productos</label>
                <div style="display:flex;gap:8px;margin-bottom:8px;">
                    <select id="productSelect" class="form-control" style="flex:1;">
                        <option value="">Seleccionar producto...</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>"
                                    data-price="<?php echo $p['sale_price']; ?>"
                                    data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                    data-stock="<?php echo $p['stock']; ?>">
                                <?php echo htmlspecialchars($p['name']); ?> (<?php echo fmtV($p['sale_price'], $currency); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" id="productQty" value="1" min="1" class="form-control" style="width:80px;" placeholder="Cant">
                    <button type="button" class="btn btn-primary" onclick="addProduct()">+</button>
                </div>
                <table id="itemsTable" style="width:100%;margin-top:10px;display:none;">
                    <thead><tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Subtotal</th><th></th></tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><td colspan="3" style="text-align:right;font-weight:600;">Total:</td><td id="saleTotal" style="font-weight:700;color:#10B981;">0</td><td></td></tr></tfoot>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSaleModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="submitSaleBtn">Completar Venta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Quick Customer (encima del modal de venta) -->
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

<!-- Modal Abono -->
<div id="paymentModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:400px;">
        <div class="modal-header"><h3>Registrar Abono</h3><button onclick="document.getElementById('paymentModal').style.display='none'" class="modal-close">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/ventas'); ?>?action=payment" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="sale_id" id="paySaleId">
            <p id="payInfo" style="margin-bottom:10px;"></p>
            <div class="form-group"><label>Monto *</label><input type="number" name="amount" id="payAmount" class="form-control" step="0.01" min="0.01" required></div>
            <div class="form-group"><label>Método</label><select name="payment_method" class="form-control"><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option></select></div>
            <div class="form-group"><label>Notas</label><input type="text" name="notes" class="form-control" placeholder="Abono"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="document.getElementById('paymentModal').style.display='none'">Cancelar</button><button type="submit" class="btn btn-success">Registrar Abono</button></div>
        </form>
    </div>
</div>

<!-- Modal Detalle -->
<div id="saleDetailModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:650px;max-height:85vh;overflow-y:auto;">
        <div class="modal-header"><h3>Detalle de Venta</h3><button onclick="document.getElementById('saleDetailModal').style.display='none'" class="modal-close">&times;</button></div>
        <div class="modal-body" id="saleDetailContent"></div>
    </div>
</div>

<script>
    window.invRouteVentasDetail = '<?php echo $viewInstance->route('app/ventas'); ?>?action=detail';
</script>
<script src="<?php echo $viewInstance->asset('js/ventas.js'); ?>"></script>
