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
        TenantOpsSchema::ensure($db);
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
        ?int $userId = null,
        ?string $movementDate = null
    ): int {
        $userId = $userId ?? ($_SESSION['tenant_user_id'] ?? null);
        $date = $movementDate ?: date('Y-m-d H:i:s');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            $date = date('Y-m-d H:i:s');
        } elseif (strlen($date) === 10) {
            $date .= ' ' . date('H:i:s');
        }

        $this->query(
            "INSERT INTO stock_movements
                (product_id, type, quantity, reference_type, reference_id, notes, user_id, movement_date, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$productId, $type, $quantity, $referenceType, $referenceId, $notes, $userId, $date, $date]
        );
        return (int)$this->db->lastInsertId();
    }

    /**
     * Edita la fecha de ingreso/movimiento (trazabilidad).
     */
    public function updateMovementDate(int $movementId, string $movementDate): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $movementDate)) {
            throw new \InvalidArgumentException('Fecha de movimiento inválida');
        }
        if (strlen($movementDate) === 10) {
            $movementDate .= ' 12:00:00';
        }
        $stmt = $this->query(
            "UPDATE stock_movements SET movement_date = ? WHERE id = ?",
            [$movementDate, $movementId]
        );
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Movimiento de stock no encontrado');
        }
    }
    
    /**
     * Descontar stock de forma atómica. Lanza excepción si no hay suficiente.
     */
    public function decrease(
        int $productId,
        int $quantity,
        string $referenceType,
        ?int $referenceId,
        string $notes = '',
        ?string $movementDate = null
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
            $this->addMovement($productId, 'out', $quantity, $referenceType, $referenceId, $notes, null, $movementDate);
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
        
        $this->addMovement($productId, 'out', $quantity, $referenceType, $referenceId, $notes, null, $movementDate);
    }
    
    /**
     * Incrementar stock (devoluciones, entradas, compras)
     */
    public function increase(
        int $productId,
        int $quantity,
        string $referenceType,
        ?int $referenceId,
        string $notes = '',
        string $movementType = 'in',
        ?string $movementDate = null
    ): void {
        if ($quantity <= 0) {
            return;
        }
        
        $this->query("UPDATE products SET stock = stock + ? WHERE id = ?", [$quantity, $productId]);
        $this->addMovement($productId, $movementType, $quantity, $referenceType, $referenceId, $notes, null, $movementDate);
    }

    /**
     * Historial de movimientos (trazabilidad) con filtros.
     *
     * @return array{rows:array,total:int}
     */
    public function listMovements(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['product_id'])) {
            $where[] = 'sm.product_id = ?';
            $params[] = (int)$filters['product_id'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'sm.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['reference_type'])) {
            $where[] = 'sm.reference_type = ?';
            $params[] = $filters['reference_type'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(p.name LIKE ? OR p.code LIKE ? OR sm.notes LIKE ? OR sm.reference_type LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($filters['from'])) {
            $where[] = 'DATE(COALESCE(sm.movement_date, sm.created_at)) >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'DATE(COALESCE(sm.movement_date, sm.created_at)) <= ?';
            $params[] = $filters['to'];
        }
        $whereSql = implode(' AND ', $where);

        $total = (int)$this->query(
            "SELECT COUNT(*) FROM stock_movements sm
             LEFT JOIN products p ON p.id = sm.product_id
             WHERE {$whereSql}",
            $params
        )->fetchColumn();

        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $rows = $this->query(
            "SELECT sm.*, p.name product_name, p.code product_code, p.source_channel,
                    u.name user_name
             FROM stock_movements sm
             LEFT JOIN products p ON p.id = sm.product_id
             LEFT JOIN users u ON u.id = sm.user_id
             WHERE {$whereSql}
             ORDER BY COALESCE(sm.movement_date, sm.created_at) DESC, sm.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        )->fetchAll();

        return ['rows' => $rows, 'total' => $total];
    }
}
