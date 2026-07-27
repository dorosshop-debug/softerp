<?php
$layout = 'tenant';
$title = 'Compras - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Compras a proveedores';
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$purchases = $purchases ?? [];
$suppliers = $suppliers ?? [];
$monthTotal = $monthTotal ?? 0;
$currency = $currency ?? ['symbol' => '$', 'decimals' => 2, 'decimal' => ',', 'thousands' => '.'];
$pagination = $pagination ?? null;
$filters = $filters ?? ['q' => '', 'from' => '', 'to' => '', 'status' => '', 'query' => []];

function fmtC(float $a, array $c): string
{
    return $c['symbol'] . ' ' . number_format($a, $c['decimals'], $c['decimal'] ?? ',', $c['thousands'] ?? '.');
}
?>
<?php echo flashMessage(); ?>

<div class="stats-grid">
    <div class="stat-card neumorphic"><h4>Compras del mes</h4><div class="stat-value" style="color:var(--color-primary);"><?php echo fmtC((float)$monthTotal, $currency); ?></div></div>
    <div class="stat-card neumorphic"><h4>Registros</h4><div class="stat-value"><?php echo (int)($pagination['total'] ?? count($purchases)); ?></div></div>
</div>

<div class="card neumorphic" style="margin-bottom:18px;">
    <div class="card-body" style="padding:14px 18px;">
        <form method="GET" action="<?php echo $viewInstance->route('app/compras'); ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
            <div class="form-group" style="margin:0;"><label>Buscar</label><input type="search" name="q" class="form-control" value="<?php echo htmlspecialchars($filters['q']); ?>" placeholder="Nº, proveedor..."></div>
            <div class="form-group" style="margin:0;"><label>Desde</label><input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($filters['from']); ?>"></div>
            <div class="form-group" style="margin:0;"><label>Hasta</label><input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($filters['to']); ?>"></div>
            <div class="form-group" style="margin:0;"><label>Estado</label>
                <select name="status" class="form-control">
                    <option value="">Todos</option>
                    <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completadas</option>
                    <option value="cancelled" <?php echo $filters['status'] === 'cancelled' ? 'selected' : ''; ?>>Canceladas</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('purchaseModal').style.display='flex'">Nueva compra</button>
        </form>
    </div>
</div>

<div class="card neumorphic">
    <div class="card-header"><h3>Listado de compras</h3></div>
    <div class="card-body">
        <?php if (empty($purchases)): ?>
            <p style="text-align:center;color:var(--color-text-secondary);padding:20px;">No hay compras registradas</p>
        <?php else: ?>
            <div class="table-container"><table>
                <thead>
                <tr>
                    <?php $comprasBase = $viewInstance->route('app/compras'); ?>
                    <th><?php $viewInstance->partial('sortable_th', ['label'=>'Número','column'=>'number','filters'=>$filters,'baseUrl'=>$comprasBase]); ?></th>
                    <th><?php $viewInstance->partial('sortable_th', ['label'=>'Fecha','column'=>'date','filters'=>$filters,'baseUrl'=>$comprasBase]); ?></th>
                    <th><?php $viewInstance->partial('sortable_th', ['label'=>'Proveedor','column'=>'supplier','filters'=>$filters,'baseUrl'=>$comprasBase]); ?></th>
                    <th><?php $viewInstance->partial('sortable_th', ['label'=>'Total','column'=>'total','filters'=>$filters,'baseUrl'=>$comprasBase]); ?></th>
                    <th>Pago</th><th>Estado</th><th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($p['purchase_number']); ?></strong></td>
                        <td><?php echo date('d/m/Y', strtotime($p['purchase_date'])); ?></td>
                        <td><?php echo htmlspecialchars($p['supplier_name'] ?? '—'); ?></td>
                        <td style="font-weight:600;"><?php echo fmtC((float)$p['total'], $currency); ?></td>
                        <td><?php echo htmlspecialchars(\SoftNova\Core\payment_method_label((string)$p['payment_method'])); ?></td>
                        <td>
                            <?php if ($p['status'] === 'cancelled'): ?>
                                <span class="badge badge-danger">Cancelada</span>
                            <?php else: ?>
                                <span class="badge badge-success">Completada</span>
                            <?php endif; ?>
                        </td>
                        <td class="table-actions">
                            <button type="button" class="btn btn-sm btn-secondary" title="Detalle" onclick="showPurchase(<?php echo (int)$p['id']; ?>)">Ver</button>
                            <?php if ($p['status'] === 'completed'): ?>
                            <form method="POST" action="<?php echo $viewInstance->route('app/compras'); ?>?action=cancel" style="display:inline;" data-ajax="true">
                                <?php echo \SoftNova\Core\csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Cancelar compra y revertir stock?')" title="Cancelar">✕</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php
            $paginationBaseUrl = $viewInstance->route('app/compras');
            $paginationQuery = $filters['query'] ?? [];
            $viewInstance->partial('pagination', compact('pagination', 'paginationBaseUrl', 'paginationQuery'));
            ?>
        <?php endif; ?>
    </div>
</div>

<div id="purchaseModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:780px;">
        <div class="modal-header">
            <h3>Nueva compra a proveedor</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('purchaseModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('app/compras'); ?>?action=create" data-ajax="true" data-prepare="purchaseItems" id="purchaseForm">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Proveedor</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">— Sin proveedor —</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Fecha de ingreso *</label>
                        <input type="date" name="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Método de pago</label>
                        <select name="payment_method" class="form-control"><?php echo \SoftNova\Core\payment_method_options('cash'); ?></select>
                    </div>
                    <div class="form-group">
                        <label>Estado de pago</label>
                        <select name="payment_status" class="form-control">
                            <option value="paid">Pagada</option>
                            <option value="pending">Por pagar</option>
                        </select>
                    </div>
                    <div class="form-group"><label><input type="checkbox" name="affect_cash" value="1"> Descontar de caja (solo efectivo)</label></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>Notas</label><input type="text" name="notes" class="form-control"></div>
                </div>

                <hr style="margin:16px 0;border:none;border-top:1px solid var(--color-border);">
                <div style="display:flex;gap:8px;margin-bottom:10px;">
                    <input type="search" id="purchaseProductSearch" class="form-control" placeholder="Buscar producto..." style="flex:1;">
                    <button type="button" class="btn btn-secondary" onclick="searchPurchaseProducts()">Buscar</button>
                </div>
                <div id="purchaseProductResults" style="max-height:140px;overflow:auto;margin-bottom:10px;"></div>
                <table class="table" id="purchaseItemsTable">
                    <thead><tr><th>Producto</th><th>Cant.</th><th>Costo unit.</th><th>Subtotal</th><th></th></tr></thead>
                    <tbody></tbody>
                </table>
                <div style="text-align:right;font-weight:700;">Total: <span id="purchaseTotal">0</span></div>
                <div id="purchaseItemsHidden"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('purchaseModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary">Registrar compra</button>
            </div>
        </form>
    </div>
</div>

<div id="purchaseDetailModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:640px;">
        <div class="modal-header">
            <h3>Detalle de compra</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('purchaseDetailModal').style.display='none'">&times;</button>
        </div>
        <div class="modal-body" id="purchaseDetailBody"></div>
    </div>
</div>

<script>
var purchaseCart = [];
var purchaseSearchUrl = <?php echo json_encode($viewInstance->route('app/compras') . '?action=products'); ?>;
var currencySym = <?php echo json_encode($currency['symbol'] ?? '$'); ?>;

function searchPurchaseProducts() {
    var q = document.getElementById('purchaseProductSearch').value.trim();
    fetch(purchaseSearchUrl + '&q=' + encodeURIComponent(q), {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.json())
        .then(data => {
            var box = document.getElementById('purchaseProductResults');
            box.innerHTML = '';
            (data.products || []).forEach(function(p) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-secondary';
                btn.style.margin = '2px';
                btn.textContent = (p.code ? p.code + ' — ' : '') + p.name + ' (stock ' + p.stock + ')';
                btn.onclick = function() { addPurchaseItem(p); };
                box.appendChild(btn);
            });
        });
}

function addPurchaseItem(p) {
    var existing = purchaseCart.find(function(i){ return i.product_id == p.id; });
    if (existing) { existing.quantity++; }
    else {
        purchaseCart.push({
            product_id: p.id,
            name: p.name,
            quantity: 1,
            unit_cost: parseFloat(p.purchase_price) || 0
        });
    }
    renderPurchaseCart();
}

function removePurchaseItem(idx) {
    purchaseCart.splice(idx, 1);
    renderPurchaseCart();
}

function renderPurchaseCart() {
    var tbody = document.querySelector('#purchaseItemsTable tbody');
    tbody.innerHTML = '';
    var total = 0;
    purchaseCart.forEach(function(item, idx) {
        var sub = item.quantity * item.unit_cost;
        total += sub;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td>' + item.name + '</td>'
            + '<td><input type="number" min="1" value="' + item.quantity + '" style="width:70px" onchange="purchaseCart['+idx+'].quantity=parseInt(this.value)||1;renderPurchaseCart()"></td>'
            + '<td><input type="number" min="0" step="0.01" value="' + item.unit_cost + '" style="width:100px" onchange="purchaseCart['+idx+'].unit_cost=parseFloat(this.value)||0;renderPurchaseCart()"></td>'
            + '<td>' + currencySym + ' ' + sub.toFixed(2) + '</td>'
            + '<td><button type="button" class="btn btn-sm btn-danger" onclick="removePurchaseItem('+idx+')">✕</button></td>';
        tbody.appendChild(tr);
    });
    document.getElementById('purchaseTotal').textContent = currencySym + ' ' + total.toFixed(2);
}

function preparePurchaseItems() {
    if (!purchaseCart.length) {
        showAlert('Agregue al menos un producto', 'warning');
        return false;
    }
    var box = document.getElementById('purchaseItemsHidden');
    box.innerHTML = '';
    purchaseCart.forEach(function(item, i) {
        ['product_id','quantity','unit_cost'].forEach(function(k) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'items[' + i + '][' + k + ']';
            input.value = item[k];
            box.appendChild(input);
        });
    });
    return true;
}

function showPurchase(id) {
    fetch(<?php echo json_encode($viewInstance->route('app/compras')); ?> + '?action=detail&id=' + id, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showAlert(data.message || 'Error', 'error'); return; }
            var p = data.purchase;
            var html = '<p><strong>' + p.purchase_number + '</strong> · ' + (p.supplier_name || 'Sin proveedor') + '</p>';
            html += '<p>Fecha ingreso: ' + p.purchase_date + ' · Total: ' + currencySym + ' ' + parseFloat(p.total).toFixed(2) + '</p>';
            html += '<table class="table"><thead><tr><th>Producto</th><th>Cant.</th><th>Costo</th><th>Subtotal</th></tr></thead><tbody>';
            (data.items || []).forEach(function(it) {
                html += '<tr><td>' + it.product_name + '</td><td>' + it.quantity + '</td><td>' + it.unit_cost + '</td><td>' + it.subtotal + '</td></tr>';
            });
            html += '</tbody></table>';
            if (data.movements && data.movements.length) {
                html += '<h4 style="margin-top:12px;">Trazabilidad de inventario</h4><ul>';
                data.movements.forEach(function(m) {
                    html += '<li>' + (m.movement_date || m.created_at) + ' · ' + m.type + ' ' + m.quantity + ' · ' + (m.product_name || '') + '</li>';
                });
                html += '</ul>';
            }
            document.getElementById('purchaseDetailBody').innerHTML = html;
            document.getElementById('purchaseDetailModal').style.display = 'flex';
        });
}

// Hook prepare en app.js via data-prepare
window.preparePurchaseItems = preparePurchaseItems;
</script>
