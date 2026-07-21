<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

/**
 * Controlador del módulo Cotizaciones
 * Permite crear cotizaciones y convertirlas a ventas
 */
class TenantCotizacionesController extends Controller
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
    
    private function registerCashMovement(float $amount, string $description, string $referenceType = 'sale', ?int $referenceId = null): void
    {
        try {
            $openSession = $this->query(
                "SELECT id FROM cash_sessions WHERE status = 'open' ORDER BY opening_date DESC LIMIT 1"
            )->fetch();
            
            if ($openSession) {
                $userId = $_SESSION['tenant_user_id'] ?? null;
                
                // Verificar si la columna user_id existe en cash_movements
                $hasUserId = true;
                try {
                    $this->query("SELECT user_id FROM cash_movements LIMIT 0");
                } catch (\Exception $e) {
                    $hasUserId = false;
                }
                
                if ($hasUserId && $userId) {
                    $this->query(
                        "INSERT INTO cash_movements (cash_session_id, type, amount, description, reference_type, reference_id, user_id, created_at)
                         VALUES (?, 'income', ?, ?, ?, ?, ?, NOW())",
                        [$openSession['id'], $amount, $description, $referenceType, $referenceId, $userId]
                    );
                } else {
                    $this->query(
                        "INSERT INTO cash_movements (cash_session_id, type, amount, description, reference_type, reference_id, created_at)
                         VALUES (?, 'income', ?, ?, ?, ?, NOW())",
                        [$openSession['id'], $amount, $description, $referenceType, $referenceId]
                    );
                }
            }
        } catch (\Exception $e) {
            // No interrumpir el flujo principal si falla el registro en caja
            error_log('registerCashMovement error: ' . $e->getMessage());
        }
    }
    
    private function getCurrency(): array
    {
        $c = $this->query("SELECT setting_value FROM settings WHERE setting_key='currency'")->fetch();
        $code = $c['setting_value'] ?? 'COP';
        return ['COP'=>['symbol'=>'$','decimals'=>0,'thousands'=>'.','decimal'=>','],'USD'=>['symbol'=>'US$','decimals'=>2,'thousands'=>',','decimal'=>'.'],'EUR'=>['symbol'=>'€','decimals'=>2,'thousands'=>'.','decimal'=>',']][$code]??['symbol'=>'$','decimals'=>0,'thousands'=>'.','decimal'=>','];
    }
    
    public function index(): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'create' && $this->request->method() === 'POST') { $this->createQuote(); return; }
        if ($action === 'detail' && $this->request->method() === 'GET') { $this->detail(); return; }
        if ($action === 'delete' && $this->request->method() === 'POST') { $this->deleteQuote(); return; }
        if ($action === 'convert' && $this->request->method() === 'POST') { $this->convertToSale(); return; }
        
        // Crear tabla de cotizaciones si no existe
        $this->ensureTable();
        
        $quotes = $this->query(
            "SELECT q.*, c.name as customer_name, u.name as user_name
             FROM quotes q LEFT JOIN customers c ON q.customer_id=c.id LEFT JOIN users u ON q.user_id=u.id
             ORDER BY q.created_at DESC LIMIT 50"
        )->fetchAll();
        
        $products = $this->query("SELECT id, name, sale_price, stock FROM products WHERE status='active' ORDER BY name")->fetchAll();
        $customers = $this->query("SELECT id, name, first_name, last_name FROM customers WHERE status='active' ORDER BY name")->fetchAll();
        
        $this->view('tenant.cotizaciones', [
            'quotes'=>$quotes,'products'=>$products,'customers'=>$customers,
            'currency'=>$this->getCurrency(),'tenantName'=>$_SESSION['tenant_name']??'Mi Empresa','userName'=>$_SESSION['tenant_user_name']??'Usuario',
        ]);
    }
    
    private function ensureTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS quotes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quote_number VARCHAR(50) NOT NULL UNIQUE,
            customer_id INT UNSIGNED,
            user_id INT UNSIGNED,
            quote_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            subtotal DECIMAL(12,2) DEFAULT 0,
            tax DECIMAL(12,2) DEFAULT 0,
            total DECIMAL(12,2) DEFAULT 0,
            status ENUM('pending','accepted','rejected','converted') DEFAULT 'pending',
            notes TEXT,
            valid_until DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_customer(customer_id), INDEX idx_status(status),
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $this->db->exec("CREATE TABLE IF NOT EXISTS quote_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quote_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED,
            product_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) DEFAULT 0,
            subtotal DECIMAL(12,2) DEFAULT 0,
            INDEX idx_quote(quote_id),
            FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    public function detail(): void
    {
        $id = (int)$this->request->get('id');
        $quote = $this->query("SELECT q.*, c.name as customer_name FROM quotes q LEFT JOIN customers c ON q.customer_id=c.id WHERE q.id=?",[$id])->fetch();
        if(!$quote){$this->json(['error'=>'Cotización no encontrada']);return;}
        $items = $this->query("SELECT * FROM quote_items WHERE quote_id=?",[$id])->fetchAll();
        $this->json(['quote'=>$quote,'items'=>$items]);
    }
    
    private function createQuote(): void
    {
        if(!$this->validateCsrfOrFail('/app/cotizaciones'))return;
        
        $customerId = $this->request->post('customer_id')?(int)$this->request->post('customer_id'):null;
        $items = $this->request->post('items',[]);
        $notes = $this->request->post('notes','');
        $validUntil = $this->request->post('valid_until')?:date('Y-m-d',strtotime('+15 days'));
        
        if(empty($items)||!is_array($items)){$this->respond(false,'Debe agregar productos','/app/cotizaciones');return;}
        
        $prefix = 'COT-';
        $quoteNumber = $prefix.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(2)),0,4));
        
        $subtotal=0;$validItems=[];
        foreach($items as $item){
            $productId=(int)($item['product_id']??0);$quantity=(int)($item['quantity']??0);
            if($productId<=0||$quantity<=0)continue;
            $product=$this->query("SELECT * FROM products WHERE id=?",[$productId])->fetch();
            if(!$product)continue;
            $up=(float)$product['sale_price'];$is=$up*$quantity;$subtotal+=$is;
            $validItems[]=['product_id'=>$productId,'product_name'=>$product['name'],'quantity'=>$quantity,'unit_price'=>$up,'subtotal'=>$is];
        }
        if(empty($validItems)){$this->respond(false,'Productos no válidos','/app/cotizaciones');return;}
        
        $taxRate=(float)($this->query("SELECT setting_value FROM settings WHERE setting_key='tax_rate'")->fetch()['setting_value']??0);
        $tax=$subtotal*($taxRate/100);$total=$subtotal+$tax;
        
        try{
            $this->db->beginTransaction();
            $this->query("INSERT INTO quotes (quote_number,customer_id,user_id,subtotal,tax,total,notes,valid_until) VALUES (?,?,?,?,?,?,?,?)",
                [$quoteNumber,$customerId,$_SESSION['tenant_user_id'],$subtotal,$tax,$total,$notes,$validUntil]);
            $quoteId=$this->db->lastInsertId();
            foreach($validItems as $vi){
                $this->query("INSERT INTO quote_items (quote_id,product_id,product_name,quantity,unit_price,subtotal) VALUES (?,?,?,?,?,?)",
                    [$quoteId,$vi['product_id'],$vi['product_name'],$vi['quantity'],$vi['unit_price'],$vi['subtotal']]);
            }
            $this->db->commit();
            $this->respond(true,'Cotización creada: '.$quoteNumber,'/app/cotizaciones');
        }catch(\Exception $e){
            $this->db->rollBack();
            $this->respond(false,'Error: '.$e->getMessage(),'/app/cotizaciones');
        }
    }
    
    private function deleteQuote(): void
    {
        if(!$this->validateCsrfOrFail('/app/cotizaciones'))return;
        $id=(int)$this->request->post('id');
        $this->query("DELETE FROM quotes WHERE id=? AND status!='converted'",[$id]);
        $this->respond(true,'Cotización eliminada','/app/cotizaciones');
    }
    
    /**
     * Convierte una cotización aceptada en una venta real
     */
    private function convertToSale(): void
    {
        if(!$this->validateCsrfOrFail('/app/cotizaciones'))return;
        
        $id=(int)$this->request->post('id');
        $paymentMethod=$this->request->post('payment_method','cash');
        $paymentType=$this->request->post('payment_type','full');
        $initialPayment=(float)$this->request->post('initial_payment',0);
        
        $quote=$this->query("SELECT * FROM quotes WHERE id=? AND status IN ('pending','accepted')",[$id])->fetch();
        if(!$quote){$this->respond(false,'Cotización no encontrada o ya convertida','/app/cotizaciones');return;}
        
        $items=$this->query("SELECT * FROM quote_items WHERE quote_id=?",[$id])->fetchAll();
        if(empty($items)){$this->respond(false,'La cotización no tiene productos','/app/cotizaciones');return;}
        
        $prefix = $this->query("SELECT setting_value FROM settings WHERE setting_key='invoice_prefix'")->fetch();
        $prefix = $prefix['setting_value']??'FAC-';
        $invoiceNumber = $prefix.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(2)),0,4));
        
        $paymentStatus = $paymentType==='credit'?'pending':'paid';
        if($paymentType==='credit'&&$initialPayment>=$quote['total'])$paymentStatus='paid';
        
        try{
            $this->db->beginTransaction();
            
            $this->query("INSERT INTO sales (invoice_number,customer_id,user_id,sale_date,subtotal,tax,discount,total,payment_method,payment_status,notes,status) VALUES (?,?,?,NOW(),?,?,0,?,?,?,?,?)",
                [$invoiceNumber,$quote['customer_id'],$_SESSION['tenant_user_id'],$quote['subtotal'],$quote['tax'],$quote['total'],$paymentMethod,$paymentStatus,'Convertido de cotización: '.$quote['quote_number'],$paymentStatus==='paid'?'completed':'pending']);
            $saleId=$this->db->lastInsertId();
            
            foreach($items as $item){
                $this->query("INSERT INTO sale_items (sale_id,product_id,product_name,quantity,unit_price,subtotal) VALUES (?,?,?,?,?,?)",
                    [$saleId,$item['product_id'],$item['product_name'],$item['quantity'],$item['unit_price'],$item['subtotal']]);
                $this->query("UPDATE products SET stock=stock-?,last_sale_date=NOW() WHERE id=? AND stock>=?",[$item['quantity'],$item['product_id'],$item['quantity']]);
                $this->query("INSERT INTO stock_movements (product_id,type,quantity,reference_type,reference_id,notes,user_id) VALUES (?,'out',?,'sale',?,?,?)",
                    [$item['product_id'],$item['quantity'],$saleId,'Venta (desde cotización '.$quote['quote_number'].')',$_SESSION['tenant_user_id']]);
            }
            
            if($paymentType==='credit'&&$initialPayment>0){
                $this->query("INSERT INTO sale_payments (sale_id,amount,payment_method,notes,user_id) VALUES (?,?,?,?,?)",
                    [$saleId,$initialPayment,$paymentMethod,'Pago inicial desde cotización',$_SESSION['tenant_user_id']]);
            }
            
            $this->query("UPDATE quotes SET status='converted', updated_at=NOW() WHERE id=?",[$id]);
            
            $this->db->commit();
            
            // Registrar en caja
            if ($paymentStatus === 'paid') {
                $this->registerCashMovement($quote['total'], 'Venta (desde cotización): ' . $invoiceNumber, 'sale', (int)$saleId);
            } elseif ($initialPayment > 0) {
                $this->registerCashMovement($initialPayment, 'Abono inicial (cotización): ' . $invoiceNumber, 'sale', (int)$saleId);
            }
            
            $this->respond(true,'Cotización convertida a venta: '.$invoiceNumber,'/app/ventas');
        }catch(\Exception $e){
            $this->db->rollBack();
            $this->respond(false,'Error: '.$e->getMessage(),'/app/cotizaciones');
        }
    }
}
