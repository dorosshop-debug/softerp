<?php
$layout = 'tenant';
$title = 'Reportes - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Analíticas y Reportes';
$tenantName = $tenantName ?? 'Mi Empresa';
$userName = $userName ?? 'Usuario';
$currency = $currency ?? ['symbol'=>'$','decimals'=>0];
function fmtR(float $a, array $c): string { return $c['symbol'].' '.number_format($a, $c['decimals'], $c['decimal']??',', $c['thousands']??'.'); }
function trendArrow(float $pct): string { return $pct > 0 ? '↑' : ($pct < 0 ? '↓' : '→'); }
function trendColor(float $pct): string { return $pct > 0 ? '#10B981' : ($pct < 0 ? '#DC2626' : '#64748B'); }

// Preparar datos para charts
$salesMonthsLabels = []; $salesMonthsData = [];
foreach ($salesByMonth as $m) { $salesMonthsLabels[] = date('M Y', strtotime($m['month'].'-01')); $salesMonthsData[] = (float)$m['total']; }

$salesDaysLabels = []; $salesDaysData = [];
foreach ($salesByDay as $d) { $salesDaysLabels[] = date('d/m', strtotime($d['day'])); $salesDaysData[] = (float)$d['total']; }

$topProductsLabels = []; $topProductsData = []; $topProductsColors = [];
$colors = ['#0D7C4A','#10B981','#34D399','#6EE7B7','#A7F3D0','#059669','#047857','#065F46','#7C3AED','#8B5CF6'];
foreach ($topProducts as $i => $p) { $topProductsLabels[] = $p['name']; $topProductsData[] = (int)$p['total_qty']; $topProductsColors[] = $colors[$i % count($colors)]; }

// Rentabilidad
$profitLabels = []; $profitData = []; $profitColors = [];
foreach ($profitability as $i => $p) { $profitLabels[] = $p['name']; $profitData[] = (float)$p['profit']; $profitColors[] = $p['profit'] >= 0 ? '#10B981' : '#DC2626'; }

// Categorías
$catLabels = []; $catData = [];
foreach ($salesByCategory as $cat) { $catLabels[] = $cat['category']; $catData[] = (float)$cat['total']; }

$topCustLabels = []; $topCustData = [];
foreach ($topCustomers as $c) { $topCustLabels[] = $c['customer_name']; $topCustData[] = (int)$c['purchase_count']; }

$payMethodLabels = []; $payMethodData = [];
$payNames = ['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia','credit'=>'Crédito','other'=>'Otro'];
foreach ($paymentMethods as $pm) { $payMethodLabels[] = $payNames[$pm['payment_method']] ?? $pm['payment_method']; $payMethodData[] = (int)$pm['count']; }

$trendLabels = []; $trendCurrent = []; $trendLast = [];
foreach ($productTrend as $t) { $trendLabels[] = $t['name']; $trendCurrent[] = (int)$t['current_month']; $trendLast[] = (int)$t['last_month']; }
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php echo flashMessage(); ?>

<!-- Filtros de Fecha + Exportar -->
<div class="card neumorphic" style="margin-bottom:20px;">
    <div class="card-body" style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;padding:15px 20px;">
        <form method="GET" action="<?php echo $viewInstance->route('app/reportes'); ?>" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;flex:1;">
            <label style="font-weight:600;font-size:13px;">📅 Período:</label>
            <input type="date" name="from" value="<?php echo $dateFrom; ?>" class="form-control" style="width:160px;" title="Fecha desde">
            <span style="color:var(--color-text-secondary);">hasta</span>
            <input type="date" name="to" value="<?php echo $dateTo; ?>" class="form-control" style="width:160px;" title="Fecha hasta">
            <button type="submit" class="btn btn-primary">🔍 Aplicar Filtro</button>
            <a href="<?php echo $viewInstance->route('app/reportes'); ?>" class="btn btn-secondary" style="font-size:12px;">↻ Reset</a>
        </form>
        <div style="display:flex;gap:8px;">
            <button onclick="exportPDF()" class="btn btn-secondary" style="font-size:12px;" title="Imprimir / Guardar PDF"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 12H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> PDF</button>
        </div>
    </div>
</div>

<!-- KPIs con tendencias -->
<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card neumorphic">
        <h4>💰 Ventas Totales</h4>
        <div class="stat-value" style="color:#10B981;"><?php echo fmtR($totalSales, $currency); ?></div>
        <small><?php echo $totalSalesCount; ?> ventas</small>
        <div style="margin-top:6px;font-size:12px;color:<?php echo trendColor($salesTrend); ?>;">
            <?php echo trendArrow($salesTrend); ?> <?php echo abs($salesTrend); ?>% vs período anterior
        </div>
    </div>
    <div class="stat-card neumorphic">
        <h4>📦 Productos</h4>
        <div class="stat-value"><?php echo $totalProducts; ?></div>
        <small><?php echo $lowStock; ?> con stock bajo</small>
    </div>
    <div class="stat-card neumorphic">
        <h4>👥 Clientes</h4>
        <div class="stat-value"><?php echo $totalCustomers; ?></div>
        <small>activos</small>
    </div>
    <div class="stat-card neumorphic">
        <h4>📊 Ticket Promedio</h4>
        <div class="stat-value" style="color:#7C3AED;"><?php echo $totalSalesCount > 0 ? fmtR($avgTicket, $currency) : '$0'; ?></div>
        <small>por venta</small>
        <div style="margin-top:6px;font-size:12px;color:<?php echo trendColor($ticketTrend); ?>;">
            <?php echo trendArrow($ticketTrend); ?> <?php echo abs($ticketTrend); ?>% vs período anterior
        </div>
    </div>
</div>

<div class="reports-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(480px, 1fr));gap:20px;">

    <!-- Ventas por Mes -->
    <div class="card neumorphic">
        <div class="card-header"><h3>📈 Ventas por Mes (12 meses)</h3></div>
        <div class="card-body"><canvas id="chartSalesMonth" height="280"></canvas></div>
    </div>

    <!-- Ventas por Día -->
    <div class="card neumorphic">
        <div class="card-header"><h3>📉 Ventas Diarias (<?php echo date('d/m', strtotime($dateFrom)); ?> - <?php echo date('d/m', strtotime($dateTo)); ?>)</h3></div>
        <div class="card-body"><canvas id="chartSalesDay" height="280"></canvas></div>
    </div>

    <!-- Rentabilidad -->
    <div class="card neumorphic">
        <div class="card-header"><h3>💎 Productos Más Rentables</h3></div>
        <div class="card-body"><canvas id="chartProfit" height="280"></canvas></div>
    </div>

    <!-- Top Productos -->
    <div class="card neumorphic">
        <div class="card-header"><h3>🏆 Productos Más Vendidos</h3></div>
        <div class="card-body"><canvas id="chartTopProducts" height="280"></canvas></div>
    </div>

    <!-- Ventas por Categoría -->
    <div class="card neumorphic">
        <div class="card-header"><h3>📂 Ventas por Categoría</h3></div>
        <div class="card-body"><canvas id="chartCategories" height="280"></canvas></div>
    </div>

    <!-- Clientes Frecuentes -->
    <div class="card neumorphic">
        <div class="card-header"><h3>⭐ Clientes Frecuentes</h3></div>
        <div class="card-body"><canvas id="chartTopCustomers" height="280"></canvas></div>
    </div>

    <!-- Métodos de Pago -->
    <div class="card neumorphic">
        <div class="card-header"><h3>💳 Métodos de Pago</h3></div>
        <div class="card-body"><canvas id="chartPayMethods" height="280"></canvas></div>
    </div>

    <!-- Tendencia de Productos -->
    <div class="card neumorphic">
        <div class="card-header"><h3>📊 Tendencia: Este Mes vs Mes Anterior</h3></div>
        <div class="card-body"><canvas id="chartTrend" height="280"></canvas></div>
    </div>

    <!-- Stock Bajo -->
    <div class="card neumorphic">
        <div class="card-header"><h3>⚠️ Productos con Stock Bajo</h3></div>
        <div class="card-body">
            <?php if (empty($lowStockProducts)): ?>
                <p style="text-align:center;color:var(--color-text-secondary);padding:20px;">✅ Todos los productos tienen stock suficiente</p>
            <?php else: ?>
                <div class="table-container"><table>
                    <thead><tr><th>Producto</th><th>Stock</th><th>Mínimo</th><th>Faltante</th><th>Precio Venta</th></tr></thead>
                    <tbody>
                    <?php foreach ($lowStockProducts as $lp): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lp['name']); ?></strong></td>
                            <td><span class="badge badge-danger"><?php echo $lp['stock']; ?></span></td>
                            <td><?php echo $lp['min_stock']; ?></td>
                            <td style="color:var(--color-error);font-weight:600;"><?php echo $lp['min_stock'] - $lp['stock']; ?></td>
                            <td><?php echo fmtR($lp['sale_price'], $currency); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Trazabilidad -->
    <div class="card neumorphic">
        <div class="card-header"><h3>🔍 Trazabilidad - Últimos Movimientos</h3></div>
        <div class="card-body">
            <?php if (empty($stockMovements)): ?>
                <p style="text-align:center;color:var(--color-text-secondary);padding:20px;">Sin movimientos registrados</p>
            <?php else: ?>
                <div class="table-container" style="max-height:350px;overflow-y:auto;"><table>
                    <thead><tr><th>Producto</th><th>Tipo</th><th>Cant</th><th>Ref</th><th>Fecha</th></tr></thead>
                    <tbody>
                    <?php foreach ($stockMovements as $sm): 
                        $typeLabel = $sm['type'] === 'in' ? 'Entrada' : ($sm['type'] === 'out' ? 'Salida' : 'Ajuste');
                        $typeClass = $sm['type'] === 'in' ? 'badge-success' : ($sm['type'] === 'out' ? 'badge-danger' : 'badge-warning');
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sm['product_name']); ?></td>
                            <td><span class="badge <?php echo $typeClass; ?>"><?php echo $typeLabel; ?></span></td>
                            <td><?php echo $sm['quantity']; ?></td>
                            <td style="font-size:12px;"><?php echo htmlspecialchars($sm['reference_type'] ?? ''); ?></td>
                            <td style="font-size:12px;"><?php echo date('d/m/Y H:i', strtotime($sm['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
@media print {
    .sidebar, .header, .app-footer, .breadcrumbs, .btn, form, .ai-suggestions, #sidebar { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .reports-grid { grid-template-columns: 1fr 1fr !important; }
    .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd !important; }
    body { background: #fff !important; }
}
</style>

<script>
function exportPDF() { window.print(); }

(function() {
    var isDark = document.body.classList.contains('dark-mode');
    var gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    var textColor = isDark ? '#94A3B8' : '#64748B';

    // Ventas por Mes
    new Chart(document.getElementById('chartSalesMonth'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($salesMonthsLabels); ?>,
            datasets: [{
                label: 'Ventas',
                data: <?php echo json_encode($salesMonthsData); ?>,
                backgroundColor: 'rgba(13,124,74,0.7)',
                borderColor: '#0D7C4A',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, callback: function(v) { return '<?php echo $currency['symbol']; ?>' + v.toLocaleString(); } } },
                x: { grid: { display: false }, ticks: { color: textColor } }
            }
        }
    });

    // Ventas por Día
    new Chart(document.getElementById('chartSalesDay'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($salesDaysLabels); ?>,
            datasets: [{
                label: 'Ventas',
                data: <?php echo json_encode($salesDaysData); ?>,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16,185,129,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, callback: function(v) { return '<?php echo $currency['symbol']; ?>' + v.toLocaleString(); } } },
                x: { grid: { display: false }, ticks: { color: textColor, maxTicksLimit: 12 } }
            }
        }
    });

    // Rentabilidad
    new Chart(document.getElementById('chartProfit'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($profitLabels); ?>,
            datasets: [{
                label: 'Ganancia Neta',
                data: <?php echo json_encode($profitData); ?>,
                backgroundColor: <?php echo json_encode($profitColors); ?>,
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: { callbacks: { label: function(ctx) { return '<?php echo $currency['symbol']; ?>' + ctx.raw.toLocaleString(); } } }
            },
            scales: {
                x: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, callback: function(v) { return '<?php echo $currency['symbol']; ?>' + v.toLocaleString(); } } },
                y: { grid: { display: false }, ticks: { color: textColor } }
            }
        }
    });

    // Top Productos
    new Chart(document.getElementById('chartTopProducts'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($topProductsLabels); ?>,
            datasets: [{
                label: 'Unidades Vendidas',
                data: <?php echo json_encode($topProductsData); ?>,
                backgroundColor: <?php echo json_encode($topProductsColors); ?>,
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor } },
                y: { grid: { display: false }, ticks: { color: textColor } }
            }
        }
    });

    // Ventas por Categoría
    new Chart(document.getElementById('chartCategories'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($catLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($catData); ?>,
                backgroundColor: ['#0D7C4A','#10B981','#34D399','#7C3AED','#F59E0B','#3B82F6','#EF4444','#EC4899','#8B5CF6','#06B6D4'],
                borderWidth: 2,
                borderColor: isDark ? '#1E293B' : '#fff'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: textColor, padding: 15 } },
                tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': <?php echo $currency['symbol']; ?>' + ctx.raw.toLocaleString(); } } }
            }
        }
    });

    // Clientes Frecuentes
    new Chart(document.getElementById('chartTopCustomers'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($topCustLabels); ?>,
            datasets: [{
                label: 'Compras',
                data: <?php echo json_encode($topCustData); ?>,
                backgroundColor: 'rgba(124,58,237,0.7)',
                borderColor: '#7C3AED',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, stepSize: 1 } },
                y: { grid: { display: false }, ticks: { color: textColor } }
            }
        }
    });

    // Métodos de Pago
    new Chart(document.getElementById('chartPayMethods'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($payMethodLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($payMethodData); ?>,
                backgroundColor: ['#0D7C4A','#7C3AED','#F59E0B','#3B82F6','#EF4444'],
                borderWidth: 2,
                borderColor: isDark ? '#1E293B' : '#fff'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { color: textColor, padding: 15 } } }
        }
    });

    // Tendencia
    new Chart(document.getElementById('chartTrend'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($trendLabels); ?>,
            datasets: [
                { label: 'Mes Actual', data: <?php echo json_encode($trendCurrent); ?>, backgroundColor: 'rgba(13,124,74,0.7)', borderRadius: 4 },
                { label: 'Mes Anterior', data: <?php echo json_encode($trendLast); ?>, backgroundColor: 'rgba(124,58,237,0.4)', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: textColor } } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor } },
                x: { grid: { display: false }, ticks: { color: textColor } }
            }
        }
    });
})();
</script>