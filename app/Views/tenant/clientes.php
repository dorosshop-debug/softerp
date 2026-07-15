<?php
$layout = 'tenant';
$title = 'Clientes - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Gestión de Clientes';
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$customers = $customers ?? [];
?>
<?php echo flashMessage(); ?>

<div class="card neumorphic">
    <div class="card-header"><h3>Lista de Clientes</h3><button onclick="openModal()" class="btn btn-primary neumorphic-btn">+ Nuevo Cliente</button></div>
    <div class="card-body">
        <div class="table-container"><table>
            <thead><tr><th>ID</th><th>Nombre</th><th>Documento</th><th>Email</th><th>Teléfono</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php if (empty($customers)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--color-text-secondary);">No hay clientes</td></tr>
            <?php else: ?>
                <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars(($c['document_type']??'CC').' '.($c['document_number']??'')); ?></td>
                        <td><?php echo htmlspecialchars($c['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($c['phone'] ?? '-'); ?></td>
                        <td><span class="badge <?php echo ($c['status']??'active')==='active'?'badge-success':'badge-danger'; ?>"><?php echo ($c['status']??'active')==='active'?'Activo':'Inactivo'; ?></span></td>
                        <td class="table-actions">
                            <button onclick='openModal(<?php echo htmlspecialchars(json_encode($c)); ?>)' class="btn btn-sm btn-secondary">✏️</button>
                            <form method="POST" action="<?php echo $viewInstance->route('app/clientes'); ?>?action=delete" style="display:inline;" data-ajax="true">
                                <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="id" value="<?php echo $c['id']; ?>">
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

<div id="customerModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:550px;max-height:85vh;overflow-y:auto;">
        <div class="modal-header"><h3 id="modalTitle">Nuevo Cliente</h3><button onclick="closeModal()" class="modal-close">&times;</button></div>
        <form method="POST" id="customerForm" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="id" id="custId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                <div class="form-group" style="grid-column:1/-1;"><label>Nombre *</label><input type="text" name="name" id="custName" required class="form-control"></div>
                <div class="form-group"><label>Tipo Doc.</label><select name="document_type" id="custDocType" class="form-control"><option value="CC">C.C</option><option value="CE">C.E</option><option value="NIT">NIT</option><option value="PPT">PPT</option><option value="OTROS">OTROS</option></select></div>
                <div class="form-group"><label>N° Documento</label><input type="text" name="document_number" id="custDocNum" class="form-control"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="custEmail" class="form-control"></div>
                <div class="form-group"><label>Teléfono</label><input type="text" name="phone" id="custPhone" class="form-control"></div>
            </div>
            <div class="form-group"><label>Dirección</label><textarea name="address" id="custAddress" rows="2" class="form-control"></textarea></div>
            <div class="modal-footer" style="margin-top:15px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Crear Cliente</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(data) {
    if (data) {
        document.getElementById('modalTitle').textContent = 'Editar Cliente';
        document.getElementById('customerForm').action = '<?php echo $viewInstance->route('app/clientes'); ?>?action=edit';
        document.getElementById('submitBtn').textContent = 'Guardar';
        document.getElementById('custId').value = data.id;
        document.getElementById('custName').value = data.name||'';
        document.getElementById('custDocType').value = data.document_type||'CC';
        document.getElementById('custDocNum').value = data.document_number||'';
        document.getElementById('custEmail').value = data.email||'';
        document.getElementById('custPhone').value = data.phone||'';
        document.getElementById('custAddress').value = data.address||'';
    } else {
        document.getElementById('modalTitle').textContent = 'Nuevo Cliente';
        document.getElementById('customerForm').action = '<?php echo $viewInstance->route('app/clientes'); ?>?action=create';
        document.getElementById('submitBtn').textContent = 'Crear Cliente';
        ['custId','custName','custDocNum','custEmail','custPhone','custAddress'].forEach(id=>document.getElementById(id).value='');
        document.getElementById('custDocType').value = 'CC';
    }
    document.getElementById('customerModal').style.display = 'flex';
}
function closeModal() { document.getElementById('customerModal').style.display = 'none'; }
</script>
