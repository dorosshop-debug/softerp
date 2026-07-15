-- ============================================
-- Agregar campos de documento y razon social a tenants
-- ============================================

USE softnova_master;

-- Verificar y agregar columnas individualmente para compatibilidad MySQL/MariaDB
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'softnova_master' AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'razon_social');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE tenants ADD COLUMN razon_social VARCHAR(255) NULL AFTER company_name',
    'SELECT "Column razon_social already exists" AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'softnova_master' AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'documento_tipo');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE tenants ADD COLUMN documento_tipo ENUM(''CC'', ''CE'', ''PPT'', ''OTROS'') NULL AFTER razon_social',
    'SELECT "Column documento_tipo already exists" AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'softnova_master' AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'documento_numero');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE tenants ADD COLUMN documento_numero VARCHAR(50) NULL AFTER documento_tipo',
    'SELECT "Column documento_numero already exists" AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
