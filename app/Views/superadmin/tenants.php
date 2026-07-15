<?php
$layout = 'superadmin';
$title = 'Super Administrador - Clientes';
$pageTitle = 'Gestion de Clientes';
$userName = $_SESSION['super_admin_name'] ?? 'Super Admin';
$tenants = $tenants ?? [];
$plans = $plans ?? [];

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
        <h3>Lista de Clientes</h3>
        <button onclick="document.getElementById('createTenantForm').style.display='flex'" class="btn btn-primary neumorphic-btn">
            Nuevo Cliente
        </button>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Empresa</th>
                        <th>Contacto</th>
                        <th>Email</th>
                        <th>Estado</th>
                        <th>Plan</th>
                        <th>Inicio</th>
                        <th>Vencimiento</th>
                        <th>Dias restantes</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tenants)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; color: var(--color-text-secondary);">
                                No hay clientes registrados
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tenants as $tenant): ?>
                            <?php $remaining = daysRemaining($tenant['subscription_end_date']); ?>
                            <tr>
                                <td><?php echo $tenant['id']; ?></td>
                                <td><?php echo htmlspecialchars($tenant['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($tenant['phone'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($tenant['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo statusBadgeClass($tenant['status']); ?>">
                                        <?php echo statusLabel($tenant['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($tenant['plan_name'] ?? '-'); ?></td>
                                <td><?php echo formatDate($tenant['subscription_start_date']); ?></td>
                                <td><?php echo formatDate($tenant['subscription_end_date']); ?></td>
                                <td>
                                    <?php if ($remaining > 0): ?>
                                        <span class="badge badge-success"><?php echo $remaining; ?> dias</span>
                                    <?php elseif ($remaining === 0): ?>
                                        <span class="badge badge-warning">Vence hoy</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Vencido (<?php echo abs($remaining); ?> dias)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <button onclick="viewTenantDetails(<?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['company_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['razon_social'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['documento_tipo'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['documento_numero'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['phone'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['address'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['plan_name'] ?? '-', ENT_QUOTES); ?>', '<?php echo formatDate($tenant['subscription_start_date']); ?>', '<?php echo formatDate($tenant['subscription_end_date']); ?>', '<?php echo statusLabel($tenant['status']); ?>', <?php echo daysRemaining($tenant['subscription_end_date']); ?>)" class="btn btn-success" title="Ver detalles">Detalles</button>
                                    <a href="<?php echo $viewInstance->route('superadmin/tenants/' . $tenant['id'] . '/users'); ?>" class="btn btn-warning" title="Gestionar usuarios">Usuarios</a>
                                    <a href="<?php echo $viewInstance->route('app/dashboard'); ?>" class="btn btn-info" title="Acceder al sistema del cliente" target="_blank">Acceder</a>
                                    <button onclick="editTenant(<?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['company_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['razon_social'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['documento_tipo'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['documento_numero'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['phone'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tenant['address'] ?? '', ENT_QUOTES); ?>', <?php echo $tenant['subscription_plan_id']; ?>, '<?php echo $tenant['status']; ?>')" class="btn btn-purple" title="Editar cliente">Editar</button>
                                    <form method="POST" action="<?php echo $viewInstance->route('superadmin/tenants'); ?>?action=toggle_status" class="switch-form" data-ajax="true" data-toggle-tenant="<?php echo $tenant['id']; ?>" title="<?php echo $tenant['status'] === 'cancelled' ? 'Habilitar acceso' : 'Cancelar acceso'; ?>">
                                        <?php echo \SoftNova\Core\csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo $tenant['id']; ?>">
                                        <label class="switch">
                                            <input type="checkbox" name="toggle_status" onchange="handleTenantToggle(this)" <?php echo $tenant['status'] !== 'cancelled' ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </form>
                                    <form method="POST" action="<?php echo $viewInstance->route('superadmin/tenants'); ?>?action=delete" style="display:inline;" data-ajax="true">
                                        <?php echo \SoftNova\Core\csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo $tenant['id']; ?>">
                                        <button type="submit" onclick="return confirm('¿Eliminar este cliente?')" class="btn btn-danger" title="Eliminar cliente">Eliminar</button>
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

<div id="createTenantForm" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Nuevo Cliente</h3>
            <button onclick="document.getElementById('createTenantForm').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('superadmin/tenants'); ?>?action=create" class="settings-form" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="form-group">
                <label>Nombre de la Empresa *</label>
                <input type="text" name="name" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Razon Social</label>
                <input type="text" name="razon_social" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Tipo de Documento</label>
                <select name="documento_tipo" class="neumorphic-input">
                    <option value="">Seleccione</option>
                    <option value="CC">C.C</option>
                    <option value="CE">C.E</option>
                    <option value="PPT">PPT</option>
                    <option value="OTROS">OTROS</option>
                </select>
            </div>
            <div class="form-group">
                <label>Numero de Documento</label>
                <input type="text" name="documento_numero" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Telefono</label>
                <input type="text" name="phone" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Direccion</label>
                <textarea name="address" rows="2" class="neumorphic-input"></textarea>
            </div>
            <div class="form-group">
                <label>Plan *</label>
                <select name="plan_id" required class="neumorphic-input">
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?php echo $plan['id']; ?>"><?php echo htmlspecialchars($plan['name']); ?> - $<?php echo $plan['monthly_price']; ?>/mes</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Ciclo de Facturacion</label>
                <select name="billing_cycle" class="neumorphic-input">
                    <option value="monthly">Mensual</option>
                    <option value="annual">Anual</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary neumorphic-btn">Crear Cliente</button>
        </form>
    </div>
</div>

<div id="viewTenantDetails" class="modal" style="display:none;">
    <div class="modal-content tenant-detail-modal">
        <div class="tenant-detail-hero">
            <div class="tenant-detail-hero-bg"></div>
            <button class="tenant-detail-close" onclick="document.getElementById('viewTenantDetails').style.display='none'">&times;</button>
            <div class="tenant-detail-hero-content">
                <div class="tenant-detail-avatar">
                    <span id="detailAvatarInitial"></span>
                </div>
                <div class="tenant-detail-title">
                    <h2 id="detailCompanyTitle"></h2>
                    <span id="detailStatusBadge" class="badge"></span>
                </div>
            </div>
        </div>
        
        <div class="tenant-detail-body">
            <div class="tenant-detail-section">
                <div class="section-header section-header-blue">
                    <span class="section-icon">&#128100;</span>
                    <h4>Informacion de Contacto</h4>
                </div>
                <div class="tenant-detail-grid">
                    <div class="tenant-detail-card info-card card-blue">
                        <div class="card-icon">&#127968;</div>
                        <div class="card-content">
                            <span class="card-label">Razon Social</span>
                            <span id="detailRazonSocial" class="card-value"></span>
                        </div>
                    </div>
                    
                    <div class="tenant-detail-card info-card card-indigo">
                        <div class="card-icon">&#128196;</div>
                        <div class="card-content">
                            <span class="card-label">Documento</span>
                            <span id="detailDocumento" class="card-value"></span>
                        </div>
                    </div>
                    
                    <div class="tenant-detail-card info-card card-cyan">
                        <div class="card-icon">&#9993;</div>
                        <div class="card-content">
                            <span class="card-label">Email</span>
                            <span id="detailEmail" class="card-value"></span>
                        </div>
                    </div>
                    
                    <div class="tenant-detail-card info-card card-teal">
                        <div class="card-icon">&#128222;</div>
                        <div class="card-content">
                            <span class="card-label">Telefono</span>
                            <span id="detailPhone" class="card-value"></span>
                        </div>
                    </div>
                    
                    <div class="tenant-detail-card info-card full-width card-violet">
                        <div class="card-icon">&#128205;</div>
                        <div class="card-content">
                            <span class="card-label">Direccion</span>
                            <span id="detailAddress" class="card-value"></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tenant-detail-section">
                <div class="section-header section-header-green">
                    <span class="section-icon">&#128187;</span>
                    <h4>Informacion del Plan</h4>
                </div>
                <div class="tenant-detail-grid">
                    <div class="tenant-detail-card plan-card card-green">
                        <div class="card-icon">&#128187;</div>
                        <div class="card-content">
                            <span class="card-label">Plan Contratado</span>
                            <span id="detailPlan" class="card-value"></span>
                        </div>
                    </div>
                    
                    <div class="tenant-detail-card plan-card card-amber">
                        <div class="card-icon">&#128197;</div>
                        <div class="card-content">
                            <span class="card-label">Inicio</span>
                            <span id="detailStartDate" class="card-value"></span>
                        </div>
                    </div>
                    
                    <div class="tenant-detail-card plan-card card-orange">
                        <div class="card-icon">&#9200;</div>
                        <div class="card-content">
                            <span class="card-label">Vencimiento</span>
                            <span id="detailEndDate" class="card-value"></span>
                        </div>
                    </div>
                    
                    <div class="tenant-detail-card plan-card card-rose">
                        <div class="card-icon">&#128228;</div>
                        <div class="card-content">
                            <span class="card-label">Dias Restantes</span>
                            <span id="detailDaysRemaining" class="card-value"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="editTenantForm" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Editar Cliente</h3>
            <button onclick="document.getElementById('editTenantForm').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('superadmin/tenants'); ?>?action=edit" class="settings-form" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="id" id="editTenantId">
            <div class="form-group">
                <label>Nombre de la Empresa *</label>
                <input type="text" name="name" id="editTenantName" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Razon Social</label>
                <input type="text" name="razon_social" id="editTenantRazonSocial" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Tipo de Documento</label>
                <select name="documento_tipo" id="editTenantDocumentoTipo" class="neumorphic-input">
                    <option value="">Seleccione</option>
                    <option value="CC">C.C</option>
                    <option value="CE">C.E</option>
                    <option value="PPT">PPT</option>
                    <option value="OTROS">OTROS</option>
                </select>
            </div>
            <div class="form-group">
                <label>Numero de Documento</label>
                <input type="text" name="documento_numero" id="editTenantDocumentoNumero" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" id="editTenantEmail" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Telefono</label>
                <input type="text" name="phone" id="editTenantPhone" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Direccion</label>
                <textarea name="address" id="editTenantAddress" rows="2" class="neumorphic-input"></textarea>
            </div>
            <div class="form-group">
                <label>Plan *</label>
                <select name="plan_id" id="editTenantPlan" required class="neumorphic-input">
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?php echo $plan['id']; ?>"><?php echo htmlspecialchars($plan['name']); ?> - $<?php echo $plan['monthly_price']; ?>/mes</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Estado</label>
                <select name="status" id="editTenantStatus" class="neumorphic-input">
                    <option value="active">Activo</option>
                    <option value="suspended">Suspendido</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary neumorphic-btn">Actualizar Cliente</button>
        </form>
    </div>
</div>

<script>
function handleTenantToggle(checkbox) {
    const form = checkbox.closest('form');
    const slider = checkbox.nextElementSibling;
    const originalChecked = checkbox.checked;
    
    checkbox.disabled = true;
    if (slider) slider.classList.add('switch-loading');
    
    const formData = new FormData(form);
    const overlay = showLoadingOverlay ? showLoadingOverlay() : null;
    
    fetch(form.action, {
        method: form.method || 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        if (overlay && typeof hideLoadingOverlay === 'function') hideLoadingOverlay(overlay);
        checkbox.disabled = false;
        if (slider) slider.classList.remove('switch-loading');
        
        if (data.success) {
            if (typeof showAlert === 'function') showAlert(data.message, 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            checkbox.checked = !originalChecked;
            if (typeof showAlert === 'function') showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        if (overlay && typeof hideLoadingOverlay === 'function') hideLoadingOverlay(overlay);
        checkbox.disabled = false;
        checkbox.checked = !originalChecked;
        if (slider) slider.classList.remove('switch-loading');
        if (typeof showAlert === 'function') showAlert('Error de conexion con el servidor', 'error');
        console.error('Error:', error);
    });
}

function editTenant(id, name, razonSocial, documentoTipo, documentoNumero, email, phone, address, planId, status) {
    document.getElementById('editTenantId').value = id;
    document.getElementById('editTenantName').value = name;
    document.getElementById('editTenantRazonSocial').value = razonSocial;
    document.getElementById('editTenantDocumentoTipo').value = documentoTipo;
    document.getElementById('editTenantDocumentoNumero').value = documentoNumero;
    document.getElementById('editTenantEmail').value = email;
    document.getElementById('editTenantPhone').value = phone;
    document.getElementById('editTenantAddress').value = address;
    document.getElementById('editTenantPlan').value = planId;
    document.getElementById('editTenantStatus').value = status;
    document.getElementById('editTenantForm').style.display = 'flex';
}

function viewTenantDetails(id, company, razonSocial, documentoTipo, documentoNumero, email, phone, address, plan, startDate, endDate, status, daysRemaining) {
    document.getElementById('detailCompanyTitle').textContent = company;
    document.getElementById('detailAvatarInitial').textContent = company.charAt(0).toUpperCase();
    document.getElementById('detailRazonSocial').textContent = razonSocial || '-';
    document.getElementById('detailDocumento').textContent = (documentoTipo ? documentoTipo + ' ' : '') + (documentoNumero || '-');
    document.getElementById('detailEmail').textContent = email;
    document.getElementById('detailPhone').textContent = phone || '-';
    document.getElementById('detailAddress').textContent = address || '-';
    document.getElementById('detailPlan').textContent = plan;
    document.getElementById('detailStartDate').textContent = startDate;
    document.getElementById('detailEndDate').textContent = endDate;
    
    const daysElement = document.getElementById('detailDaysRemaining');
    let daysText;
    let daysClass;
    if (daysRemaining > 0) {
        daysText = daysRemaining + ' dias';
        daysClass = 'detail-status-active';
    } else if (daysRemaining === 0) {
        daysText = 'Vence hoy';
        daysClass = 'detail-status-suspended';
    } else {
        daysText = 'Vencido (' + Math.abs(daysRemaining) + ' dias)';
        daysClass = 'detail-status-cancelled';
    }
    daysElement.className = 'card-value ' + daysClass;
    daysElement.textContent = daysText;
    
    const statusBadge = document.getElementById('detailStatusBadge');
    statusBadge.className = 'badge';
    if (status === 'Activo') {
        statusBadge.classList.add('badge-success');
    } else if (status === 'Suspendido') {
        statusBadge.classList.add('badge-warning');
    } else if (status === 'Cancelado') {
        statusBadge.classList.add('badge-danger');
    } else {
        statusBadge.classList.add('badge-info');
    }
    statusBadge.textContent = status;
    
    document.getElementById('viewTenantDetails').style.display = 'flex';
}
</script>
