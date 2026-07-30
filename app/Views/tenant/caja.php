<?php
$layout = 'tenant';
$title = 'Caja-POS - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Caja-POS';
$loadBarcode = true;
$pageScripts = ['js/ventas.js', 'js/caja.js'];
$tenantName = $tenantName ?? 'Mi Empresa'; $userName = $userName ?? 'Usuario';
$openSession = $openSession ?? null; $movements = $movements ?? [];
$todaySales = $todaySales ?? [];
$totals = $totals ?? ['incomes'=>0,'expenses'=>0,'balance'=>0];
$historySessions = $historySessions ?? [];
$posCustomers = $posCustomers ?? [];
$paymentMethods = $paymentMethods ?? \SoftNova\Services\PaymentMethodCatalog::all();
$invoicePrefix = $invoicePrefix ?? 'FAC-';
$currency = $currency ?? ['symbol'=>'$','name'=>'Peso','decimals'=>0];
$canSell = \SoftNova\Core\TenantMiddleware::canAccess('ventas')
    && \SoftNova\Core\TenantMiddleware::canDo('create', 'ventas');
function fmt(float $a, array $c): string { return $c['symbol'].' '.number_format($a,$c['decimals'],$c['decimal']??',',$c['thousands']??'.'); }
function hoursOpen(string $date): float { return round((time()-strtotime($date))/3600, 1); }
?>
<meta name="currency-symbol" content="<?php echo htmlspecialchars($currency['symbol'] ?? '$'); ?>">
<meta name="currency-decimals" content="<?php echo (int)($currency['decimals'] ?? 0); ?>">
<?php echo flashMessage(); ?>

<?php if (!$openSession): ?>
<div class="card neumorphic" style="text-align:center;padding:40px;">
    <div style="font-size:64px;margin-bottom:15px;">🔒</div>
    <h2 style="color:var(--color-primary);margin-bottom:10px;">Caja Cerrada</h2>
    <p style="color:var(--color-text-secondary);margin-bottom:20px;">No hay ninguna sesión de caja abierta.</p>
    <button onclick="document.getElementById('openCashModal').style.display='flex'" class="btn btn-primary neumorphic-btn" style="font-size:16px;padding:12px 30px;">🔓 Abrir Caja</button>
</div>

<div id="openCashModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:450px;">
        <div class="modal-header"><h3>Abrir Caja</h3><button onclick="document.getElementById('openCashModal').style.display='none'" class="modal-close">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/caja'); ?>?action=open" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="modal-body">
                <div class="form-group"><label>Monto de Apertura (<?php echo $currency['symbol']; ?>) *</label><input type="number" name="opening_amount" class="form-control" step="0.01" min="0" placeholder="0" required autofocus></div>
                <div class="form-group"><label>Notas</label><textarea name="notes" class="form-control" rows="2" placeholder="Observaciones..."></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="document.getElementById('openCashModal').style.display='none'">Cancelar</button><button type="submit" class="btn btn-primary">Abrir Caja</button></div>
        </form>
    </div>
</div>

<?php else: ?>
<?php
$hours = hoursOpen($openSession['opening_date']);
$warningClass = $hours > 20 ? 'badge-danger' : ($hours > 16 ? 'badge-warning' : 'badge-success');
$csrfPos = \SoftNova\Core\csrf_token();
$searchUrl = $viewInstance->route('app/caja') . '?action=searchProducts';
$saleUrl = $viewInstance->route('app/ventas') . '?action=create';
$isAdmin = $isAdmin ?? \SoftNova\Core\TenantMiddleware::isAdmin();
$isPosUser = $isPosUser ?? \SoftNova\Core\TenantMiddleware::isPosUser();
$taxRate = (float)($taxRate ?? 0);
$canManageCash = \SoftNova\Core\TenantMiddleware::canDo('create', 'caja');
$canCloseCash = \SoftNova\Core\TenantMiddleware::canDo('edit', 'caja');
$canGastos = \SoftNova\Core\TenantMiddleware::canAccess('gastos');
$canCompras = \SoftNova\Core\TenantMiddleware::canAccess('compras');
$invoicePreview = ($invoicePrefix ?? 'FAC-') . date('Ymd') . '-XXXX';
$expenseCategories = $expenseCategories ?? [];
$expenseSuppliers = $expenseSuppliers ?? [];
$financialCats = array_filter($expenseCategories, static fn($c) => ($c['kind'] ?? '') === 'financial');
$operationalCats = array_filter($expenseCategories, static fn($c) => ($c['kind'] ?? '') === 'operational');
?>

<?php if ($isAdmin): ?>
<section class="caja-stats-section card neumorphic" id="cajaStatsSection" style="margin-bottom:20px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <h3 style="margin:0;">Resumen de caja</h3>
        <button type="button" class="btn btn-secondary btn-sm" id="cajaStatsToggle">Ocultar</button>
    </div>
    <div class="card-body" id="cajaStatsBody">
        <div class="stats-grid">
            <div class="stat-card neumorphic"><h4>Monto Apertura</h4><div class="stat-value"><?php echo fmt($openSession['opening_amount'], $currency); ?></div></div>
            <div class="stat-card neumorphic"><h4>Ingresos</h4><div class="stat-value" style="color:#10B981;">+<?php echo fmt($totals['incomes'], $currency); ?></div></div>
            <div class="stat-card neumorphic"><h4>Egresos</h4><div class="stat-value" style="color:#DC2626;">-<?php echo fmt($totals['expenses'], $currency); ?></div></div>
            <div class="stat-card neumorphic"><h4>Balance Actual</h4><div class="stat-value" style="color:<?php echo $totals['balance']>=0?'#10B981':'#DC2626';?>;"><?php echo fmt($totals['balance'], $currency); ?></div></div>
        </div>
    </div>
</section>
<?php endif; ?>

<div style="display:flex;gap:15px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
    <div style="flex:1;min-width:200px;">
        <span style="color:var(--color-text-secondary);font-size:13px;">
            Abierta por: <strong><?php echo htmlspecialchars($openSession['user_name']); ?></strong> · <?php echo date('d/m/Y H:i', strtotime($openSession['opening_date'])); ?>
        </span>
        <span class="badge <?php echo $warningClass; ?>" style="margin-left:8px;">
            ⏱️ <?php echo $hours; ?>h abierta
            <?php if ($hours > 20): ?> ⚠️ ¡Cierre antes de 24h!<?php endif; ?>
        </span>
        <?php if ($hours > 22): ?>
        <div class="alert alert-error" style="margin-top:8px;font-size:13px;">
            ⚠️ <strong>¡Atención!</strong> La caja lleva más de 22 horas abierta. Debe cerrarse antes de 24 horas.
        </div>
        <?php endif; ?>
    </div>
    <?php if ($canManageCash): ?>
    <button type="button" onclick="openGastoCompraModal()" class="btn btn-primary neumorphic-btn">+ Gasto / Compra</button>
    <?php endif; ?>
    <?php if ($canCloseCash): ?>
    <button onclick="document.getElementById('closeCashModal').style.display='flex'" class="btn btn-danger">Cerrar Caja</button>
    <?php endif; ?>
</div>

<?php if ($canSell): ?>
<!-- POS (venta rápida) -->
<section class="pos-panel" id="posPanel"
         data-search-url="<?php echo htmlspecialchars($searchUrl); ?>"
         data-sale-url="<?php echo htmlspecialchars($saleUrl); ?>"
         data-csrf="<?php echo htmlspecialchars($csrfPos); ?>"
         data-symbol="<?php echo htmlspecialchars($currency['symbol'] ?? '$'); ?>"
         data-decimals="<?php echo (int)($currency['decimals'] ?? 0); ?>"
         data-prefix="<?php echo htmlspecialchars((string)$invoicePrefix); ?>"
         data-tax-rate="<?php echo htmlspecialchars((string)$taxRate); ?>"
         data-user="<?php echo htmlspecialchars($userName); ?>">
    <div class="pos-sale card neumorphic">
        <div class="pos-sale-head">
            <div class="pos-sale-meta">
                <label class="pos-label">Cliente</label>
                <div class="pos-customer-row">
                    <div class="customer-combobox pos-customer-combo" id="posCustomerCombobox">
                        <input type="hidden" id="posCustomer" value="">
                        <input type="text" id="posCustomerSearch" class="form-control pos-customer" placeholder="Buscar cliente…" autocomplete="off">
                        <ul class="combobox-list" id="posCustomerList" hidden>
                            <li class="combobox-option" data-id="" data-label="Cliente general" data-search="cliente general">Cliente general</li>
                            <?php foreach ($posCustomers as $c):
                                $cname = trim((string)($c['name'] ?? ''));
                                if ($cname === '') {
                                    $cname = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                                }
                                if ($cname === '') {
                                    $cname = 'Cliente #' . $c['id'];
                                }
                                $csearch = mb_strtolower($cname . ' ' . ($c['document_number'] ?? '') . ' ' . ($c['phone'] ?? ''));
                            ?>
                                <li class="combobox-option" data-id="<?php echo (int)$c['id']; ?>" data-label="<?php echo htmlspecialchars($cname); ?>" data-search="<?php echo htmlspecialchars($csearch); ?>"><?php echo htmlspecialchars($cname); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php if (\SoftNova\Core\TenantMiddleware::canDo('create', 'clientes')): ?>
                        <button type="button" class="btn btn-secondary btn-sm" id="posNewCustomerBtn" title="Nuevo cliente">+ Nuevo</button>
                    <?php endif; ?>
                </div>
                <div class="pos-discount-row">
                    <label class="pos-label" for="posDiscountPercent">Descuento %</label>
                    <input type="number" id="posDiscountPercent" class="form-control pos-discount" min="0" max="100" step="0.01" value="0" title="Descuento sobre el subtotal">
                </div>
                <div class="pos-attendant">
                    <span class="pos-label">Atendido</span>
                    <strong id="posAttendant"><?php echo htmlspecialchars(mb_strtoupper($userName)); ?></strong>
                </div>
                <div class="pos-ticket-meta">
                    <span>Nº <strong id="posPrefijo" title="Número de factura"><?php echo htmlspecialchars($invoicePreview); ?></strong></span>
                    <span>Total Items: <strong id="posItemCount">0</strong></span>
                    <span>Registro: <strong id="posClock"><?php echo date('g:i A'); ?></strong></span>
                </div>
                <div class="pos-subtotal-line" id="posSubtotalLine" hidden>Subtotal: <span id="posSubtotal"><?php echo fmt(0, $currency); ?></span></div>
                <div class="pos-discount-line" id="posDiscountLine" hidden>Descuento: <span id="posDiscountAmt"><?php echo fmt(0, $currency); ?></span></div>
                <div class="pos-tax-line" id="posTaxLine" hidden>IVA: <span id="posTaxAmt"><?php echo fmt(0, $currency); ?></span></div>
                <div class="pos-total-line">TOTAL: <span id="posTotal"><?php echo fmt(0, $currency); ?></span></div>
            </div>
            <button type="button" class="pos-pay-btn" id="posPayBtn" disabled title="Se activa cuando la venta tiene productos">Listo</button>
        </div>
        <div class="pos-items-wrap">
            <table class="pos-items-table">
                <thead>
                    <tr><th style="width:70px;">Cant</th><th>Producto</th><th style="width:110px;">Acción</th></tr>
                </thead>
                <tbody id="posItemsBody">
                    <tr class="pos-empty-row"><td colspan="3">Escanee o busque productos a la derecha</td></tr>
                </tbody>
            </table>
        </div>
        <div class="pos-pay-bar" id="posPayBar" hidden>
            <select id="posPaymentType" class="form-control" title="Contado o crédito">
                <option value="full">Contado</option>
                <option value="credit">Crédito</option>
            </select>
            <select id="posPaymentTerms" class="form-control" title="Condición de pago" style="display:none;">
                <option value="cash">Contado / inmediato</option>
                <option value="net_15">Crédito 15 días</option>
                <option value="net_30" selected>Crédito 30 días</option>
            </select>
            <input type="number" id="posInitialPayment" class="form-control" step="0.01" min="0" placeholder="Abono inicial" style="display:none;max-width:140px;" title="Abono inicial (crédito)">
            <select id="posPayMethod" class="form-control">
                <?php foreach ($paymentMethods as $code => $meta): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $code === 'cash' ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($meta['label'] ?? $code); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-success" id="posConfirmPay">Cobrar ahora</button>
            <button type="button" class="btn btn-secondary" id="posCancelPay">Cancelar</button>
        </div>
    </div>

    <div class="pos-search card neumorphic">
        <div class="pos-search-box">
            <span class="pos-search-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </span>
            <input type="text" id="posProductSearch" class="pos-search-input" placeholder="Código de barras o nombre…" autocomplete="off" autofocus data-barcode-input="true">
            <button type="button" class="pos-search-clear" id="posSearchClear" title="Limpiar">&times;</button>
        </div>
        <div class="pos-recent-block" id="posRecentBlock">
            <div class="pos-recent-title">Últimos buscados</div>
            <div class="pos-recent-list" id="posRecentList"></div>
        </div>
        <div class="pos-search-results" id="posSearchResults">
            <p class="pos-search-hint">Escriba o escanee un código para encontrar productos</p>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Ventas de la sesión (mismo formato que módulo Ventas) -->
<?php
$ventasBase = $viewInstance->route('app/ventas');
$canDeleteSale = \SoftNova\Core\TenantMiddleware::canDo('delete', 'ventas');
$canPaySale = \SoftNova\Core\TenantMiddleware::canDo('create', 'ventas');
?>
<div class="card neumorphic" style="margin-bottom:20px;">
    <div class="card-header"><h3>📋 Últimas Ventas (<?php echo (int)($salesPagination['total'] ?? count($todaySales)); ?>)</h3></div>
    <div class="card-body">
        <?php if (empty($todaySales)): ?>
            <p style="text-align:center;color:var(--color-text-secondary);padding:20px;">Aún no hay ventas en esta sesión de caja</p>
        <?php else: ?>
            <div class="table-container"><table>
                <thead><tr>
                    <th>Factura</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Pagado</th>
                    <th>Pago</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr></thead>
                <tbody>
                <?php foreach ($todaySales as $sale): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($sale['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'General'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($sale['sale_date'])); ?></td>
                        <td style="font-weight:600;"><?php echo fmt((float)$sale['total'], $currency); ?></td>
                        <td><?php echo ($sale['payment_status'] ?? '') === 'paid' ? fmt((float)$sale['total'], $currency) : fmt((float)($sale['paid_amount'] ?? 0), $currency); ?></td>
                        <td>
                            <span class="badge <?php echo ($sale['payment_method'] ?? '') === 'cash' ? 'badge-success' : (in_array($sale['payment_method'] ?? '', ['card','dataphone','payment_link','transfer'], true) ? 'badge-info' : 'badge-warning'); ?>">
                                <?php echo htmlspecialchars(\SoftNova\Services\PaymentMethodCatalog::label((string)($sale['payment_method'] ?? 'cash'))); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (($sale['status'] ?? '') === 'cancelled'): ?>
                                <span class="badge badge-danger">Cancelado</span>
                            <?php elseif (($sale['payment_status'] ?? '') === 'paid'): ?>
                                <span class="badge badge-success">Pagado</span>
                            <?php elseif (($sale['payment_status'] ?? '') === 'partial'): ?>
                                <span class="badge badge-warning">Parcial</span>
                            <?php else: ?>
                                <span class="badge badge-info">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td class="table-actions">
                            <button type="button" onclick="viewDetail(<?php echo (int)$sale['id']; ?>)" class="btn btn-sm btn-info" title="Ver detalle"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                            <a href="<?php echo $ventasBase; ?>?action=pdf&id=<?php echo (int)$sale['id']; ?>" class="btn btn-sm btn-secondary" target="_blank" title="PDF carta"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg></a>
                            <a href="<?php echo $ventasBase; ?>?action=pdf&format=ticket&id=<?php echo (int)$sale['id']; ?>" class="btn btn-sm btn-secondary" target="_blank" title="Ticket 58mm">58mm</a>
                            <button type="button" class="btn btn-sm btn-success" onclick="shareSale(<?php echo (int)$sale['id']; ?>)" title="WhatsApp / Correo">↗</button>
                            <?php if ($canPaySale && in_array($sale['payment_status'] ?? '', ['pending', 'partial'], true)): ?>
                                <button type="button" onclick="openPaymentModal(<?php echo (int)$sale['id']; ?>,<?php echo (float)$sale['total']; ?>,<?php echo (float)($sale['paid_amount'] ?? 0); ?>)" class="btn btn-sm btn-success" title="Registrar abono">$</button>
                            <?php endif; ?>
                            <?php if ($canDeleteSale && ($sale['status'] ?? '') !== 'cancelled'): ?>
                                <form method="POST" action="<?php echo $ventasBase; ?>?action=cancel" style="display:inline;" data-ajax="true">
                                    <?php echo \SoftNova\Core\csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$sale['id']; ?>">
                                    <button type="submit" data-confirm="¿Eliminar esta venta? Se cancelará, se devolverá el stock y se ajustará la caja." class="btn btn-sm btn-danger" title="Eliminar venta"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php
            $pagination = $salesPagination ?? null;
            $paginationBaseUrl = $viewInstance->route('app/caja');
            $paginationQuery = [];
            $viewInstance->partial('pagination', compact('pagination', 'paginationBaseUrl', 'paginationQuery'));
            ?>
        <?php endif; ?>
    </div>
</div>

<div class="card neumorphic">
    <div class="card-header"><h3>📋 Movimientos del Día (<?php echo count($movements); ?>)</h3></div>
    <div class="card-body">
        <?php if (empty($movements)): ?>
            <p style="text-align:center;color:var(--color-text-secondary);padding:20px;">No hay movimientos</p>
        <?php else: ?>
            <div class="table-container"><table>
                <thead><tr><th>Hora</th><th>Tipo</th><th>Descripción</th><th>Monto</th></tr></thead>
                <tbody><?php foreach($movements as $mov): ?>
                    <tr><td><?php echo date('H:i',strtotime($mov['created_at'])); ?></td><td><span class="badge <?php echo $mov['type']==='income'?'badge-success':'badge-danger';?>"><?php echo $mov['type']==='income'?'Ingreso':'Egreso';?></span></td><td><?php echo htmlspecialchars($mov['description']);?></td><td style="color:<?php echo $mov['type']==='income'?'#10B981':'#DC2626';?>;font-weight:600;"><?php echo $mov['type']==='income'?'+':'-';?><?php echo fmt($mov['amount'],$currency);?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Gasto / Compra (alineado con módulos Gastos y Compras) -->
<div id="movementModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:560px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3>Registrar gasto o compra</h3>
            <button type="button" onclick="closeGastoCompraModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="gastoCompraChooser" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <button type="button" class="btn btn-primary" style="padding:18px;" onclick="showGastoForm()">Registrar gasto</button>
                <?php if ($canCompras): ?>
                    <a href="<?php echo $viewInstance->route('app/compras'); ?>" class="btn btn-secondary" style="padding:18px;text-align:center;display:flex;align-items:center;justify-content:center;">Ir a compras</a>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary" style="padding:18px;opacity:0.6;" disabled title="Sin permiso de Compras">Compras (sin acceso)</button>
                <?php endif; ?>
            </div>
            <?php if ($canGastos): ?>
            <p style="font-size:12px;margin:10px 0 0;">
                <a href="<?php echo $viewInstance->route('app/gastos'); ?>">Abrir módulo Gastos completo →</a>
            </p>
            <?php endif; ?>
            <p style="font-size:12px;color:var(--color-text-secondary);margin:12px 0 0;">
                Los gastos usan las mismas categorías y medios de pago del módulo Gastos. Si paga en efectivo, se registra el egreso en esta caja.
            </p>
            <div id="gastoFormWrap" style="display:none;margin-top:16px;">
                <button type="button" class="btn btn-sm btn-secondary" style="margin-bottom:12px;" onclick="showGastoChooser()">← Volver</button>
                <form method="POST" action="<?php echo $viewInstance->route('app/caja'); ?>?action=expense" data-ajax="true" enctype="multipart/form-data">
                    <?php echo \SoftNova\Core\csrf_field(); ?>
                    <input type="hidden" name="session_id" value="<?php echo (int)$openSession['id']; ?>">
                    <div class="form-group">
                        <label>Descripción *</label>
                        <input type="text" name="description" class="form-control" required placeholder="Ej. Comisión datáfono / arriendo">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label>Monto *</label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Fecha</label>
                            <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label>Categoría *</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">Seleccionar…</option>
                                <optgroup label="Gastos financieros">
                                    <?php foreach ($financialCats as $c): ?>
                                        <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Gastos operativos">
                                    <?php foreach ($operationalCats as $c): ?>
                                        <option value="<?php echo (int)$c['id']; ?>" <?php echo ($c['code'] ?? '') === 'general' ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Medio de pago</label>
                            <select name="payment_method" class="form-control">
                                <?php echo \SoftNova\Services\PaymentMethodCatalog::optionsHtml('cash'); ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Proveedor</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">Sin proveedor</option>
                            <?php foreach ($expenseSuppliers as $s): ?>
                                <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label>N° comprobante</label>
                            <input type="text" name="receipt_number" class="form-control" placeholder="Referencia">
                        </div>
                        <div class="form-group">
                            <label>Foto / PDF</label>
                            <input type="file" name="receipt_file" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notas</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="affect_cash" value="1" checked>
                            Ya pagado (si es efectivo, registra egreso en caja)
                        </label>
                    </div>
                    <div class="modal-footer" style="padding:0;margin-top:12px;">
                        <button type="button" class="btn btn-secondary" onclick="closeGastoCompraModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar gasto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openGastoCompraModal() {
    showGastoChooser();
    document.getElementById('movementModal').style.display = 'flex';
}
function closeGastoCompraModal() {
    document.getElementById('movementModal').style.display = 'none';
}
function showGastoChooser() {
    document.getElementById('gastoCompraChooser').style.display = 'grid';
    document.getElementById('gastoFormWrap').style.display = 'none';
}
function showGastoForm() {
    document.getElementById('gastoCompraChooser').style.display = 'none';
    document.getElementById('gastoFormWrap').style.display = 'block';
}
</script>

<div id="closeCashModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:450px;">
        <div class="modal-header"><h3>Cerrar Caja</h3><button onclick="document.getElementById('closeCashModal').style.display='none'" class="modal-close">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/caja'); ?>?action=close" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?><input type="hidden" name="session_id" value="<?php echo $openSession['id']; ?>">
            <div class="modal-body">
                <p style="margin-bottom:15px;color:var(--color-text-secondary);">Balance esperado: <strong style="color:#10B981;"><?php echo fmt($totals['balance'],$currency);?></strong></p>
                <div class="form-group"><label>Monto Final Contado (<?php echo $currency['symbol'];?>) *</label><input type="number" name="closing_amount" class="form-control" step="0.01" min="0" placeholder="0" required></div>
                <div class="form-group"><label>Notas de cierre</label><textarea name="notes" class="form-control" rows="2" placeholder="Observaciones..."></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="document.getElementById('closeCashModal').style.display='none'">Cancelar</button><button type="submit" class="btn btn-danger">Cerrar Caja</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Historial de cierres -->
<?php if ($isAdmin && !empty($historySessions)): ?>
<div class="card neumorphic" style="margin-top:20px;">
    <div class="card-header"><h3>📦 Historial de Cierres</h3></div>
    <div class="card-body">
        <div class="table-container"><table>
            <thead><tr><th>Apertura</th><th>Cierre</th><th>Usuario</th><th>Monto Ini.</th><th>Monto Fin</th><th>Dif.</th><th>PDF</th></tr></thead>
            <tbody><?php foreach($historySessions as $h): ?>
                <?php $diff = ($h['closing_amount']??0)-($h['opening_amount']??0); ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i',strtotime($h['opening_date']));?></td>
                    <td><?php echo date('d/m/Y H:i',strtotime($h['closing_date']));?></td>
                    <td><?php echo htmlspecialchars($h['user_name']);?></td>
                    <td><?php echo fmt($h['opening_amount'],$currency);?></td>
                    <td><?php echo fmt($h['closing_amount']??0,$currency);?></td>
                    <td style="color:<?php echo $diff>=0?'#10B981':'#DC2626';?>;"><?php echo ($diff>=0?'+':'').fmt($diff,$currency);?></td>
                    <td><a href="<?php echo $viewInstance->route('app/caja'); ?>?action=pdf&id=<?php echo $h['id']; ?>" class="btn btn-sm btn-info" target="_blank" title="Descargar PDF"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg></a></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
</div>
<?php endif; ?>

<!-- Modales compartidos con Ventas (detalle / abono / compartir / cliente rápido) -->
<div id="quickCustomerModal" class="modal-overlay" style="display:none;z-index:1100;">
    <div class="modal-content neumorphic" style="max-width:450px;">
        <div class="modal-header">
            <h3>Nuevo Cliente</h3>
            <button type="button" onclick="closeQuickCustomer()" class="modal-close">&times;</button>
        </div>
        <form method="POST" action="<?php echo $viewInstance->route('app/clientes'); ?>?action=create" id="quickCustomerForm" onsubmit="return submitQuickCustomer(this)">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="modal-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group"><label>Tipo Doc.</label><select name="document_type" class="form-control"><option value="CC">C.C</option><option value="CE">C.E</option><option value="NIT">NIT</option><option value="PPT">PPT</option><option value="OTROS">OTROS</option></select></div>
                    <div class="form-group"><label>N° Documento *</label><input type="text" name="document_number" class="form-control" placeholder="Número" required></div>
                    <div class="form-group"><label>Nombre *</label><input type="text" name="first_name" class="form-control" required placeholder="Nombres"></div>
                    <div class="form-group"><label>Apellido</label><input type="text" name="last_name" class="form-control" placeholder="Apellidos"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" placeholder="correo@ejemplo.com"></div>
                    <div class="form-group"><label>Teléfono *</label><input type="text" name="phone" class="form-control" placeholder="Teléfono" required></div>
                </div>
                <div class="form-group"><label>Dirección</label><textarea name="address" class="form-control" rows="2" placeholder="Dirección"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeQuickCustomer()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear y Seleccionar</button>
            </div>
        </form>
    </div>
</div>

<div id="paymentModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:400px;">
        <div class="modal-header"><h3>Registrar Abono</h3><button type="button" onclick="document.getElementById('paymentModal').style.display='none'" class="modal-close">&times;</button></div>
        <form method="POST" action="<?php echo $viewInstance->route('app/ventas'); ?>?action=payment" data-ajax="true">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="sale_id" id="paySaleId">
            <p id="payInfo" style="margin-bottom:10px;"></p>
            <div class="form-group"><label>Monto *</label><input type="number" name="amount" id="payAmount" class="form-control" step="0.01" min="0.01" required></div>
            <div class="form-group"><label>Método</label><select name="payment_method" class="form-control"><?php echo \SoftNova\Services\PaymentMethodCatalog::optionsHtml('cash', false); ?></select></div>
            <div class="form-group"><label>Notas</label><input type="text" name="notes" class="form-control" placeholder="Abono"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="document.getElementById('paymentModal').style.display='none'">Cancelar</button><button type="submit" class="btn btn-success">Registrar Abono</button></div>
        </form>
    </div>
</div>

<div id="saleDetailModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:650px;max-height:85vh;overflow-y:auto;">
        <div class="modal-header"><h3>Detalle de Venta</h3><button type="button" onclick="document.getElementById('saleDetailModal').style.display='none'" class="modal-close">&times;</button></div>
        <div class="modal-body" id="saleDetailContent"></div>
    </div>
</div>

<div id="shareModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:420px;">
        <div class="modal-header"><h3>Enviar recibo</h3><button type="button" class="modal-close" onclick="document.getElementById('shareModal').style.display='none'">&times;</button></div>
        <div class="modal-body">
            <a id="shareWhatsappBtn" href="#" target="_blank" class="btn btn-success" style="width:100%;margin-bottom:10px;">Abrir WhatsApp</a>
            <form method="POST" action="<?php echo $viewInstance->route('app/ventas'); ?>?action=email" data-ajax="true">
                <?php echo \SoftNova\Core\csrf_field(); ?>
                <input type="hidden" name="id" id="shareSaleId">
                <div class="form-group"><label>Correo</label><input type="email" name="email" id="shareEmail" class="form-control" required></div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Enviar por correo</button>
            </form>
        </div>
    </div>
</div>

<script>
    window.invRouteVentasDetail = '<?php echo $viewInstance->route('app/ventas'); ?>?action=detail';
    window.invRouteVentasShare = '<?php echo $viewInstance->route('app/ventas'); ?>?action=share';
</script>
