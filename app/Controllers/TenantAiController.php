<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

/**
 * Asistente IA "Seri" para el tenant
 * Proporciona análisis de negocio, recomendaciones y respuestas contextuales
 */
class TenantAiController extends Controller
{
    private \PDO $db;
    
    public function __construct()
    {
        parent::__construct();
        TenantMiddleware::auth();
        $this->db = TenantMiddleware::getDb();
    }
    
    private function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function index(): void
    {
        $this->view('tenant.ai', [
            'tenantName' => $_SESSION['tenant_name'] ?? 'Mi Empresa',
            'userName' => $_SESSION['tenant_user_name'] ?? 'Usuario',
        ]);
    }
    
    /**
     * Endpoint AJAX para el chat de IA
     */
    public function chat(): void
    {
        $message = trim($this->request->post('message') ?? '');
        if (empty($message)) {
            $this->json(['reply' => 'Por favor, escribe un mensaje para que pueda ayudarte.']);
            return;
        }
        
        $reply = $this->generateReply($message);
        $this->json(['reply' => $reply]);
    }
    
    /**
     * Genera respuesta basada en el contexto del negocio
     */
    private function generateReply(string $message): string
    {
        $msg = strtolower($message);
        
        // Contexto completo del negocio
        $totalProducts = (int)$this->query("SELECT COUNT(*) as c FROM products WHERE status='active'")->fetch()['c'];
        $totalServices = (int)$this->query("SELECT COUNT(*) as c FROM products WHERE status='active' AND product_type='service'")->fetch()['c'];
        $totalCustomers = (int)$this->query("SELECT COUNT(*) as c FROM customers WHERE status='active'")->fetch()['c'];
        $totalSales = (int)$this->query("SELECT COUNT(*) as c FROM sales WHERE status='completed'")->fetch()['c'];
        $todaySales = (float)$this->query("SELECT COALESCE(SUM(total),0) as t FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'")->fetch()['t'];
        $monthSales = (float)$this->query("SELECT COALESCE(SUM(total),0) as t FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE()) AND status='completed'")->fetch()['t'];
        $lowStock = (int)$this->query("SELECT COUNT(*) as c FROM products WHERE stock<=min_stock AND status='active' AND product_type='product'")->fetch()['c'];
        $pendingPayments = (float)$this->query("SELECT COALESCE(SUM(total),0) as t FROM sales WHERE payment_status IN ('pending','partial') AND status='completed'")->fetch()['t'];
        
        $topProducts = $this->query("SELECT p.name, SUM(si.quantity) as cnt, SUM(si.subtotal) as total FROM sale_items si JOIN products p ON si.product_id=p.id JOIN sales s ON si.sale_id=s.id WHERE s.status='completed' GROUP BY p.id ORDER BY cnt DESC LIMIT 5")->fetchAll();
        
        $lowStockProducts = $this->query("SELECT name, stock, min_stock FROM products WHERE stock<=min_stock AND status='active' AND product_type='product' ORDER BY (min_stock-stock) DESC LIMIT 5")->fetchAll();
        
        $bestDay = $this->query("SELECT DAYNAME(sale_date) as dayname, COALESCE(SUM(total),0) as t FROM sales WHERE status='completed' AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY DAYNAME(sale_date) ORDER BY t DESC LIMIT 1")->fetch();
        
        $topCustomers = $this->query("SELECT COALESCE(CONCAT(c.first_name,' ',c.last_name),c.name) as name, COUNT(s.id) as cnt, COALESCE(SUM(s.total),0) as total FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE s.status='completed' AND s.customer_id IS NOT NULL GROUP BY c.id ORDER BY cnt DESC LIMIT 3")->fetchAll();
        
        $recentNewCustomers = (int)$this->query("SELECT COUNT(*) as c FROM customers WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch()['c'];
        
        // === RESPUESTAS CONTEXTUALES ===
        
        // Saludos
        if (preg_match('/hola|buenos|saludos|hey|buenas/i', $msg)) {
            $greetings = [
                "¡Hola! 👋 Soy Seri, tu asistente virtual. Actualmente tienes {$totalProducts} productos, {$totalCustomers} clientes y hoy llevas \$" . number_format($todaySales, 0) . " en ventas. ¿En qué puedo ayudarte?",
                "¡Buen día! ☀️ Soy Seri. Veo que tienes {$totalProducts} productos activos y {$lowStock} necesitan reabastecerse. ¿Qué te gustaría revisar?",
                "¡Hey! 🤖 Seri al habla. Hoy has vendido \$" . number_format($todaySales, 0) . " y tienes {$totalCustomers} clientes registrados. ¿Cómo puedo asistirte?"
            ];
            return $greetings[array_rand($greetings)];
        }
        
        // Productos populares / más vendidos
        if (preg_match('/producto.*(popular|vende|mejor|estrella|top)|(popular|vende|mejor|estrella|top).*producto/i', $msg)) {
            if (empty($topProducts)) return "Aún no hay suficientes datos de ventas. ¡Empieza a vender y podré darte mejores insights! 📊";
            $list = [];
            foreach ($topProducts as $i => $p) {
                $medal = $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '📌'));
                $list[] = "{$medal} {$p['name']} — {$p['cnt']} unidades (\$" . number_format($p['total'], 0) . ")";
            }
            $bestDayName = $bestDay ? $this->dayNameEs($bestDay['dayname']) : 'desconocido';
            return "🏆 Tus productos estrella:\n\n" . implode("\n", $list) . "\n\n💡 Tip: Tu mejor día de ventas suele ser el {$bestDayName}. ¡Aprovecha para promocionar!";
        }
        
        // Inventario / stock
        if (preg_match('/stock|inventario|existencia|reabastecer|faltante/i', $msg)) {
            $base = "📦 Tienes {$totalProducts} productos activos";
            if ($totalServices > 0) $base .= " ({$totalServices} servicios)";
            $base .= ".\n\n";
            
            if ($lowStock > 0 && !empty($lowStockProducts)) {
                $base .= "⚠️ {$lowStock} productos con stock bajo:\n";
                foreach ($lowStockProducts as $lp) {
                    $base .= "• {$lp['name']} — Stock: {$lp['stock']} (mín: {$lp['min_stock']}) → Faltan " . ($lp['min_stock'] - $lp['stock']) . " unidades\n";
                }
                $base .= "\n🔔 Te recomiendo generar órdenes de compra para estos productos.";
            } else {
                $base .= "✅ Todos los productos tienen stock suficiente. ¡Buen trabajo!";
            }
            return $base;
        }
        
        // Ventas / facturación
        if (preg_match('/venta|vendido|factur|ingreso|ganancia|revenue/i', $msg)) {
            $avgTicket = $totalSales > 0 ? $monthSales / $totalSales : 0;
            return "💰 Resumen de ventas:\n\n"
                . "📅 Hoy: \$" . number_format($todaySales, 0) . "\n"
                . "📆 Este mes: \$" . number_format($monthSales, 0) . "\n"
                . "📊 Total histórico: {$totalSales} ventas\n"
                . "🎫 Ticket promedio: \$" . number_format($avgTicket, 0) . "\n"
                . "💳 Pagos pendientes: \$" . number_format($pendingPayments, 0) . "\n\n"
                . ($pendingPayments > 0 ? "⚠️ Tienes cobros pendientes. Revisa el módulo de Ventas para gestionarlos." : "✅ No tienes pagos pendientes. ¡Excelente!");
        }
        
        // Clientes
        if (preg_match('/cliente|comprador|frecuente|fidel/i', $msg)) {
            $base = "👥 Tienes {$totalCustomers} clientes activos";
            if ($recentNewCustomers > 0) $base .= " ({$recentNewCustomers} nuevos este mes)";
            $base .= ".\n\n";
            
            if (!empty($topCustomers)) {
                $base .= "⭐ Clientes más frecuentes:\n";
                foreach ($topCustomers as $c) {
                    $base .= "• {$c['name']} — {$c['cnt']} compras (\$" . number_format($c['total'], 0) . ")\n";
                }
                $base .= "\n💡 Considera crear un programa de fidelización para tus mejores clientes.";
            }
            return $base;
        }
        
        // Ayuda / comandos
        if (preg_match('/ayuda|como|ayudar|que.*hace|comando|funcion/i', $msg)) {
            return "🤖 Soy Seri, tu asistente de negocio. Puedo ayudarte con:\n\n"
                . "📊 «productos más vendidos» — Ver tu top de productos\n"
                . "📦 «estado del inventario» — Revisar stock y faltantes\n"
                . "💰 «cómo van las ventas» — Resumen de facturación\n"
                . "👥 «quiénes son mis clientes» — Análisis de clientes\n"
                . "📸 «ideas para redes sociales» — Sugerencias de marketing\n"
                . "📈 «recomendaciones» — Consejos para mejorar tu negocio\n"
                . "💡 «tendencias» — Qué está funcionando mejor\n\n"
                . "Solo pregúntame lo que necesites. ¡Estoy aquí para ayudarte!";
        }
        
        // Redes sociales / marketing
        if (preg_match('/redes|publicar|instagram|facebook|marketing|promocion|whatsapp/i', $msg)) {
            if (!empty($topProducts)) {
                $p = $topProducts[0];
                return "📸 Estrategia de marketing para «{$p['name']}» (tu producto estrella con {$p['cnt']} ventas):\n\n"
                    . "🎨 Visual: Foto con buena iluminación natural, fondo limpio\n"
                    . "📝 Copy: Destaca beneficios y precio especial\n"
                    . "🏷️ Hashtags: #SeriTienda #Oferta #Emprende #Calidad\n"
                    . "⏰ Mejor hora: 11am-1pm y 7pm-9pm\n"
                    . "📅 Frecuencia: 3-4 posts por semana\n\n"
                    . "💡 ¿Quieres que te ayude con el texto para una publicación?";
            }
            return "📸 Tips de marketing digital:\n\n"
                . "• 📷 Fotos de productos con fondo blanco venden 40% más\n"
                . "• 💰 Muestra precios claros (la gente no pregunta si ve el precio)\n"
                . "• ⭐ Comparte testimonios de clientes reales\n"
                . "• 🎯 Usa Instagram Stories para ofertas flash\n"
                . "• 📅 Publica de forma consistente (mínimo 3 veces/semana)\n\n"
                . "¿Quieres ideas específicas para algún producto?";
        }
        
        // Recomendaciones / consejos
        if (preg_match('/recomend|consejo|mejora|sugerencia|optimizar/i', $msg)) {
            $tips = ["📈 Recomendaciones para tu negocio:\n\n"];
            
            if ($lowStock > 0) {
                $tips[] = "🔴 Urgente: Reabastece {$lowStock} productos con stock bajo. Ve a Inventario → ordenar por stock.";
            }
            if ($pendingPayments > 0) {
                $tips[] = "💳 Tienes \$" . number_format($pendingPayments, 0) . " en pagos pendientes. Gestiona los cobros en Ventas.";
            }
            if ($recentNewCustomers === 0 && $totalCustomers > 0) {
                $tips[] = "👥 No has tenido clientes nuevos este mes. Considera una promoción para atraer nuevos compradores.";
            }
            if ($totalCustomers < 10) {
                $tips[] = "📋 Tienes pocos clientes registrados. Registra a todos tus compradores para hacer seguimiento y fidelización.";
            }
            if (empty($topProducts)) {
                $tips[] = "📊 Aún no hay datos de ventas. Empieza a registrar todas tus transacciones para obtener análisis.";
            }
            
            $tips[] = "\n✨ Dato: Las empresas que usan datos para decidir crecen 30% más rápido. ¡Revisa tus reportes regularmente!";
            
            return implode("\n", $tips);
        }
        
        // Tendencias
        if (preg_match('/tendencia|crecimiento|proyeccion|futuro|pronostico/i', $msg)) {
            $bestDayName = $bestDay ? $this->dayNameEs($bestDay['dayname']) : 'desconocido';
            $monthTrend = $this->query("SELECT COALESCE(SUM(total),0) as t FROM sales WHERE status='completed' AND MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())")->fetch()['t'];
            $lastMonthTrend = $this->query("SELECT COALESCE(SUM(total),0) as t FROM sales WHERE status='completed' AND MONTH(sale_date)=MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(sale_date)=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))")->fetch()['t'];
            
            $trendMsg = "📊 Análisis de tendencias:\n\n"
                . "📅 Mejor día: {$bestDayName}\n"
                . "📆 Este mes: \$" . number_format($monthTrend, 0) . "\n"
                . "📆 Mes anterior: \$" . number_format($lastMonthTrend, 0) . "\n";
            
            if ($lastMonthTrend > 0) {
                $change = (($monthTrend - $lastMonthTrend) / $lastMonthTrend) * 100;
                $arrow = $change >= 0 ? '📈' : '📉';
                $trendMsg .= "{$arrow} Variación: " . ($change >= 0 ? '+' : '') . number_format($change, 1) . "%\n";
            }
            
            if (!empty($topProducts)) {
                $trendMsg .= "\n🏆 Lo que más está funcionando:\n";
                foreach (array_slice($topProducts, 0, 3) as $p) {
                    $trendMsg .= "• {$p['name']} — {$p['cnt']} unidades\n";
                }
            }
            
            $trendMsg .= "\n💡 Recomendación: " . ($change >= 0 ? '¡Vas por buen camino! Sigue impulsando lo que funciona.' : 'Es momento de probar nuevas estrategias. Revisa tus reportes para identificar oportunidades.');
            
            return $trendMsg;
        }
        
        // Agradecimiento
        if (preg_match('/gracias|thank|te agradezco/i', $msg)) {
            return "¡De nada! 😊 Estoy aquí 24/7 para ayudarte a hacer crecer tu negocio. ¿Necesitas algo más?";
        }
        
        // Sobre Seri
        if (preg_match('/quien.*eres|como.*llamas|nombre|seri/i', $msg)) {
            return "🤖 Soy Seri, el asistente virtual de Seri ERP. Fui creado para ayudarte a gestionar y hacer crecer tu negocio.\n\n"
                . "Puedo analizar tus ventas, monitorear tu inventario, identificar tendencias, sugerir estrategias de marketing y mucho más.\n\n"
                . "Actualmente monitoreo {$totalProducts} productos y {$totalCustomers} clientes en tu sistema. ¡Pregúntame lo que necesites!";
        }
        
        // Respuesta genérica inteligente
        $genericResponses = [
            "Interesante pregunta. Actualmente tienes {$totalProducts} productos, {$totalCustomers} clientes y \$" . number_format($todaySales, 0) . " en ventas hoy. ¿Te gustaría que analice algo específico?",
            "Entendido. Puedo ayudarte con análisis de ventas, inventario, clientes, tendencias y marketing. ¿Qué área te interesa más?",
            "¡Buena pregunta! Para darte una mejor respuesta, ¿podrías ser más específico? Por ejemplo: «¿cuáles son mis productos más vendidos?» o «¿cómo está mi inventario?»"
        ];
        
        return $genericResponses[array_rand($genericResponses)];
    }
    
    private function dayNameEs(string $englishDay): string
    {
        return match (strtolower($englishDay)) {
            'monday' => 'Lunes', 'tuesday' => 'Martes', 'wednesday' => 'Miércoles',
            'thursday' => 'Jueves', 'friday' => 'Viernes', 'saturday' => 'Sábado',
            'sunday' => 'Domingo', default => $englishDay,
        };
    }
}
