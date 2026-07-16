<?php
$layout = 'tenant';
$title = 'Ventas - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Punto de Venta';
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$sales = $sales ?? []; $todayTotal = $todayTotal ?? 0; $products = $products ?? []; $customers = $customers ?? [];
$currency = $currency ?? ['symbol'=>'$','decimals'=>0];
function fmtV(float $a, array $c): string { return $c['symbol'].' '.number_format($a, $c['decimals'], $c['decimal']??',', $c['thousands']??'.'); }
?>
<?php echo flashMessage(); ?>

<div class="stats-grid">
    <div class="stat-card neumorphic"><h4>Ventas Hoy</h4><div class="stat-value"><?php echo count($sales); ?></div></div>
    <div class="stat-card neumorphic"><h4>Total Hoy</h4><div class="stat-value" style="color:#10B981;"><?php echo fmtV($todayTotal, $currency); ?></div></div>
</div>

<div style="display:flex;gap:15px;margin-bottom:20px;">
    <button onclick="openSaleModal()" class="btn btn-primary neumorphic-btn" style="font-size:16px;padding:12px 30px;">🛒 Nueva Venta</button>
</div>

<div class="card neumorphic">
    <div class="card-header"><h3>📋 Últimas Ventas</h3></div>
    <div class="card-body">
        <?php if (empty($sales)): ?>
            <p style="text-align:center;color:var(--color-text-secondary);padding:20px;">No hay ventas registradas</p>
        <?php else: ?>
            <div class="table-container"><table>
                <thead><tr><th>Factura</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Pago</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($sale['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'General'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($sale['sale_date'])); ?></td>
                        <td style="font-weight:600;"><?php echo fmtV($sale['total'], $currency); ?></td>
                        <td><span class="badge badge-info"><?php echo $sale['payment_method']==='cash'?'Efectivo':($sale['payment_method']==='card'?'Tarjeta':ucfirst($sale['payment_method'])); ?></span></td>
                        <td><span class="badge <?php echo $sale['status']==='completed'?'badge-success':($sale['status']==='cancelled'?'badge-danger':'badge-warning'); ?>"><?php echo $sale['status']==='completed'?'Completada':($sale['status']==='cancelled'?'Cancelada':'Pendiente'); ?></span></td>
                        <td class="table-actions">
                            <button onclick="viewDetail(<?php echo $sale['id']; ?>)" class="btn btn-sm btn-info" title="Ver detalle">👁️</button>
                            <button onclick="printInvoice(<?php echo $sale['id']; ?>)" class="btn btn-sm btn-secondary" title="Imprimir">🖨️</button>
                            <?php if ($sale['status'] === 'completed'): ?>
                            <form method="POST" action="<?php echo $viewInstance->route('app/ventas'); ?>?action=cancel" style="display:inline;" data-ajax="true">
                                <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="id" value="<?php echo $sale['id']; ?>">
                                <button type="submit" onclick="return confirm('¿Cancelar esta venta? Se devolverá el stock.')" class="btn btn-sm btn-danger" title="Cancelar/Devolución">↩️</button>
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
                <div class="form-group"><label>Cliente</label><select name="customer_id" class="form-control"><option value="">Consumidor Final</option><?php foreach($customers as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['first_name']??$c['name']); ?> <?php echo htmlspecialchars($c['last_name']??''); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Método de Pago</label><select name="payment_method" class="form-control"><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option><option value="other">Otro</option></select></div>
            </div>
            <div class="form-group"><label>Notas</label><input type="text" name="notes" class="form-control" placeholder="Observaciones..."></div>
            <div class="form-group"><label>Productos</label>
                <div style="display:flex;gap:8px;margin-bottom:8px;">
                    <select id="productSelect" class="form-control" style="flex:1;"><option value="">Seleccionar producto...</option><?php foreach($products as $p): ?><option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['sale_price']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>" data-stock="<?php echo $p['stock']; ?>"><?php echo htmlspecialchars($p['name']); ?> (<?php echo fmtV($p['sale_price'],$currency); ?>)</option><?php endforeach; ?></select>
                    <input type="number" id="productQty" value="1" min="1" class="form-control" style="width:80px;" placeholder="Cant">
                    <button type="button" class="btn btn-primary" onclick="addProduct()">+</button>
                </div>
                <table id="itemsTable" style="width:100%;margin-top:10px;display:none;"><thead><tr><th>Producto</th><th>Cant</th><th>Precio</th><th>Subtotal</th><th></th></tr></thead><tbody></tbody><tfoot><tr><td colspan="3" style="text-align:right;font-weight:600;">Total:</td><td id="saleTotal" style="font-weight:700;color:#10B981;">0</td><td></td></tr></tfoot></table>
            </div>
            <div class="modal-footer" style="margin-top:15px;"><button type="button" class="btn btn-secondary" onclick="closeSaleModal()">Cancelar</button><button type="submit" class="btn btn-primary" id="submitSaleBtn">Completar Venta</button></div>
        </form>
    </div>
</div>

<!-- Modal Detalle Venta -->
<div id="saleDetailModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:650px;max-height:85vh;overflow-y:auto;">
        <div class="modal-header"><h3>Detalle de Venta</h3><button onclick="document.getElementById('saleDetailModal').style.display='none'" class="modal-close">&times;</button></div>
        <div class="modal-body" id="saleDetailContent"></div>
    </div>
</div>

<script>
let saleItems = []; let curSym = '<?php echo $currency['symbol']; ?>'; let curDec = <?php echo $currency['decimals']; ?>;

function addProduct() {
    const sel = document.getElementById('productSelect'), qty = parseInt(document.getElementById('productQty').value)||1;
    if(!sel.value) return alert('Seleccione un producto');
    const opt = sel.options[sel.selectedIndex], id=opt.value, name=opt.dataset.name, price=parseFloat(opt.dataset.price), stock=parseInt(opt.dataset.stock);
    if(qty>stock) return alert('Stock insuficiente: '+stock);
    const ex=saleItems.find(i=>i.product_id==id); if(ex){ex.quantity+=qty;ex.subtotal=ex.quantity*price;} else{saleItems.push({product_id:id,product_name:name,quantity:qty,unit_price:price,subtotal:qty*price});}
    document.getElementById('productQty').value=1; renderItems();
}
function removeItem(idx){saleItems.splice(idx,1);renderItems();}
function renderItems(){
    const tb=document.querySelector('#itemsTable tbody'), tbl=document.getElementById('itemsTable'); let t=0; tb.innerHTML='';
    if(saleItems.length===0){tbl.style.display='none';return;} tbl.style.display='';
    saleItems.forEach((it,i)=>{t+=it.subtotal; tb.innerHTML+=`<tr><td>${esc(it.product_name)}</td><td>${it.quantity}</td><td>${curSym} ${it.unit_price.toFixed(curDec)}</td><td>${curSym} ${it.subtotal.toFixed(curDec)}</td><td><button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${i})">✕</button></td></tr>`});
    document.getElementById('saleTotal').textContent=curSym+' '+t.toFixed(curDec);
    document.getElementById('submitSaleBtn').textContent='Completar Venta ('+curSym+' '+t.toFixed(curDec)+')';
}
function prepareSaleItems(){
    if(saleItems.length===0){alert('Agregue al menos un producto');return false;}
    const f=document.querySelector('#saleModal form'); f.querySelectorAll('.sale-item-input').forEach(e=>e.remove());
    saleItems.forEach((it,i)=>{for(const[k,v]of Object.entries(it)){const inp=document.createElement('input');inp.type='hidden';inp.name=`items[${i}][${k}]`;inp.value=v;inp.className='sale-item-input';f.appendChild(inp);}}); return true;
}
function openSaleModal(){saleItems=[];renderItems();document.getElementById('saleModal').style.display='flex';}
function closeSaleModal(){document.getElementById('saleModal').style.display='none';}

function viewDetail(id){
    fetch('<?php echo $viewInstance->route('app/ventas'); ?>?action=detail&id='+id,{headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(r=>r.json()).then(d=>{
        if(d.error) return alert(d.error);
        let s=d.sale, items=d.items||[], totalItems=items.reduce((a,i)=>a+parseInt(i.quantity),0);
        let html=`<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:15px;">
            <div><strong>Factura:</strong> ${esc(s.invoice_number)}</div><div><strong>Fecha:</strong> ${s.sale_date?.substr(0,16)}</div>
            <div><strong>Cliente:</strong> ${esc(s.customer_name||s.first_name||'General')} ${esc(s.last_name||'')}</div><div><strong>Vendedor:</strong> ${esc(s.user_name||'-')}</div>
            <div><strong>Método:</strong> ${esc(s.payment_method)}</div><div><strong>Estado:</strong> ${s.status==='completed'?'Completada':s.status==='cancelled'?'Cancelada':'Pendiente'}</div>
            <div><strong>Subtotal:</strong> ${curSym} ${parseFloat(s.subtotal).toFixed(curDec)}</div><div><strong>Impuesto:</strong> ${curSym} ${parseFloat(s.tax).toFixed(curDec)}</div>
            <div><strong>Total:</strong> <span style="color:#10B981;font-size:18px;">${curSym} ${parseFloat(s.total).toFixed(curDec)}</span></div><div><strong>Notas:</strong> ${esc(s.notes||'-')}</div>
        </div>`;
        html+='<h4>Productos ('+totalItems+' items)</h4><div class="table-container"><table><thead><tr><th>Producto</th><th>Cant</th><th>P.Unit</th><th>Subtotal</th></tr></thead><tbody>';
        items.forEach(i=>{html+=`<tr><td>${esc(i.product_name)}</td><td>${i.quantity}</td><td>${curSym} ${parseFloat(i.unit_price).toFixed(curDec)}</td><td>${curSym} ${parseFloat(i.subtotal).toFixed(curDec)}</td></tr>`});
        html+='</tbody></table></div>';
        document.getElementById('saleDetailContent').innerHTML=html;
        document.getElementById('saleDetailModal').style.display='flex';
    });
}

function printInvoice(id){
    viewDetail(id);
    setTimeout(()=>{window.print();}, 500);
}
function esc(s){return(s||'').replace(/</g,'<').replace(/>/g,'>');}
</script>
