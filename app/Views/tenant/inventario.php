<?php
$layout = 'tenant';
$title = 'Inventario - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Gestión de Inventario';
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$products = $products ?? [];
$categories = $categories ?? [];
$lowStock = $lowStock ?? 0;
$totalProducts = $totalProducts ?? 0;
$currency = $currency ?? ['symbol'=>'$','decimals'=>0];
function fmtInv(float $a, array $c): string { return $c['symbol'].' '.number_format($a, $c['decimals'], $c['decimal']??',', $c['thousands']??'.'); }
?>
<?php echo flashMessage(); ?>

<div class="stats-grid">
    <div class="stat-card neumorphic"><h4>Total Productos</h4><div class="stat-value"><?php echo $totalProducts; ?></div></div>
    <div class="stat-card neumorphic"><h4>Stock Bajo</h4><div class="stat-value" style="color:<?php echo $lowStock>0?'#DC2626':'#10B981';?>;"><?php echo $lowStock; ?></div></div>
</div>

<div class="card neumorphic">
    <div class="card-header"><h3>Productos</h3><button onclick="openModal()" class="btn btn-primary neumorphic-btn">+ Nuevo Producto</button></div>
    <div class="card-body">
        <div class="table-container"><table>
            <thead><tr><th>ID</th><th>Código</th><th>Nombre</th><th>Categoría</th><th>P. Compra</th><th>P. Venta</th><th>Stock</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--color-text-secondary);">No hay productos</td></tr>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?php echo $p['id']; ?></td>
                        <td><?php echo htmlspecialchars($p['code']??'-'); ?></td>
                        <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['category_name']??'-'); ?></td>
                        <td><?php echo fmtInv($p['purchase_price'], $currency); ?></td>
                        <td><?php echo fmtInv($p['sale_price'], $currency); ?></td>
                        <td><span class="badge <?php echo $p['stock']<=$p['min_stock']?'badge-danger':'badge-success'; ?>"><?php echo $p['stock']; ?></span></td>
                        <td><span class="badge <?php echo ($p['status']??'active')==='active'?'badge-success':'badge-danger'; ?>"><?php echo ($p['status']??'active')==='active'?'Activo':'Inactivo'; ?></span></td>
                        <td class="table-actions">
                            <button onclick='openModal(<?php echo htmlspecialchars(json_encode($p)); ?>)' class="btn btn-sm btn-secondary">✏️</button>
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

<div id="productModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:600px;max-height:85vh;overflow-y:auto;">
        <div class="modal-header"><h3 id="modalTitle">Nuevo Producto</h3><button onclick="closeModal()" class="modal-close">&times;</button></div>
        <form method="POST" id="productForm" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="id" id="prodId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group"><label>Código</label><input type="text" name="code" id="prodCode" class="form-control" placeholder="Auto-generado"></div>
                <div class="form-group"><label>Nombre *</label><input type="text" name="name" id="prodName" required class="form-control"></div>
                <div class="form-group"><label>Categoría</label><select name="category_id" id="prodCat" class="form-control"><option value="">Sin categoría</option><?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Unidad</label><input type="text" name="unit" id="prodUnit" class="form-control" placeholder="UNIDAD"></div>
                <div class="form-group"><label>Precio Compra</label><input type="number" step="0.01" name="purchase_price" id="prodPcompra" class="form-control" placeholder="0"></div>
                <div class="form-group"><label>Precio Venta *</label><input type="number" step="0.01" name="sale_price" id="prodPventa" required class="form-control" placeholder="0"></div>
                <div class="form-group"><label>Stock Inicial</label><input type="number" name="stock" id="prodStock" class="form-control" placeholder="0"></div>
                <div class="form-group"><label>Stock Mínimo</label><input type="number" name="min_stock" id="prodMinStock" class="form-control" placeholder="5"></div>
                <div class="form-group" id="statusGroup"><label>Estado</label><select name="status" id="prodStatus" class="form-control"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></div>
            </div>
            <div class="form-group"><label>Descripción</label><textarea name="description" id="prodDesc" rows="2" class="form-control"></textarea></div>
            <div class="modal-footer" style="margin-top:15px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Crear Producto</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(data) {
    if (data) {
        document.getElementById('modalTitle').textContent = 'Editar Producto';
        document.getElementById('productForm').action = '<?php echo $viewInstance->route('app/inventario'); ?>?action=edit';
        document.getElementById('submitBtn').textContent = 'Guardar';
        document.getElementById('statusGroup').style.display = '';
        document.getElementById('prodId').value = data.id;
        document.getElementById('prodCode').value = data.code||'';
        document.getElementById('prodName').value = data.name||'';
        document.getElementById('prodCat').value = data.category_id||'';
        document.getElementById('prodUnit').value = data.unit||'';
        document.getElementById('prodPcompra').value = data.purchase_price||0;
        document.getElementById('prodPventa').value = data.sale_price||0;
        document.getElementById('prodStock').value = data.stock||0;
        document.getElementById('prodMinStock').value = data.min_stock||5;
        document.getElementById('prodStatus').value = data.status||'active';
        document.getElementById('prodDesc').value = data.description||'';
    } else {
        document.getElementById('modalTitle').textContent = 'Nuevo Producto';
        document.getElementById('productForm').action = '<?php echo $viewInstance->route('app/inventario'); ?>?action=create';
        document.getElementById('submitBtn').textContent = 'Crear Producto';
        document.getElementById('statusGroup').style.display = 'none';
        ['prodId','prodCode','prodName','prodUnit','prodDesc'].forEach(id=>document.getElementById(id).value='');
        document.getElementById('prodCat').value = '';
        document.getElementById('prodPcompra').value = '';
        document.getElementById('prodPventa').value = '';
        document.getElementById('prodStock').value = '0';
        document.getElementById('prodMinStock').value = '5';
    }
    document.getElementById('productModal').style.display = 'flex';
}
function closeModal() { document.getElementById('productModal').style.display = 'none'; }
</script>
