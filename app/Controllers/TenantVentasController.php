<?php

namespace SoftNova\Controllers;

use SoftNova\Core\Controller;
use SoftNova\Core\TenantMiddleware;

class TenantVentasController extends Controller
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
    
    /**
     * Registra automáticamente un movimiento en caja (si hay sesión abierta)
     */
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
        $c = $this->query("SELECT setting_value FROM settings WHERE setting_key = 'currency'")->fetch();
        $code = $c['setting_value'] ?? 'COP';
        return ['COP'=>['symbol'=>'$','decimals'=>0,'thousands'=>'.','decimal'=>','],'USD'=>['symbol'=>'US$','decimals'=>2,'thousands'=>',','decimal'=>'.'],'EUR'=>['symbol'=>'€','decimals'=>2,'thousands'=>'.','decimal'=>',']][$code]??['symbol'=>'$','decimals'=>0,'thousands'=>'.','decimal'=>','];
    }
    
    public function index(): void
    {
        $action = $this->request->get('action');
        
        if ($action === 'create' && $this->request->method() === 'POST') { $this->createSale(); return; }
        if ($action === 'detail' && $this->request->method() === 'GET') { $this->detail(); return; }
        if ($action === 'cancel' && $this->request->method() === 'POST') { $this->cancelSale(); return; }
        if ($action === 'payment' && $this->request->method() === 'POST') { $this->registerPayment(); return; }
        
        $sales = $this->query("SELECT s.*, c.name as customer_name, u.name as user_name, COALESCE((SELECT SUM(sp.amount) FROM sale_payments sp WHERE sp.sale_id=s.id),0) as paid_amount FROM sales s LEFT JOIN customers c ON s.customer_id=c.id LEFT JOIN users u ON s.user_id=u.id ORDER BY s.created_at DESC LIMIT 50")->fetchAll();
        $todayTotal = $this->query("SELECT COALESCE(SUM(total),0) as t FROM sales WHERE DATE(sale_date)=CURDATE() AND status='completed'")->fetch()['t'];
        $products = $this->query("SELECT id, name, sale_price, stock FROM products WHERE status='active' ORDER BY name")->fetchAll();
        $customers = $this->query("SELECT id, name, first_name, last_name FROM customers WHERE status='active' ORDER BY name")->fetchAll();
        
        $this->view('tenant.ventas', [
            'sales'=>$sales,'todayTotal'=>$todayTotal,'products'=>$products,'customers'=>$customers,
            'currency'=>$this->getCurrency(),'tenantName'=>$_SESSION['tenant_name']??'Mi Empresa','userName'=>$_SESSION['tenant_user_name']??'Usuario',
        ]);
    }
    
    public function detail(): void
    {
        $id = (int)$this->request->get('id');
        $sale = $this->query("SELECT s.*, c.name as customer_name, c.first_name, c.last_name, u.name as user_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id LEFT JOIN users u ON s.user_id=u.id WHERE s.id=?",[$id])->fetch();
        if(!$sale){$this->json(['error'=>'Venta no encontrada']);return;}
        $items = $this->query("SELECT * FROM sale_items WHERE sale_id=?",[$id])->fetchAll();
        $payments = $this->query("SELECT * FROM sale_payments WHERE sale_id=? ORDER BY payment_date DESC",[$id])->fetchAll();
        $this->json(['sale'=>$sale,'items'=>$items,'payments'=>$payments]);
    }
    
    private function createSale(): void
    {
        if(!$this->validateCsrfOrFail('/app/ventas'))return;
        
        $customerId = $this->request->post('customer_id')?(int)$this->request->post('customer_id'):null;
        $paymentMethod = $this->request->post('payment_method','cash');
        $paymentType = $this->request->post('payment_type','full');
        $initialPayment = (float)$this->request->post('initial_payment',0);
        $items = $this->request->post('items',[]);
        $notes = $this->request->post('notes','');
        
        if(empty($items)||!is_array($items)){$this->respond(false,'Debe agregar al menos un producto','/app/ventas');return;}
        
        $prefix = $this->query("SELECT setting_value FROM settings WHERE setting_key='invoice_prefix'")->fetch();
        $prefix = $prefix['setting_value']??'FAC-';
        $invoiceNumber = $prefix.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(2)),0,4));
        
        $subtotal=0;$validItems=[];
        foreach($items as $item){
            $productId=(int)($item['product_id']??0);$quantity=(int)($item['quantity']??0);
            if($productId<=0||$quantity<=0)continue;
            $product=$this->query("SELECT * FROM products WHERE id=?",[$productId])->fetch();
            if(!$product)continue;
            $up=(float)$product['sale_price'];$is=$up*$quantity;$subtotal+=$is;
            $validItems[]=['product_id'=>$productId,'product_name'=>$product['name'],'quantity'=>$quantity,'unit_price'=>$up,'subtotal'=>$is];
        }
        if(empty($validItems)){$this->respond(false,'No se encontraron productos validos','/app/ventas');return;}
        
        $taxRate=(float)($this->query("SELECT setting_value FROM settings WHERE setting_key='tax_rate'")->fetch()['setting_value']??0);
        $tax=$subtotal*($taxRate/100);$total=$subtotal+$tax;
        
        $paymentStatus = $paymentType==='credit'?'pending':'paid';
        if($paymentType==='credit'&&$initialPayment>=$total)$paymentStatus='paid';
        
        try{
            $this->db->beginTransaction();
            $this->query("INSERT INTO sales (invoice_number,customer_id,user_id,sale_date,subtotal,tax,discount,total,payment_method,payment_status,notes,status) VALUES (?,?,?,NOW(),?,?,0,?,?,?,?,?)",
                [$invoiceNumber,$customerId,$_SESSION['tenant_user_id'],$subtotal,$tax,$total,$paymentMethod,$paymentStatus,$notes,$paymentStatus==='paid'?'completed':'pending']);
            $saleId=$this->db->lastInsertId();
            
            foreach($validItems as $vi){
                $this->query("INSERT INTO sale_items (sale_id,product_id,product_name,quantity,unit_price,subtotal) VALUES (?,?,?,?,?,?)",
                    [$saleId,$vi['product_id'],$vi['product_name'],$vi['quantity'],$vi['unit_price'],$vi['subtotal']]);
                $this->query("UPDATE products SET stock=stock-?,last_sale_date=NOW() WHERE id=? AND stock>=?",[$vi['quantity'],$vi['product_id'],$vi['quantity']]);
                $this->query("INSERT INTO stock_movements (product_id,type,quantity,reference_type,reference_id,notes,user_id) VALUES (?,'out',?,'sale',?,?,?)",
                    [$vi['product_id'],$vi['quantity'],$saleId,'Venta: '.$invoiceNumber,$_SESSION['tenant_user_id']]);
            }
            
            // Registrar pago inicial si es crédito
            if($paymentType==='credit'&&$initialPayment>0){
                $this->query("INSERT INTO sale_payments (sale_id,amount,payment_method,notes,user_id) VALUES (?,?,?,?,?)",
                    [$saleId,$initialPayment,$paymentMethod,'Pago inicial - credito',$_SESSION['tenant_user_id']]);
                if($initialPayment>=$total){
                    $this->query("UPDATE sales SET payment_status='paid',status='completed' WHERE id=?",[$saleId]);
                }else{
                    $this->query("UPDATE sales SET payment_status='partial' WHERE id=?",[$saleId]);
                }
            }
            
            $this->db->commit();
            
            // Registrar en caja
            if ($paymentStatus === 'paid') {
                $this->registerCashMovement($total, 'Venta: ' . $invoiceNumber, 'sale', (int)$saleId);
            } elseif ($initialPayment > 0) {
                $this->registerCashMovement($initialPayment, 'Abono inicial: ' . $invoiceNumber, 'sale', (int)$saleId);
            }
            
            $msg = $paymentType==='credit'?'Venta a credito creada: '.$invoiceNumber.' (Pendiente: $'.number_format($total-$initialPayment,0).')':'Venta creada: '.$invoiceNumber;
            $this->respond(true,$msg,'/app/ventas');
        }catch(\Exception $e){
            $this->db->rollBack();
            $this->respond(false,'Error: '.$e->getMessage(),'/app/ventas');
        }
    }
    
    private function registerPayment(): void
    {
        if(!$this->validateCsrfOrFail('/app/ventas'))return;
        
        $saleId=(int)$this->request->post('sale_id');
        $amount=(float)$this->request->post('amount');
        $method=$this->request->post('payment_method','cash');
        $notes=$this->request->post('notes','Abono');
        
        if($amount<=0){$this->respond(false,'El monto debe ser mayor a cero','/app/ventas');return;}
        
        $sale=$this->query("SELECT * FROM sales WHERE id=? AND payment_status IN ('pending','partial')",[$saleId])->fetch();
        if(!$sale){$this->respond(false,'Venta no encontrada o ya esta pagada','/app/ventas');return;}
        
        try{
            $this->db->beginTransaction();
            $this->query("INSERT INTO sale_payments (sale_id,amount,payment_date,payment_method,notes,user_id) VALUES (?,?,NOW(),?,?,?)",
                [$saleId,$amount,$method,$notes,$_SESSION['tenant_user_id']]);
            
            $totalPaid=$this->query("SELECT COALESCE(SUM(amount),0) as t FROM sale_payments WHERE sale_id=?",[$saleId])->fetch()['t'];
            $remaining=$sale['total']-$totalPaid;
            
            if($totalPaid>=$sale['total']){
                $this->query("UPDATE sales SET payment_status='paid',status='completed' WHERE id=?",[$saleId]);
            }else{
                $this->query("UPDATE sales SET payment_status='partial' WHERE id=?",[$saleId]);
            }
            
            $this->db->commit();
            
            // Registrar en caja
            $this->registerCashMovement($amount, 'Abono: ' . $sale['invoice_number'], 'sale_payment', $saleId);
            
            $this->respond(true,'Abono registrado. Pendiente: $'.number_format(max(0,$remaining),0),'/app/ventas');
        }catch(\Exception $e){
            $this->db->rollBack();
            $this->respond(false,'Error: '.$e->getMessage(),'/app/ventas');
        }
    }
    
    private function cancelSale(): void
    {
        if(!$this->validateCsrfOrFail('/app/ventas'))return;
        $id=(int)$this->request->post('id');
        $sale=$this->query("SELECT * FROM sales WHERE id=? AND status IN ('completed','pending')",[$id])->fetch();
        if(!$sale){$this->respond(false,'Venta no encontrada o ya cancelada','/app/ventas');return;}
        try{
            $this->db->beginTransaction();
            $items=$this->query("SELECT * FROM sale_items WHERE sale_id=?",[$id])->fetchAll();
            foreach($items as $item){
                $this->query("UPDATE products SET stock=stock+? WHERE id=?",[$item['quantity'],$item['product_id']]);
                $this->query("INSERT INTO stock_movements (product_id,type,quantity,reference_type,reference_id,notes,user_id) VALUES (?,'in',?,'return',?,?,?)",
                    [$item['product_id'],$item['quantity'],$id,'Devolucion: '.$sale['invoice_number'],$_SESSION['tenant_user_id']]);
            }
            $this->query("UPDATE sales SET status='cancelled',notes=CONCAT(IFNULL(notes,''),' | CANCELADO: ',".$this->db->quote(date('Y-m-d H:i')).") WHERE id=?",[$id]);
            $this->db->commit();
            $this->respond(true,'Venta cancelada. Stock devuelto.','/app/ventas');
        }catch(\Exception $e){
            $this->db->rollBack();
            $this->respond(false,'Error: '.$e->getMessage(),'/app/ventas');
        }
    }
}
