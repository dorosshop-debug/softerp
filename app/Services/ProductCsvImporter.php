<?php

namespace SoftNova\Services;

/**
 * Importación de productos desde CSV (reutilizable por request o job).
 */
class ProductCsvImporter
{
    public function __construct(private \PDO $db, private ?StockService $stock = null)
    {
        $this->stock = $stock ?? new StockService($db);
    }

    /** @return array{created:int,updated:int,skipped:int,errors:string[]} */
    public function importFile(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo leer el CSV');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw new \RuntimeException('CSV vacío');
        }
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false || count($header) < 2) {
            fclose($handle);
            throw new \RuntimeException('CSV sin encabezados válidos');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
        $map = [];
        foreach ($header as $i => $col) {
            $key = strtolower(trim((string)$col));
            $key = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $key);
            $aliases = [
                'sku' => 'code', 'codigo' => 'code', 'code' => 'code',
                'nombre' => 'name', 'name' => 'name',
                'tipo' => 'type', 'type' => 'type', 'product_type' => 'type',
                'categoria' => 'category', 'category' => 'category',
                'costo' => 'cost', 'cost' => 'cost', 'purchase_price' => 'cost',
                'precio' => 'price', 'price' => 'price', 'sale_price' => 'price',
                'stock' => 'stock', 'min' => 'min', 'min_stock' => 'min',
                'unidad' => 'unit', 'unit' => 'unit',
                'estado' => 'status', 'status' => 'status',
            ];
            if (isset($aliases[$key])) {
                $map[$aliases[$key]] = $i;
            }
        }
        if (!isset($map['name'])) {
            fclose($handle);
            throw new \RuntimeException('Falta columna Nombre');
        }

        $created = $updated = $skipped = 0;
        $errors = [];
        $rowNum = 1;
        $userId = (int)($_SESSION['tenant_user_id'] ?? 0);

        $this->db->beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNum++;
                if ($this->rowEmpty($row)) {
                    continue;
                }
                $name = trim((string)($row[$map['name']] ?? ''));
                if ($name === '') {
                    $skipped++;
                    continue;
                }
                $code = isset($map['code']) ? trim((string)($row[$map['code']] ?? '')) : '';
                $typeRaw = isset($map['type']) ? strtolower(trim((string)($row[$map['type']] ?? 'product'))) : 'product';
                $productType = in_array($typeRaw, ['service', 'servicio'], true) ? 'service' : 'product';
                $categoryName = isset($map['category']) ? trim((string)($row[$map['category']] ?? '')) : '';
                $cost = isset($map['cost']) ? (float)str_replace([',', ' '], ['.', ''], (string)($row[$map['cost']] ?? 0)) : 0.0;
                $price = isset($map['price']) ? (float)str_replace([',', ' '], ['.', ''], (string)($row[$map['price']] ?? 0)) : 0.0;
                $stock = isset($map['stock']) ? (int)$row[$map['stock']] : 0;
                $minStock = isset($map['min']) ? (int)$row[$map['min']] : 5;
                $unit = isset($map['unit']) ? trim((string)($row[$map['unit']] ?? 'UNIDAD')) : 'UNIDAD';
                if ($unit === '') {
                    $unit = 'UNIDAD';
                }
                $statusRaw = isset($map['status']) ? strtolower(trim((string)($row[$map['status']] ?? 'active'))) : 'active';
                $status = in_array($statusRaw, ['inactive', 'inactivo', '0'], true) ? 'inactive' : 'active';
                if ($productType === 'service') {
                    $stock = 0;
                }
                $categoryId = $this->resolveCategoryId($categoryName);
                $existing = null;
                if ($code !== '') {
                    $st = $this->db->prepare('SELECT id, stock FROM products WHERE code = ? LIMIT 1');
                    $st->execute([$code]);
                    $existing = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
                }
                if ($existing) {
                    $id = (int)$existing['id'];
                    $oldStock = (int)$existing['stock'];
                    $this->db->prepare(
                        "UPDATE products SET name=?, product_type=?, category_id=?, purchase_price=?, sale_price=?,
                         stock=?, min_stock=?, unit=?, status=? WHERE id=?"
                    )->execute([$name, $productType, $categoryId, $cost, $price, $stock, $minStock, $unit, $status, $id]);
                    if ($productType === 'product' && $stock !== $oldStock) {
                        $diff = $stock - $oldStock;
                        $this->stock->addMovement($id, $diff > 0 ? 'in' : 'out', abs($diff), 'adjustment', null, 'Ajuste por importación CSV');
                    }
                    $updated++;
                } else {
                    if ($code === '') {
                        $code = 'SKU-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                    }
                    $this->db->prepare(
                        "INSERT INTO products (code,name,product_type,description,category_id,purchase_price,sale_price,stock,min_stock,unit,status,created_by)
                         VALUES (?,?,?,'',?,?,?,?,?,?,?,?)"
                    )->execute([$code, $name, $productType, $categoryId, $cost, $price, $stock, $minStock, $unit, $status, $userId ?: null]);
                    $newId = (int)$this->db->lastInsertId();
                    if ($stock > 0 && $productType === 'product') {
                        $this->stock->addMovement($newId, 'in', $stock, 'adjustment', null, 'Stock inicial (importación CSV)');
                    }
                    $created++;
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            fclose($handle);
            throw $e;
        }
        fclose($handle);
        return compact('created', 'updated', 'skipped', 'errors');
    }

    private function rowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string)$cell) !== '') {
                return false;
            }
        }
        return true;
    }

    private function resolveCategoryId(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $st = $this->db->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
        $st->execute([$name]);
        $id = $st->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        $this->db->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
        return (int)$this->db->lastInsertId();
    }
}
