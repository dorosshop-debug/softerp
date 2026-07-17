<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

/**
 * Asistente IA para el tenant
 * Ayuda con productos, ventas, sugerencias
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
            $this->json(['reply' => 'Por favor, escribe un mensaje.']);
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
        
        // Datos del negocio para contexto
        $totalProducts = $this->query("SELECT COUNT(*) as c FROM products WHERE status='active'")->fetch()['c'];
        $totalSales = $this->query("SELECT COUNT(*) as c FROM sales WHERE status='completed'")->fetch()['c'];
        $todaySales = $this->query("SELECT COALESCE(SUM(total),0) as t FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'")->fetch()['t'];
        $lowStock = $this->query("SELECT COUNT(*) as c FROM products WHERE stock<=min_stock AND status='active' AND product_type='product'")->fetch()['c'];
        $topProducts = $this->query("SELECT p.name, COUNT(si.id) as cnt FROM sale_items si JOIN products p ON si.product_id=p.id GROUP BY p.id ORDER BY cnt DESC LIMIT 3")->fetchAll();
        
        // Respuestas contextuales
        if (str_contains($msg, 'hola') || str_contains($msg, 'buenos') || str_contains($msg, 'saludos')) {
            return "¡Hola! Soy el asistente virtual de EVA ERP. Tengo {$totalProducts} productos activos en tu inventario. Hoy has vendido \$" . number_format($todaySales, 0) . ". ¿En qué puedo ayudarte?";
        }
        
        if (str_contains($msg, 'producto') && (str_contains($msg, 'popular') || str_contains($msg, 'vende') || str_contains($msg, 'mejor'))) {
            if (empty($topProducts)) return "Aún no hay suficientes datos de ventas para determinar los productos más populares. ¡Empieza a vender!";
            $list = implode(', ', array_map(fn($p) => $p['name'] . " ({$p['cnt']} ventas)", $topProducts));
            return "Tus productos más vendidos son: {$list}. Considera promocionarlos en redes sociales con imágenes atractivas.";
        }
        
        if (str_contains($msg, 'stock') || str_contains($msg, 'inventario')) {
            return "Actualmente tienes {$totalProducts} productos activos. Hay {$lowStock} productos con stock bajo. Te recomiendo revisar el módulo de Inventario y reabastecer los que están en rojo.";
        }
        
        if (str_contains($msg, 'venta') || str_contains($msg, 'vendido') || str_contains($msg, 'hoy')) {
            return "Hoy has realizado ventas por \$" . number_format($todaySales, 0) . ". En total llevas {$totalSales} ventas completadas. ¡Sigue así!";
        }
        
        if (str_contains($msg, 'ayuda') || str_contains($msg, 'como') || str_contains($msg, 'ayudar')) {
            return "Puedo ayudarte con:\n📊 Consultar tus productos más vendidos\n📦 Revisar el estado del inventario\n💰 Ver tus ventas del día\n📸 Sugerencias para publicar en redes sociales\n\nSolo pregúntame lo que necesites.";
        }
        
        if (str_contains($msg, 'redes') || str_contains($msg, 'publicar') || str_contains($msg, 'instagram') || str_contains($msg, 'facebook')) {
            if (!empty($topProducts)) {
                $p = $topProducts[0];
                return "¡Buena idea! Tu producto estrella es '{$p['name']}' con {$p['cnt']} ventas. Te sugiero:\n📸 Tomar una foto con buena iluminación natural\n🎨 Usar colores de la marca EVA (verde + blanco)\n📝 Incluir precio y beneficios en la descripción\n🏷️ Usar hashtags: #EVATienda #Oferta #Calidad";
            }
            return "Para crear contenido atractivo para redes te sugiero:\n📸 Fotografías de tus productos con fondo blanco\n💰 Mostrar precios y promociones\n⭐ Destacar testimonios de clientes\n📅 Publicar consistente (3-4 veces por semana)";
        }
        
        if (str_contains($msg, 'gracias')) {
            return "¡De nada! Estoy aquí para ayudarte a hacer crecer tu negocio. ¿Necesitas algo más?";
        }
        
        // Respuesta genérica
        return "Entendido. Puedo ayudarte con informacion sobre tus productos ({$totalProducts} activos), ventas ({$totalSales} totales), inventario ({$lowStock} con stock bajo) o sugerencias para redes sociales. Te gustaria saber algo mas?";
    }
}
