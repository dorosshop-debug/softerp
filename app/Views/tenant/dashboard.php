<?php
$layout = 'tenant';
$title = 'Dashboard - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Dashboard';
$userName = $userName ?? 'Usuario';
$tenantName = $tenantName ?? 'Mi Empresa';
$stats = $stats ?? [];
$recentSales = $recentSales ?? [];

function formatCurrencyDashboard(?float $amount): string {
    return '$' . number_format($amount ?? 0, 2);
}
?>

<div style="margin-bottom: 20px;">
    <h2 style="color: var(--color-primary);">Bienvenido, <?php echo htmlspecialchars($userName); ?></h2>
    <p style="color: var(--color-text-secondary);"><?php echo htmlspecialchars($tenantName); ?></p>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<!-- KPIs -->
<div class="stats-grid">
    <div class="stat-card neumorphic">
        <h4>Productos</h4>
        <div class="stat-value"><?php echo number_format($stats['total_products'] ?? 0); ?></div>
    </div>
    <div class="stat-card neumorphic">
        <h4>Clientes</h4>
        <div class="stat-value"><?php echo number_format($stats['total_customers'] ?? 0); ?></div>
    </div>
    <div class="stat-card neumorphic">
        <h4>Ventas Hoy</h4>
        <div class="stat-value" style="color: #10B981;"><?php echo formatCurrencyDashboard($stats['today_sales'] ?? 0); ?></div>
    </div>
    <div class="stat-card neumorphic">
        <h4>Stock Bajo</h4>
        <div class="stat-value" style="color: <?php echo ($stats['low_stock'] ?? 0) > 0 ? '#DC2626' : '#10B981'; ?>;">
            <?php echo number_format($stats['low_stock'] ?? 0); ?>
        </div>
    </div>
</div>

<!-- Ventas recientes -->
<div class="card neumorphic">
    <div class="card-header">
        <h3>📋 Últimas Ventas</h3>
    </div>
    <div class="card-body">
        <?php if (empty($recentSales)): ?>
            <p style="text-align: center; color: var(--color-text-secondary); padding: 20px;">
                No hay ventas registradas aún
            </p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sale['invoice_number']); ?></td>
                                <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Consumidor Final'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sale['sale_date'])); ?></td>
                                <td><?php echo formatCurrencyDashboard($sale['total']); ?></td>
                                <td>
                                    <span class="badge <?php echo $sale['payment_status'] === 'paid' ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo $sale['payment_status'] === 'paid' ? 'Pagado' : 'Pendiente'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
