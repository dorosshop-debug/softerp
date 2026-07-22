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
        'free_trial' => 'Free TRIAL',
        default => ucfirst($status),
    };
}

function paymentStatusBadgeClass(string $status): string {
    return match ($status) {
        'paid' => 'badge-success',
        'free_trial' => 'badge-info',
        default => 'badge-warning',
    };
}

function billingCycleLabel(string $cycle): string {
    return match ($cycle) {
        'monthly' => 'Mensual',
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
                                    <button onclick="registerPayment(<?php echo $sale['id']; ?>, <?php echo $sale['amount'] - $sale['paid_amount']; ?>)" class="btn btn-sm btn-success" title="Registrar pago"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></button>
                                    <button onclick="editLicense(<?php echo $sale['id']; ?>, <?php echo $sale['tenant_id'] ?? 'null'; ?>, <?php echo $sale['plan_id']; ?>, '<?php echo $sale['sale_date']; ?>', '<?php echo $sale['start_date']; ?>', '<?php echo $sale['billing_cycle']; ?>', '<?php echo $sale['payment_status']; ?>', '<?php echo $sale['payment_method']; ?>', '<?php echo htmlspecialchars($sale['notes'] ?? '', ENT_QUOTES); ?>', '<?php echo $sale['status']; ?>')" class="btn btn-sm btn-purple" title="Editar venta"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
                                    <form method="POST" action="<?php echo $viewInstance->route('superadmin/licencias'); ?>?action=delete" style="display:inline;" data-ajax="true">
                                        <?php echo \SoftNova\Core\csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo $sale['id']; ?>">
                                        <button type="submit" onclick="return confirm('¿Eliminar esta venta?')" class="btn btn-sm btn-danger" title="Eliminar venta"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
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
<div id="createLicenseForm" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width: 580px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3>🛒 Nueva Venta de Licencia</h3>
            <button onclick="document.getElementById('createLicenseForm').style.display='none'" class="modal-close">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('superadmin/licencias'); ?>?action=create" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="modal-body">
                <!-- Cliente existente o nuevo -->
                <div class="form-group">
                    <label>🏢 Cliente</label>
                    <select name="tenant_id" id="createTenantId" class="form-control" onchange="toggleNewClientForm(this.value)">
                        <option value="">Seleccione un cliente</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?php echo $tenant['id']; ?>"><?php echo htmlspecialchars($tenant['company_name']); ?></option>
                        <?php endforeach; ?>
                        <option value="__new__">➕ Nuevo Cliente...</option>
                    </select>
                </div>
                
                <!-- Formulario inline para nuevo cliente -->
                <div id="newClientInline" style="display:none; background: var(--bg-input); border: 1px solid var(--color-border); border-radius: 8px; padding: 12px; margin-bottom: 15px;">
                    <h4 style="margin:0 0 10px 0;font-size:14px;">➕ Nuevo Cliente</h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;">Empresa *</label>
                            <input type="text" name="new_company_name" id="newCompanyName" class="form-control" placeholder="Nombre de la empresa">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;">Email *</label>
                            <input type="email" name="new_email" id="newEmail" class="form-control" placeholder="correo@empresa.com">
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>📦 Plan *</label>
                        <select name="plan_id" id="createPlanId" required class="form-control">
                            <option value="">Seleccione</option>
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?php echo $plan['id']; ?>"><?php echo htmlspecialchars($plan['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🔄 Ciclo de Facturación *</label>
                        <select name="billing_cycle" id="createBillingCycle" required class="form-control">
                            <option value="monthly">Mensual</option>
                            <option value="semiannual">Semestral</option>
                            <option value="annual">Anual</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>📅 Fecha de Venta *</label>
                        <input type="date" name="sale_date" required class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>🚀 Fecha de Inicio *</label>
                        <input type="date" name="start_date" required class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>💳 Estado de Pago *</label>
                        <select name="payment_status" required class="form-control">
                            <option value="paid">✅ Pagado</option>
                            <option value="free_trial">🆓 Free TRIAL</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🏦 Método de Pago</label>
                        <select name="payment_method" class="form-control">
                            <option value="transfer">Transferencia</option>
                            <option value="cash">Efectivo</option>
                            <option value="card">Tarjeta</option>
                            <option value="deposit">Depósito</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>📝 Notas</label>
                    <textarea name="notes" rows="2" class="form-control" placeholder="Observaciones..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('createLicenseForm').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary neumorphic-btn">💾 Crear Venta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Venta -->
<div id="editLicenseForm" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width: 580px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3>✏️ Editar Venta de Licencia</h3>
            <button onclick="document.getElementById('editLicenseForm').style.display='none'" class="modal-close">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('superadmin/licencias'); ?>?action=edit" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="id" id="editLicenseId">
            <div class="modal-body">
                <div class="form-group">
                    <label>🏢 Cliente</label>
                    <select name="tenant_id" id="editLicenseTenant" class="form-control">
                        <option value="">Seleccione un cliente</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?php echo $tenant['id']; ?>"><?php echo htmlspecialchars($tenant['company_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>📦 Plan *</label>
                        <select name="plan_id" id="editLicensePlan" required class="form-control">
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?php echo $plan['id']; ?>"><?php echo htmlspecialchars($plan['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🔄 Ciclo de Facturación *</label>
                        <select name="billing_cycle" id="editLicenseBillingCycle" required class="form-control">
                            <option value="monthly">Mensual</option>
                            <option value="semiannual">Semestral</option>
                            <option value="annual">Anual</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>📅 Fecha de Venta *</label>
                        <input type="date" name="sale_date" id="editLicenseSaleDate" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>🚀 Fecha de Inicio *</label>
                        <input type="date" name="start_date" id="editLicenseStartDate" required class="form-control">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>💳 Estado Pago *</label>
                        <select name="payment_status" id="editLicensePaymentStatus" required class="form-control">
                            <option value="paid">✅ Pagado</option>
                            <option value="free_trial">🆓 Free TRIAL</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🏦 Método</label>
                        <select name="payment_method" id="editLicensePaymentMethod" class="form-control">
                            <option value="transfer">Transferencia</option>
                            <option value="cash">Efectivo</option>
                            <option value="card">Tarjeta</option>
                            <option value="deposit">Depósito</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>📌 Estado</label>
                        <select name="status" id="editLicenseStatus" class="form-control">
                            <option value="active">✅ Activo</option>
                            <option value="inactive">❌ Inactivo</option>
                            <option value="cancelled">🗑️ Cancelado</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>📝 Notas</label>
                    <textarea name="notes" id="editLicenseNotes" rows="2" class="form-control"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editLicenseForm').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary neumorphic-btn">💾 Actualizar Venta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Registrar Pago -->
<div id="paymentForm" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width: 450px;">
        <div class="modal-header">
            <h3>💵 Registrar Pago</h3>
            <button onclick="document.getElementById('paymentForm').style.display='none'" class="modal-close">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('superadmin/licencias'); ?>?action=payment" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="sale_id" id="paymentSaleId">
            <div class="modal-body">
                <div class="form-group">
                    <label>💰 Monto *</label>
                    <input type="number" name="amount" id="paymentAmount" step="0.01" min="0.01" required class="form-control" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>📅 Fecha de Pago *</label>
                    <input type="date" name="payment_date" required class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>🏦 Método de Pago</label>
                    <select name="payment_method" class="form-control">
                        <option value="transfer">Transferencia</option>
                        <option value="cash">Efectivo</option>
                        <option value="card">Tarjeta</option>
                        <option value="deposit">Depósito</option>
                        <option value="other">Otro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>📝 Notas</label>
                    <textarea name="notes" rows="2" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('paymentForm').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary neumorphic-btn">💾 Registrar Pago</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleNewClientForm(val) {
    document.getElementById('newClientInline').style.display = val === '__new__' ? 'block' : 'none';
}

function editLicense(id, tenantId, planId, saleDate, startDate, billingCycle, paymentStatus, paymentMethod, notes, status) {
    document.getElementById('editLicenseId').value = id;
    document.getElementById('editLicenseTenant').value = tenantId ?? '';
    document.getElementById('editLicensePlan').value = planId;
    document.getElementById('editLicenseBillingCycle').value = billingCycle;
    document.getElementById('editLicenseSaleDate').value = saleDate;
    document.getElementById('editLicenseStartDate').value = startDate;
    document.getElementById('editLicensePaymentStatus').value = paymentStatus;
    document.getElementById('editLicensePaymentMethod').value = paymentMethod;
    document.getElementById('editLicenseNotes').value = notes || '';
    document.getElementById('editLicenseStatus').value = status;
    document.getElementById('editLicenseForm').style.display = 'flex';
}

function registerPayment(saleId, pendingAmount) {
    document.getElementById('paymentSaleId').value = saleId;
    document.getElementById('paymentAmount').value = pendingAmount > 0 ? pendingAmount.toFixed(2) : '';
    document.getElementById('paymentForm').style.display = 'flex';
}
</script>
