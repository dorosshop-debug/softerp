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
function fmtV(float $a, array $c): string { return $c['symbol'].' '.number_format($a, $c['decimals'], $c['decimal']??',', $c['thousands']??'.'); }
?>

<?php echo flashMessage(); ?>

<!-- KPIs -->
<div class="stats-grid">
    <div class="stat-card neumorphic">
        <h4>Ventas Hoy</h4>
        <div class="stat-value"><?php echo number_format(count($sales)); ?></div>
    </div>
    <div class="stat-card neumorphic">
        <h4>Total Hoy</h4>
        <div class="stat-value" style="color:#10B981;"><?php echo fmtV($todayTotal, $currency); ?></div>
    </div>
</div>

<div style="display:flex; gap:15px; margin-bottom:20px;">
    <button onclick="openSaleModal()" class="btn btn-primary neumorphic-btn" style="font-size:16px;padding:12px 30px;">🛒 Nueva Venta</button>
</div>

<!-- Tabla de ventas -->
<div class="card neumorphic">
    <div class="card-header"><h3>📋 Últimas Ventas</h3></div>
    <div class="card-body">
        <?php if (empty($sales)): ?>
            <p style="text-align:center;color:var(--color-text-secondary);padding:20px;">No hay ventas registradas</p>
        <?php else: ?>
            <div class="table-container"><table>
                <thead><tr><th>Factura</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Pago</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($sale['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'General'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($sale['sale_date'])); ?></td>
                        <td style="font-weight:600;"><?php echo fmtV($sale['total'], $currency); ?></td>
                        <td><span class="badge badge-info"><?php echo $sale['payment_method'] === 'cash' ? 'Efectivo' : ($sale['payment_method'] === 'card' ? 'Tarjeta' : ucfirst($sale['payment_method'])); ?></span></td>
                        <td><span class="badge <?php echo $sale['status'] === 'completed' ? 'badge-success' : 'badge-warning'; ?>"><?php echo $sale['status'] === 'completed' ? 'Completada' : 'Pendiente'; ?></span></td>
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
                <div class="form-group"><label>Cliente</label><select name="customer_id" class="form-control"><option value="">Consumidor Final</option><?php foreach($customers as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Método de Pago</label><select name="payment_method" class="form-control"><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option><option value="other">Otro</option></select></div>
            </div>
            
            <div class="form-group"><label>Notas</label><input type="text" name="notes" class="form-control" placeholder="Observaciones..."></div>
            
            <div class="form-group">
                <label>Productos</label>
                <div style="display:flex;gap:8px;margin-bottom:8px;">
                    <select id="productSelect" class="form-control" style="flex:1;">
                        <option value="">Seleccionar producto...</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['sale_price']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>" data-stock="<?php echo $p['stock']; ?>">
                                <?php echo htmlspecialchars($p['name']); ?> (<?php echo fmtV($p['sale_price'], $currency); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" id="productQty" value="1" min="1" class="form-control" style="width:80px;" placeholder="Cant">
                    <button type="button" class="btn btn-primary" onclick="addProduct()">+ Agregar</button>
                </div>
                <table id="itemsTable" style="width:100%;margin-top:10px;display:none;">
                    <thead><tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Subtotal</th><th></th></tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><td colspan="3" style="text-align:right;font-weight:600;">Total:</td><td id="saleTotal" style="font-weight:700;color:#10B981;">0</td><td></td></tr></tfoot>
                </table>
            </div>
            
            <div class="modal-footer" style="margin-top:15px;">
                <button type="button" class="btn btn-secondary" onclick="closeSaleModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="submitSaleBtn">Completar Venta</button>
            </div>
        </form>
    </div>
</div>

<script>
let saleItems = [];
let currencySymbol = '<?php echo $currency['symbol']; ?>';

function addProduct() {
    const sel = document.getElementById('productSelect');
    const qty = parseInt(document.getElementById('productQty').value) || 1;
    if (!sel.value) return alert('Seleccione un producto');
    
    const opt = sel.options[sel.selectedIndex];
    const id = opt.value, name = opt.dataset.name, price = parseFloat(opt.dataset.price), stock = parseInt(opt.dataset.stock);
    
    if (qty > stock) return alert('Stock insuficiente: ' + stock);
    
    const existing = saleItems.find(i => i.product_id == id);
    if (existing) { existing.quantity += qty; existing.subtotal = existing.quantity * price; }
    else { saleItems.push({product_id: id, product_name: name, quantity: qty, unit_price: price, subtotal: qty * price}); }
    
    document.getElementById('productQty').value = 1;
    renderItems();
}

function removeItem(idx) { saleItems.splice(idx, 1); renderItems(); }

function renderItems() {
    const tbody = document.querySelector('#itemsTable tbody');
    const table = document.getElementById('itemsTable');
    let total = 0;
    tbody.innerHTML = '';
    
    if (saleItems.length === 0) { table.style.display = 'none'; return; }
    table.style.display = '';
    
    saleItems.forEach((item, i) => {
        total += item.subtotal;
        tbody.innerHTML += `<tr>
            <td>${item.product_name}</td>
            <td>${item.quantity}</td>
            <td>${currencySymbol} ${item.unit_price.toFixed(<?php echo $currency['decimals']; ?>)}</td>
            <td>${currencySymbol} ${item.subtotal.toFixed(<?php echo $currency['decimals']; ?>)}</td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${i})">✕</button></td>
        </tr>`;
    });
    
    document.getElementById('saleTotal').textContent = currencySymbol + ' ' + total.toFixed(<?php echo $currency['decimals']; ?>);
    document.getElementById('submitSaleBtn').textContent = 'Completar Venta (' + currencySymbol + ' ' + total.toFixed(<?php echo $currency['decimals']; ?>) + ')';
}

function prepareSaleItems() {
    if (saleItems.length === 0) { alert('Agregue al menos un producto'); return false; }
    const form = document.querySelector('#saleModal form');
    form.querySelectorAll('.sale-item-input').forEach(el => el.remove());
    saleItems.forEach((item, i) => {
        for (const [k, v] of Object.entries(item)) {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = `items[${i}][${k}]`; input.value = v;
            input.className = 'sale-item-input';
            form.appendChild(input);
        }
    });
    return true;
}

function openSaleModal() { saleItems = []; renderItems(); document.getElementById('saleModal').style.display = 'flex'; }
function closeSaleModal() { document.getElementById('saleModal').style.display = 'none'; }
</script>
