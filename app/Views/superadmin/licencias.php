<?php
$layout = 'superadmin';
$title = 'Super Administrador - Control de Licencias';
$pageTitle = 'Control de Licencias';
$userName = $_SESSION['super_admin_name'] ?? 'Super Admin';
$sales = $sales ?? [];
$tenants = $tenants ?? [];
$plans = $plans ?? [];
$stats = $stats ?? [];

function formatCurrency(?float $amount): string {
    return '$' . number_format($amount ?? 0, 2);
}

function paymentStatusLabel(string $status): string {
    return match ($status) {
        'paid' => 'Pagado',
        'pending' => 'Pendiente',
        'partial' => 'Parcial',
        'cancelled' => 'Cancelado',
        'refunded' => 'Reembolsado',
        default => ucfirst($status),
    };
}

function paymentStatusBadgeClass(string $status): string {
    return match ($status) {
        'paid' => 'badge-success',
        'pending' => 'badge-warning',
        'partial' => 'badge-info',
        'cancelled' => 'badge-danger',
        'refunded' => 'badge-secondary',
        default => 'badge-info',
    };
}

function billingCycleLabel(string $cycle): string {
    return match ($cycle) {
        'monthly' => 'Mensual',
        'quarterly' => 'Trimestral',
        'semiannual' => 'Semestral',
        'annual' => 'Anual',
        default => ucfirst($cycle),
    };
}
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

<!-- Estadísticas -->
<div class="stats-grid">
    <div class="stat-card neumorphic">
        <h4>Total Ventas</h4>
        <div class="stat-value"><?php echo number_format($stats['total_sales'] ?? 0); ?></div>
    </div>
    <div class="stat-card neumorphic">
        <h4>Ingresos Totales</h4>
        <div class="stat-value"><?php echo formatCurrency($stats['total_revenue'] ?? 0); ?></div>
    </div>
    <div class="stat-card neumorphic">
        <h4>Pagos Pendientes</h4>
        <div class="stat-value"><?php echo number_format($stats['pending_payments'] ?? 0); ?></div>
    </div>
    <div class="stat-card neumorphic">
        <h4>Suscripciones Activas</h4>
        <div class="stat-value"><?php echo number_format($stats['active_subscriptions'] ?? 0); ?></div>
    </div>
</div>

<div class="card neumorphic">
    <div class="card-header">
        <h3>Ventas y Suscripciones</h3>
        <button onclick="document.getElementById('createLicenseForm').style.display='flex'" class="btn btn-primary neumorphic-btn">
            Nueva Venta
        </button>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Plan</th>
                        <th>Ciclo</th>
                        <th>Monto</th>
                        <th>Pagado</th>
                        <th>Estado Pago</th>
                        <th>Inicio</th>
                        <th>Vencimiento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; color: var(--color-text-secondary);">
                                No hay ventas registradas
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sale['sale_code']); ?></strong></td>
                                <td>
                                    <?php if ($sale['tenant_id']): ?>
                                        <?php echo htmlspecialchars($sale['company_name'] ?? 'N/A'); ?>
                                    <?php else: ?>
                                        <span style="color: var(--color-text-secondary);">Sin cliente asignado</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($sale['plan_name'] ?? '-'); ?></td>
                                <td><?php echo billingCycleLabel($sale['billing_cycle']); ?></td>
                                <td><?php echo formatCurrency($sale['amount']); ?></td>
                                <td><?php echo formatCurrency($sale['paid_amount']); ?></td>
                                <td>
                                    <span class="badge <?php echo paymentStatusBadgeClass($sale['payment_status']); ?>">
                                        <?php echo paymentStatusLabel($sale['payment_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($sale['start_date']); ?></td>
                                <td><?php echo formatDate($sale['end_date']); ?></td>
                                <td class="table-actions">
                                    <button onclick="registerPayment(<?php echo $sale['id']; ?>, <?php echo $sale['amount'] - $sale['paid_amount']; ?>)" class="btn btn-success" title="Registrar pago">Pago</button>
                                    <button onclick="editLicense(<?php echo $sale['id']; ?>, <?php echo $sale['tenant_id'] ?? 'null'; ?>, <?php echo $sale['plan_id']; ?>, '<?php echo $sale['sale_date']; ?>', '<?php echo $sale['start_date']; ?>', '<?php echo $sale['billing_cycle']; ?>', '<?php echo $sale['payment_status']; ?>', '<?php echo $sale['payment_method']; ?>', '<?php echo htmlspecialchars($sale['reference_number'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($sale['notes'] ?? '', ENT_QUOTES); ?>', '<?php echo $sale['status']; ?>')" class="btn btn-purple" title="Editar venta">Editar</button>
                                    <form method="POST" action="<?php echo $viewInstance->route('superadmin/licencias'); ?>?action=delete" style="display:inline;" data-ajax="true">
                                        <?php echo \SoftNova\Core\csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo $sale['id']; ?>">
                                        <button type="submit" onclick="return confirm('¿Eliminar esta venta?')" class="btn btn-danger" title="Eliminar venta">Eliminar</button>
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

<!-- Modal Crear Venta -->
<div id="createLicenseForm" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Nueva Venta de Licencia</h3>
            <button onclick="document.getElementById('createLicenseForm').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('superadmin/licencias'); ?>?action=create" class="settings-form" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="form-group">
                <label>Cliente</label>
                <select name="tenant_id" class="neumorphic-input">
                    <option value="">Seleccione un cliente (opcional)</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?php echo $tenant['id']; ?>"><?php echo htmlspecialchars($tenant['company_name']); ?> (<?php echo htmlspecialchars($tenant['email']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Plan *</label>
                <select name="plan_id" id="createPlanId" required class="neumorphic-input" onchange="updateCreateAmount()">
                    <option value="">Seleccione</option>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?php echo $plan['id']; ?>" data-monthly="<?php echo $plan['monthly_price']; ?>" data-annual="<?php echo $plan['annual_price']; ?>">
                            <?php echo htmlspecialchars($plan['name']); ?> - $<?php echo $plan['monthly_price']; ?>/mes
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Ciclo de Facturación *</label>
                <select name="billing_cycle" id="createBillingCycle" required class="neumorphic-input" onchange="updateCreateAmount()">
                    <option value="monthly">Mensual</option>
                    <option value="quarterly">Trimestral</option>
                    <option value="semiannual">Semestral</option>
                    <option value="annual">Anual</option>
                </select>
            </div>
            <div class="form-group">
                <label>Fecha de Venta *</label>
                <input type="date" name="sale_date" required class="neumorphic-input" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Fecha de Inicio *</label>
                <input type="date" name="start_date" id="createStartDate" required class="neumorphic-input" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Estado de Pago *</label>
                <select name="payment_status" required class="neumorphic-input">
                    <option value="pending">Pendiente</option>
                    <option value="paid">Pagado</option>
                    <option value="partial">Parcial</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>
            <div class="form-group">
                <label>Método de Pago</label>
                <select name="payment_method" class="neumorphic-input">
                    <option value="other">Otro</option>
                    <option value="cash">Efectivo</option>
                    <option value="transfer">Transferencia</option>
                    <option value="card">Tarjeta</option>
                    <option value="deposit">Depósito</option>
                </select>
            </div>
            <div class="form-group">
                <label>Número de Referencia</label>
                <input type="text" name="reference_number" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Notas</label>
                <textarea name="notes" rows="2" class="neumorphic-input"></textarea>
            </div>
            <button type="submit" class="btn btn-primary neumorphic-btn">Crear Venta</button>
        </form>
    </div>
</div>

<!-- Modal Editar Venta -->
<div id="editLicenseForm" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Editar Venta de Licencia</h3>
            <button onclick="document.getElementById('editLicenseForm').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('superadmin/licencias'); ?>?action=edit" class="settings-form" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="id" id="editLicenseId">
            <div class="form-group">
                <label>Cliente</label>
                <select name="tenant_id" id="editLicenseTenant" class="neumorphic-input">
                    <option value="">Seleccione un cliente (opcional)</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?php echo $tenant['id']; ?>"><?php echo htmlspecialchars($tenant['company_name']); ?> (<?php echo htmlspecialchars($tenant['email']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Plan *</label>
                <select name="plan_id" id="editLicensePlan" required class="neumorphic-input" onchange="updateEditAmount()">
                    <option value="">Seleccione</option>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?php echo $plan['id']; ?>" data-monthly="<?php echo $plan['monthly_price']; ?>" data-annual="<?php echo $plan['annual_price']; ?>">
                            <?php echo htmlspecialchars($plan['name']); ?> - $<?php echo $plan['monthly_price']; ?>/mes
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Ciclo de Facturación *</label>
                <select name="billing_cycle" id="editLicenseBillingCycle" required class="neumorphic-input" onchange="updateEditAmount()">
                    <option value="monthly">Mensual</option>
                    <option value="quarterly">Trimestral</option>
                    <option value="semiannual">Semestral</option>
                    <option value="annual">Anual</option>
                </select>
            </div>
            <div class="form-group">
                <label>Fecha de Venta *</label>
                <input type="date" name="sale_date" id="editLicenseSaleDate" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Fecha de Inicio *</label>
                <input type="date" name="start_date" id="editLicenseStartDate" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Estado de Pago *</label>
                <select name="payment_status" id="editLicensePaymentStatus" required class="neumorphic-input">
                    <option value="pending">Pendiente</option>
                    <option value="paid">Pagado</option>
                    <option value="partial">Parcial</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>
            <div class="form-group">
                <label>Método de Pago</label>
                <select name="payment_method" id="editLicensePaymentMethod" class="neumorphic-input">
                    <option value="other">Otro</option>
                    <option value="cash">Efectivo</option>
                    <option value="transfer">Transferencia</option>
                    <option value="card">Tarjeta</option>
                    <option value="deposit">Depósito</option>
                </select>
            </div>
            <div class="form-group">
                <label>Número de Referencia</label>
                <input type="text" name="reference_number" id="editLicenseReference" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Notas</label>
                <textarea name="notes" id="editLicenseNotes" rows="2" class="neumorphic-input"></textarea>
            </div>
            <div class="form-group">
                <label>Estado</label>
                <select name="status" id="editLicenseStatus" class="neumorphic-input">
                    <option value="active">Activo</option>
                    <option value="inactive">Inactivo</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary neumorphic-btn">Actualizar Venta</button>
        </form>
    </div>
</div>

<!-- Modal Registrar Pago -->
<div id="paymentForm" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Registrar Pago</h3>
            <button onclick="document.getElementById('paymentForm').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('superadmin/licencias'); ?>?action=payment" class="settings-form" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="sale_id" id="paymentSaleId">
            <div class="form-group">
                <label>Monto *</label>
                <input type="number" name="amount" id="paymentAmount" step="0.01" min="0.01" required class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Fecha de Pago *</label>
                <input type="date" name="payment_date" required class="neumorphic-input" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Método de Pago</label>
                <select name="payment_method" class="neumorphic-input">
                    <option value="cash">Efectivo</option>
                    <option value="transfer">Transferencia</option>
                    <option value="card">Tarjeta</option>
                    <option value="deposit">Depósito</option>
                    <option value="other">Otro</option>
                </select>
            </div>
            <div class="form-group">
                <label>Número de Referencia</label>
                <input type="text" name="reference_number" class="neumorphic-input">
            </div>
            <div class="form-group">
                <label>Notas</label>
                <textarea name="notes" rows="2" class="neumorphic-input"></textarea>
            </div>
            <button type="submit" class="btn btn-primary neumorphic-btn">Registrar Pago</button>
        </form>
    </div>
</div>

<script>
function editLicense(id, tenantId, planId, saleDate, startDate, billingCycle, paymentStatus, paymentMethod, referenceNumber, notes, status) {
    document.getElementById('editLicenseId').value = id;
    document.getElementById('editLicenseTenant').value = tenantId ?? '';
    document.getElementById('editLicensePlan').value = planId;
    document.getElementById('editLicenseBillingCycle').value = billingCycle;
    document.getElementById('editLicenseSaleDate').value = saleDate;
    document.getElementById('editLicenseStartDate').value = startDate;
    document.getElementById('editLicensePaymentStatus').value = paymentStatus;
    document.getElementById('editLicensePaymentMethod').value = paymentMethod;
    document.getElementById('editLicenseReference').value = referenceNumber;
    document.getElementById('editLicenseNotes').value = notes;
    document.getElementById('editLicenseStatus').value = status;
    document.getElementById('editLicenseForm').style.display = 'flex';
}

function registerPayment(saleId, pendingAmount) {
    document.getElementById('paymentSaleId').value = saleId;
    document.getElementById('paymentAmount').value = pendingAmount > 0 ? pendingAmount.toFixed(2) : '';
    document.getElementById('paymentForm').style.display = 'flex';
}

function updateCreateAmount() {
    // El monto se calcula en el servidor según el plan y ciclo seleccionados
}

function updateEditAmount() {
    // El monto se calcula en el servidor según el plan y ciclo seleccionados
}
</script>
