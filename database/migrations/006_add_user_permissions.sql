-- ============================================
-- Agregar campo de permisos a tenant_users
-- ============================================

USE softnova_master;

-- Verificar y agregar columna para compatibilidad MySQL/MariaDB
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'softnova_master' AND TABLE_NAME = 'tenant_users' AND COLUMN_NAME = 'permissions');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE tenant_users ADD COLUMN permissions JSON NULL AFTER role',
    'SELECT "Column permissions already exists" AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
