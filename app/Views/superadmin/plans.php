<?php
$layout = 'superadmin';
$title = 'Super Administrador - Planes';
$pageTitle = 'Gestión de Planes';
$userName = $_SESSION['super_admin_name'] ?? 'Super Admin';
$plans = $plans ?? [];
$availableModules = ['dashboard', 'caja', 'ventas', 'inventario', 'clientes', 'proveedores', 'cotizaciones', 'gastos', 'contabilidad', 'nomina', 'reportes'];
$moduleNames = [
    'dashboard' => 'Dashboard',
    'caja' => 'Caja',
    'ventas' => 'Ventas',
    'inventario' => 'Inventario',
    'clientes' => 'Clientes',
    'proveedores' => 'Proveedores',
    'cotizaciones' => 'Cotizaciones',
    'gastos' => 'Gastos',
    'contabilidad' => 'Contabilidad',
    'nomina' => 'Nómina',
    'reportes' => 'Reportes'
];
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="card neumorphic">
    <div class="card-header">
        <h3>Planes de Suscripción</h3>
        <button onclick="document.getElementById('createPlanForm').style.display='flex'" class="btn btn-primary neumorphic-btn">
            Nuevo Plan
        </button>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Descripción</th>
                        <th>Precio Mensual</th>
                        <th>Precio Anual</th>
                        <th>Max Usuarios</th>
                        <th>Max Productos</th>
                        <th>Módulos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plans)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--color-text-secondary);">
                                No hay planes configurados
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($plans as $plan): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($plan['name']); ?></td>
                                <td><?php echo htmlspecialchars($plan['description']); ?></td>
                                <td>$<?php echo number_format($plan['monthly_price'], 2); ?></td>
                                <td>$<?php echo number_format($plan['annual_price'], 2); ?></td>
                                <td><?php echo $plan['max_users']; ?></td>
                                <td><?php echo $plan['max_products']; ?></td>
                                <td>
                                    <?php 
                                    $modules = json_decode($plan['modules'], true);
                                    if (is_array($modules) && !empty($modules)): 
                                        foreach ($modules as $module): 
                                            echo '<span class="badge badge-info">' . ($moduleNames[$module] ?? $module) . '</span> ';
                                        endforeach; 
                                    else: 
                                        echo '-'; 
                                    endif; 
                                    ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $plan['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $plan['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button onclick="editPlan(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name']); ?>', '<?php echo htmlspecialchars($plan['description']); ?>', <?php echo $plan['monthly_price']; ?>, <?php echo $plan['annual_price']; ?>, <?php echo $plan['max_users']; ?>, <?php echo $plan['max_products']; ?>, '<?php echo htmlspecialchars($plan['modules']); ?>', '<?php echo $plan['status']; ?>')" class="btn btn-sm btn-secondary">Editar</button>
                                    <form method="POST" action="<?php echo $viewInstance->route('superadmin/plans'); ?>?action=delete" style="display:inline;" data-ajax="true">
                                        <?php echo \SoftNova\Core\csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo $plan['id']; ?>">
                                        <button type="submit" onclick="return confirm('¿Eliminar este plan?')" class="btn btn-sm btn-danger">Eliminar</button>
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

<div id="createPlanForm" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Nuevo Plan</h3>
            <button onclick="document.getElementById('createPlanForm').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('superadmin/plans'); ?>?action=create" class="settings-form" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="form-group">
                <label>Nombre del Plan *</label>
                <input type="text" name="name" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="description" rows="3" class="neumorphic-input"></textarea>
            </div>
            <div class="form-group">
                <label>Precio Mensual *</label>
                <input type="number" step="0.01" name="monthly_price" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Precio Anual *</label>
                <input type="number" step="0.01" name="annual_price" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Max Usuarios</label>
                <input type="number" name="max_users" value="5" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Max Productos</label>
                <input type="number" name="max_products" value="500" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Módulos</label>
                <?php foreach ($availableModules as $module): ?>
                    <label style="display:block; margin:5px 0;">
                        <input type="checkbox" name="modules[]" value="<?php echo $module; ?>"> <?php echo $moduleNames[$module]; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary neumorphic-btn">Crear Plan</button>
        </form>
    </div>
</div>

<div id="editPlanForm" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Editar Plan</h3>
            <button onclick="document.getElementById('editPlanForm').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('superadmin/plans'); ?>?action=edit" class="settings-form" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="id" id="editPlanId">
            <div class="form-group">
                <label>Nombre del Plan *</label>
                <input type="text" name="name" id="editPlanName" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="description" id="editPlanDescription" rows="3" class="neumorphic-input"></textarea>
            </div>
            <div class="form-group">
                <label>Precio Mensual *</label>
                <input type="number" step="0.01" name="monthly_price" id="editPlanMonthlyPrice" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Precio Anual *</label>
                <input type="number" step="0.01" name="annual_price" id="editPlanAnnualPrice" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Max Usuarios</label>
                <input type="number" name="max_users" id="editPlanMaxUsers" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Max Productos</label>
                <input type="number" name="max_products" id="editPlanMaxProducts" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Módulos</label>
                <?php foreach ($availableModules as $module): ?>
                    <label style="display:block; margin:5px 0;">
                        <input type="checkbox" name="modules[]" id="editModule_<?php echo $module; ?>" value="<?php echo $module; ?>"> <?php echo $moduleNames[$module]; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="form-group">
                <label>Estado</label>
                <select name="status" id="editPlanStatus" class="neumorphic-input">
                    <option value="active">Activo</option>
                    <option value="inactive">Inactivo</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary neumorphic-btn">Actualizar Plan</button>
        </form>
    </div>
</div>

<script>
function editPlan(id, name, description, monthlyPrice, annualPrice, maxUsers, maxProducts, modules, status) {
    document.getElementById('editPlanId').value = id;
    document.getElementById('editPlanName').value = name;
    document.getElementById('editPlanDescription').value = description;
    document.getElementById('editPlanMonthlyPrice').value = monthlyPrice;
    document.getElementById('editPlanAnnualPrice').value = annualPrice;
    document.getElementById('editPlanMaxUsers').value = maxUsers;
    document.getElementById('editPlanMaxProducts').value = maxProducts;
    document.getElementById('editPlanStatus').value = status;
    
    var moduleArray = JSON.parse(modules);
    <?php foreach ($availableModules as $module): ?>
        document.getElementById('editModule_<?php echo $module; ?>').checked = moduleArray.includes('<?php echo $module; ?>');
    <?php endforeach; ?>
    
    document.getElementById('editPlanForm').style.display = 'flex';
}
</script>
