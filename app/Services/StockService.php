<?php

namespace SoftNova\Services;

/**
 * Servicio de inventario / stock para BD tenant
 */
class StockService
{
    private \PDO $db;
    
    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }
    
    private function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function addMovement(
        int $productId,
        string $type,
        int $quantity,
        string $referenceType = '',
        ?int $referenceId = null,
        string $notes = '',
        ?int $userId = null
    ): void {
        $userId = $userId ?? ($_SESSION['tenant_user_id'] ?? null);
        $this->query(
            "INSERT INTO stock_movements (product_id, type, quantity, reference_type, reference_id, notes, user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$productId, $type, $quantity, $referenceType, $referenceId, $notes, $userId]
        );
    }
    
    /**
     * Descontar stock de forma atómica. Lanza excepción si no hay suficiente.
     */
    public function decrease(
        int $productId,
        int $quantity,
        string $referenceType,
        ?int $referenceId,
        string $notes = ''
    ): void {
        if ($quantity <= 0) {
            return;
        }
        
        $product = $this->query(
            "SELECT id, name, stock, product_type, status FROM products WHERE id = ?",
            [$productId]
        )->fetch();
        
        if (!$product) {
            throw new \RuntimeException('Producto no encontrado');
        }
        
        if (($product['product_type'] ?? 'product') === 'service') {
            $this->addMovement($productId, 'out', $quantity, $referenceType, $referenceId, $notes);
            return;
        }
        
        $stmt = $this->query(
            "UPDATE products SET stock = stock - ?, last_sale_date = NOW()
             WHERE id = ? AND stock >= ? AND status = 'active'",
            [$quantity, $productId, $quantity]
        );
        
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException(
                'Stock insuficiente para: ' . ($product['name'] ?? ('#' . $productId))
                . ' (disponible: ' . (int)$product['stock'] . ', solicitado: ' . $quantity . ')'
            );
        }
        
        $this->addMovement($productId, 'out', $quantity, $referenceType, $referenceId, $notes);
    }
    
    /**
     * Incrementar stock (devoluciones, entradas)
     */
    public function increase(
        int $productId,
        int $quantity,
        string $referenceType,
        ?int $referenceId,
        string $notes = '',
        string $movementType = 'in'
    ): void {
        if ($quantity <= 0) {
            return;
        }
        
        $this->query("UPDATE products SET stock = stock + ? WHERE id = ?", [$quantity, $productId]);
        $this->addMovement($productId, $movementType, $quantity, $referenceType, $referenceId, $notes);
    }
}
