<?php
$layout = 'superadmin';
$title = 'Super Administrador - Planes';
$pageTitle = 'Gestión de Planes';
$userName = $_SESSION['super_admin_name'] ?? 'Super Admin';
$plans = $plans ?? [];
$availableModules = ['dashboard', 'caja', 'ventas', 'inventario', 'clientes', 'proveedores', 'cotizaciones', 'contabilidad', 'nomina', 'reportes'];
$moduleNames = [
    'dashboard' => 'Dashboard', 'caja' => 'Caja', 'ventas' => 'Ventas',
    'inventario' => 'Inventario', 'clientes' => 'Clientes', 'proveedores' => 'Proveedores',
    'cotizaciones' => 'Cotizaciones', 'contabilidad' => 'Contabilidad',
    'nomina' => 'Nómina', 'reportes' => 'Reportes'
];
$moduleIcons = [
    'dashboard' => '📊', 'caja' => '💰', 'ventas' => '🛒', 'inventario' => '📦',
    'clientes' => '👥', 'proveedores' => '🚚', 'cotizaciones' => '📝',
    'contabilidad' => '🧮', 'nomina' => '💵', 'reportes' => '📋'
];
?>

<?php echo flashMessage(); ?>

<div class="card neumorphic">
    <div class="card-header">
        <h3>Planes de Suscripción</h3>
        <button onclick="openCreateModal()" class="btn btn-primary neumorphic-btn">+ Nuevo Plan</button>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Mensual</th>
                        <th>Anual</th>
                        <th>Usuarios</th>
                        <th>Módulos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plans)): ?>
                        <tr><td colspan="8" style="text-align:center;color:var(--color-text-secondary);">No hay planes</td></tr>
                    <?php else: ?>
                        <?php foreach ($plans as $plan): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($plan['name']); ?></strong></td>
                                <td>$<?php echo number_format($plan['monthly_price'], 2); ?></td>
                                <td>$<?php echo number_format($plan['annual_price'], 2); ?></td>
                                <td><?php echo $plan['max_users']; ?></td>
                                <td>
                                    <?php $modules = json_decode($plan['modules'], true); ?>
                                    <?php if (is_array($modules)): ?>
                                        <?php foreach (array_slice($modules, 0, 4) as $m): ?>
                                            <span class="badge badge-info"><?php echo $moduleNames[$m] ?? $m; ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($modules) > 4): ?>
                                            <span class="badge badge-info">+<?php echo count($modules) - 4; ?></span>
                                        <?php endif; ?>
                                    <?php else: echo '-'; endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $plan['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $plan['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="table-actions">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($plan)); ?>)" class="btn btn-sm btn-secondary">✏️</button>
                                    <form method="POST" action="<?php echo $viewInstance->route('superadmin/plans'); ?>?action=delete" style="display:inline;" data-ajax="true">
                                        <?php echo \SoftNova\Core\csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo $plan['id']; ?>">
                                        <button type="submit" onclick="return confirm('¿Eliminar este plan?')" class="btn btn-sm btn-danger">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Crear/Editar Plan -->
<div id="planModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width: 580px; max-height: 85vh; overflow-y: auto;">
        <div class="modal-header">
            <h3 id="planModalTitle">📦 Nuevo Plan</h3>
            <button onclick="closePlanModal()" class="modal-close">&times;</button>
        </div>
        <form method="POST" id="planForm" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="id" id="planId">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Nombre del Plan *</label>
                    <input type="text" name="name" id="planName" required class="form-control" placeholder="Ej: Plan Básico, Plan Premium...">
                </div>
                
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="description" id="planDescription" rows="2" class="form-control" placeholder="Describe las características de este plan..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>💵 Precio Mensual *</label>
                        <input type="number" step="0.01" name="monthly_price" id="planMonthly" required class="form-control" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>📅 Precio Anual *</label>
                        <input type="number" step="0.01" name="annual_price" id="planAnnual" required class="form-control" placeholder="0.00">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>👥 Máx. Usuarios</label>
                        <input type="number" name="max_users" id="planMaxUsers" value="5" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>📦 Máx. Productos</label>
                        <input type="number" name="max_products" id="planMaxProducts" value="500" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="status" id="planStatus" class="form-control">
                            <option value="active">✅ Activo</option>
                            <option value="inactive">❌ Inactivo</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>🧩 Módulos incluidos</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; background: var(--bg-input); border: 1px solid var(--color-border); border-radius: 8px; padding: 12px;">
                        <?php foreach ($availableModules as $module): ?>
                            <label style="display: flex; align-items: center; gap: 8px; padding: 4px 0; cursor: pointer; font-size: 13px;">
                                <input type="checkbox" name="modules[]" value="<?php echo $module; ?>" class="plan-module-cb" style="accent-color: var(--color-primary);">
                                <?php echo $moduleIcons[$module]; ?> <?php echo $moduleNames[$module]; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePlanModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary neumorphic-btn" id="planSubmitBtn">💾 Crear Plan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('planModalTitle').textContent = '📦 Nuevo Plan';
    document.getElementById('planForm').action = '<?php echo $viewInstance->route('superadmin/plans'); ?>?action=create';
    document.getElementById('planSubmitBtn').textContent = '💾 Crear Plan';
    document.getElementById('planId').value = '';
    document.getElementById('planName').value = '';
    document.getElementById('planDescription').value = '';
    document.getElementById('planMonthly').value = '';
    document.getElementById('planAnnual').value = '';
    document.getElementById('planMaxUsers').value = '5';
    document.getElementById('planMaxProducts').value = '500';
    document.getElementById('planStatus').value = 'active';
    document.querySelectorAll('.plan-module-cb').forEach(cb => cb.checked = false);
    document.getElementById('planModal').style.display = 'flex';
}

function openEditModal(plan) {
    document.getElementById('planModalTitle').textContent = '✏️ Editar Plan';
    document.getElementById('planForm').action = '<?php echo $viewInstance->route('superadmin/plans'); ?>?action=edit';
    document.getElementById('planSubmitBtn').textContent = '💾 Guardar Cambios';
    document.getElementById('planId').value = plan.id;
    document.getElementById('planName').value = plan.name;
    document.getElementById('planDescription').value = plan.description || '';
    document.getElementById('planMonthly').value = plan.monthly_price;
    document.getElementById('planAnnual').value = plan.annual_price;
    document.getElementById('planMaxUsers').value = plan.max_users || 5;
    document.getElementById('planMaxProducts').value = plan.max_products || 500;
    document.getElementById('planStatus').value = plan.status || 'active';
    
    // Marcar módulos
    const activeModules = JSON.parse(plan.modules || '[]');
    document.querySelectorAll('.plan-module-cb').forEach(cb => {
        cb.checked = activeModules.includes(cb.value);
    });
    
    document.getElementById('planModal').style.display = 'flex';
}

function closePlanModal() {
    document.getElementById('planModal').style.display = 'none';
}
</script>
