-- ============================================
-- Script de configuración: Campos faltantes de tenant + Módulo Control de Licencias
-- Ejecutar este script si aparece el error "Unknown column 'razon_social'"
-- y para crear las tablas del módulo Control de Licencias
-- ============================================

USE softnova_master;

-- ============================================
-- 1. Corregir campos faltantes en tenants
-- ============================================
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS razon_social VARCHAR(255) NULL AFTER company_name,
    ADD COLUMN IF NOT EXISTS documento_tipo ENUM('CC', 'CE', 'PPT', 'OTROS') NULL AFTER razon_social,
    ADD COLUMN IF NOT EXISTS documento_numero VARCHAR(50) NULL AFTER documento_tipo,
    ADD COLUMN IF NOT EXISTS address TEXT NULL AFTER phone;

-- ============================================
-- 2. Tabla: license_sales (Ventas de Licencias/Suscripciones)
-- ============================================
CREATE TABLE IF NOT EXISTS license_sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NULL,
    plan_id INT UNSIGNED NOT NULL,
    sale_code VARCHAR(50) NOT NULL UNIQUE,
    sale_date DATE NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    billing_cycle ENUM('monthly', 'annual', 'quarterly', 'semiannual') DEFAULT 'monthly',
    amount DECIMAL(12, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_status ENUM('paid', 'pending', 'partial', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_method ENUM('cash', 'transfer', 'card', 'deposit', 'other') DEFAULT 'other',
    reference_number VARCHAR(100),
    notes TEXT,
    status ENUM('active', 'inactive', 'cancelled') DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_plan_id (plan_id),
    INDEX idx_sale_date (sale_date),
    INDEX idx_payment_status (payment_status),
    INDEX idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. Tabla: license_payments (Pagos de ventas)
-- ============================================
CREATE TABLE IF NOT EXISTS license_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    license_sale_id INT UNSIGNED NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    payment_method ENUM('cash', 'transfer', 'card', 'deposit', 'other') DEFAULT 'other',
    reference_number VARCHAR(100),
    notes TEXT,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_license_sale_id (license_sale_id),
    INDEX idx_payment_date (payment_date),
    FOREIGN KEY (license_sale_id) REFERENCES license_sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
