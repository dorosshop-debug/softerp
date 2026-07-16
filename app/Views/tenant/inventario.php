<?php
$layout = 'tenant';
$title = 'Inventario - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Gestión de Inventario';
$tenantName = $tenantName ?? 'Mi Empresa'; $userName = $userName ?? 'Usuario';
$products = $products ?? []; $categories = $categories ?? []; $lowStock = $lowStock ?? 0;
$totalProducts = $totalProducts ?? 0; $totalServices = $totalServices ?? 0;
$currency = $currency ?? ['symbol'=>'$','decimals'=>0]; $typeFilter = $typeFilter ?? '';
function fmtI(float $a, array $c): string { return $c['symbol'].' '.number_format($a, $c['decimals'], $c['decimal']??',', $c['thousands']??'.'); }
?>
<?php echo flashMessage(); ?>

<div class="stats-grid">
    <div class="stat-card neumorphic"><h4>Productos</h4><div class="stat-value"><?php echo $totalProducts; ?></div></div>
    <div class="stat-card neumorphic"><h4>Servicios</h4><div class="stat-value" style="color:#3B82F6;"><?php echo $totalServices; ?></div></div>
    <div class="stat-card neumorphic"><h4>Stock Bajo</h4><div class="stat-value" style="color:<?php echo $lowStock>0?'#DC2626':'#10B981';?>;"><?php echo $lowStock; ?></div></div>
    <div class="stat-card neumorphic"><h4>Total Ítems</h4><div class="stat-value"><?php echo $totalProducts+$totalServices; ?></div></div>
</div>

<div class="card neumorphic">
    <div class="card-header">
        <h3>Lista de Ítems</h3>
        <div style="display:flex;gap:10px;">
            <select onchange="window.location='<?php echo $viewInstance->route('app/inventario'); ?>?type='+this.value" class="form-control" style="width:auto;">
                <option value="">Todos</option>
                <option value="product" <?php echo $typeFilter==='product'?'selected':''; ?>>📦 Productos</option>
                <option value="service" <?php echo $typeFilter==='service'?'selected':''; ?>>🔧 Servicios</option>
            </select>
            <button onclick="openModal()" class="btn btn-primary neumorphic-btn">+ Nuevo</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-container"><table>
            <thead><tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>P. Venta</th><th>Stock</th><th>Última Venta</th><th>Días en Inv.</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--color-text-secondary);">No hay ítems</td></tr>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <?php $isService = ($p['product_type']??'product') === 'service'; ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                        <td><span class="badge <?php echo $isService?'badge-info':'badge-success'; ?>"><?php echo $isService?'Servicio':'Producto'; ?></span></td>
                        <td><?php echo fmtI($p['sale_price'], $currency); ?></td>
                        <td><?php if($isService): ?><span style="color:var(--color-text-secondary);">N/A</span><?php else: ?><span class="badge <?php echo $p['stock']<=$p['min_stock']?'badge-danger':'badge-success'; ?>"><?php echo $p['stock']; ?></span><?php endif; ?></td>
                        <td style="font-size:12px;"><?php echo $p['last_sale_date'] ? date('d/m/Y', strtotime($p['last_sale_date'])) : '<span style="color:var(--color-text-secondary);">Nunca</span>'; ?></td>
                        <td style="font-size:12px;"><?php echo $isService ? '-' : ($p['days_in_inventory']??0).' días'; ?></td>
                        <td class="table-actions">
                            <button onclick="viewDetail(<?php echo $p['id']; ?>)" class="btn btn-sm btn-info" title="Trazabilidad">📊</button>
                            <button onclick='openModal(<?php echo htmlspecialchars(json_encode($p)); ?>)' class="btn btn-sm btn-secondary">✏️</button>
                            <?php if (!$isService): ?>
                            <button onclick='openStockModal(<?php echo $p['id']; ?>, "<?php echo htmlspecialchars($p['name']); ?>")' class="btn btn-sm btn-success" title="Agregar stock">📥</button>
                            <?php endif; ?>
                            <form method="POST" action="<?php echo $viewInstance->route('app/inventario'); ?>?action=delete" style="display:inline;" data-ajax="true">
                                <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <button type="submit" onclick="return confirm('¿Eliminar?')" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table></div>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div id="productModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:600px;max-height:85vh;overflow-y:auto;">
        <div class="modal-header"><h3 id="modalTitle">Nuevo Ítem</h3><button onclick="closeModal()" class="modal-close">&times;</button></div>
        <form method="POST" id="productForm" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="id" id="prodId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label>Tipo *</label><select name="product_type" id="prodType" class="form-control" onchange="onTypeChange()"><option value="product">📦 Producto</option><option value="service">🔧 Servicio</option></select></div>
                <div class="form-group"><label>Código</label><input type="text" name="code" id="prodCode" class="form-control" placeholder="Auto-generado"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Nombre *</label><input type="text" name="name" id="prodName" required class="form-control"></div>
                <div class="form-group"><label>Categoría</label><select name="category_id" id="prodCat" class="form-control"><option value="">Sin categoría</option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Unidad</label><input type="text" name="unit" id="prodUnit" class="form-control" placeholder="UNIDAD"></div>
                <div class="form-group"><label>Precio Compra</label><input type="number" step="0.01" name="purchase_price" id="prodPcompra" class="form-control" placeholder="0"></div>
                <div class="form-group"><label>Precio Venta *</label><input type="number" step="0.01" name="sale_price" id="prodPventa" required class="form-control" placeholder="0"></div>
                <div class="form-group" id="stockGroup"><label>Stock Inicial</label><input type="number" name="stock" id="prodStock" class="form-control" placeholder="0"></div>
                <div class="form-group" id="minStockGroup"><label>Stock Mínimo</label><input type="number" name="min_stock" id="prodMinStock" class="form-control" placeholder="5"></div>
                <div class="form-group" id="statusGroup"><label>Estado</label><select name="status" id="prodStatus" class="form-control"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></div>
            </div>
            <div class="form-group"><label>Descripción</label><textarea name="description" id="prodDesc" rows="2" class="form-control"></textarea></div>
            <div class="modal-footer" style="margin-top:15px;"><button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button><button type="submit" class="btn btn-primary" id="submitBtn">Crear</button></div>
        </form>
    </div>
</div>

<!-- Modal Agregar Stock -->
<div id="stockModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:400px;">
        <div class="modal-header"><h3>Agregar Stock</h3><button onclick="document.getElementById('stockModal').style.display='none'" class="modal-close">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/inventario'); ?>?action=add_stock" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="id" id="stockProdId">
            <p id="stockProdName" style="margin-bottom:10px;font-weight:600;"></p>
            <div class="form-group"><label>Cantidad a agregar *</label><input type="number" name="quantity" class="form-control" min="1" required placeholder="1"></div>
            <div class="form-group"><label>Notas</label><input type="text" name="notes" class="form-control" placeholder="Ej: Compra a proveedor"></div>
            <div class="modal-footer" style="margin-top:15px;"><button type="button" class="btn btn-secondary" onclick="document.getElementById('stockModal').style.display='none'">Cancelar</button><button type="submit" class="btn btn-success">Agregar Stock</button></div>
        </form>
    </div>
</div>

<!-- Modal Trazabilidad -->
<div id="detailModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:700px;max-height:85vh;overflow-y:auto;">
        <div class="modal-header"><h3>Trazabilidad del Producto</h3><button onclick="document.getElementById('detailModal').style.display='none'" class="modal-close">&times;</button></div>
        <div class="modal-body" id="detailContent"></div>
    </div>
</div>

<script>
function onTypeChange(){
    const isSvc = document.getElementById('prodType').value === 'service';
    ['stockGroup','minStockGroup'].forEach(id=>document.getElementById(id).style.display=isSvc?'none':'');
}
function openModal(data){
    if(data){
        document.getElementById('modalTitle').textContent='Editar Ítem';
        document.getElementById('productForm').action='<?php echo $viewInstance->route('app/inventario'); ?>?action=edit';
        document.getElementById('submitBtn').textContent='Guardar';
        document.getElementById('statusGroup').style.display='';
        document.getElementById('prodId').value=data.id; document.getElementById('prodType').value=data.product_type||'product';
        document.getElementById('prodCode').value=data.code||''; document.getElementById('prodName').value=data.name||'';
        document.getElementById('prodCat').value=data.category_id||''; document.getElementById('prodUnit').value=data.unit||'';
        document.getElementById('prodPcompra').value=data.purchase_price||0; document.getElementById('prodPventa').value=data.sale_price||0;
        document.getElementById('prodStock').value=data.stock||0; document.getElementById('prodMinStock').value=data.min_stock||5;
        document.getElementById('prodStatus').value=data.status||'active'; document.getElementById('prodDesc').value=data.description||'';
        onTypeChange();
    }else{
        document.getElementById('modalTitle').textContent='Nuevo Ítem';
        document.getElementById('productForm').action='<?php echo $viewInstance->route('app/inventario'); ?>?action=create';
        document.getElementById('submitBtn').textContent='Crear';
        document.getElementById('statusGroup').style.display='none';
        ['prodId','prodCode','prodName','prodUnit','prodDesc'].forEach(id=>document.getElementById(id).value='');
        document.getElementById('prodType').value='product'; document.getElementById('prodCat').value='';
        document.getElementById('prodPcompra').value=''; document.getElementById('prodPventa').value='';
        document.getElementById('prodStock').value='0'; document.getElementById('prodMinStock').value='5';
        onTypeChange();
    }
    document.getElementById('productModal').style.display='flex';
}
function closeModal(){document.getElementById('productModal').style.display='none';}
function openStockModal(id, name){
    document.getElementById('stockProdId').value=id;
    document.getElementById('stockProdName').textContent='📦 '+name;
    document.getElementById('stockModal').style.display='flex';
}

function viewDetail(id){
    fetch('<?php echo $viewInstance->route('app/inventario'); ?>?action=detail&id='+id,{headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(r=>r.json()).then(d=>{
        if(d.error) return alert(d.error);
        let p=d.product, m=d.movements||[], s=d.lastSales||[];
        let isSvc = p.product_type==='service';
        let html=`<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:15px;">
            <div><strong>Nombre:</strong> ${esc(p.name)}</div><div><strong>Tipo:</strong> ${isSvc?'🔧 Servicio':'📦 Producto'}</div>
            <div><strong>Código:</strong> ${esc(p.code||'-')}</div><div><strong>Categoría:</strong> ${esc(p.category_name||'-')}</div>
            <div><strong>P. Venta:</strong> $${parseFloat(p.sale_price).toFixed(0)}</div><div><strong>Stock Actual:</strong> ${isSvc?'N/A':p.stock}</div>
            <div><strong>Creado:</strong> ${p.created_at?.substr(0,10)}</div><div><strong>Última Venta:</strong> ${p.last_sale_date?.substr(0,10)||'Nunca'}</div>
            <div><strong>Días en inventario:</strong> ${isSvc?'N/A':(p.days_in_inventory||0)+' días'}</div><div><strong>Días desde última venta:</strong> ${p.last_sale_date?(p.days_since_last_sale||0)+' días':'N/A'}</div>
        </div>`;
        
        html+='<h4>📊 Movimientos de Stock ('+m.length+')</h4>';
        if(m.length===0) html+='<p style="color:var(--color-text-secondary);text-align:center;">Sin movimientos</p>';
        else{html+='<div class="table-container"><table><thead><tr><th>Fecha</th><th>Tipo</th><th>Cant</th><th>Ref</th><th>Notas</th><th>Usuario</th></tr></thead><tbody>';
        m.forEach(x=>{let t=x.type==='in'?'Entrada':x.type==='out'?'Salida':'Ajuste';html+=`<tr><td>${x.created_at?.substr(0,16)}</td><td><span class="badge ${x.type==='in'?'badge-success':x.type==='out'?'badge-danger':'badge-warning'}">${t}</span></td><td>${x.quantity}</td><td>${esc(x.reference_type||'-')}</td><td>${esc(x.notes||'-')}</td><td>${esc(x.user_name||'-')}</td></tr>`});
        html+='</tbody></table></div>';}
        
        html+='<h4 style="margin-top:15px;">🛒 Últimas Ventas</h4>';
        if(s.length===0) html+='<p style="color:var(--color-text-secondary);text-align:center;">Sin ventas registradas</p>';
        else{html+='<div class="table-container"><table><thead><tr><th>Factura</th><th>Fecha</th><th>Cant</th><th>P.Unit</th></tr></thead><tbody>';
        s.forEach(x=>{html+=`<tr><td>${esc(x.invoice_number)}</td><td>${x.sale_date?.substr(0,16)}</td><td>${x.quantity}</td><td>$${parseFloat(x.unit_price).toFixed(0)}</td></tr>`});
        html+='</tbody></table></div>';}
        
        document.getElementById('detailContent').innerHTML=html;
        document.getElementById('detailModal').style.display='flex';
    });
}
function esc(s){return(s||'').replace(/</g,'<').replace(/>/g,'>');}
</script>
