-- ============================================
-- Software de Gestión Active - ERP SaaS
-- Base de Datos Maestra
-- ============================================
-- Descripción: Base de datos maestra para el sistema Super Administrador
-- Autor: SoftNova Development Team
-- Fecha: 2026-06-23
-- ============================================

-- Crear base de datos maestra
CREATE DATABASE IF NOT EXISTS softnova_master 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE softnova_master;

-- ============================================
-- Tabla: tenants (Clientes/Empresas)
-- ============================================
-- Almacena información de cada cliente del sistema SaaS
-- ============================================
CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    tax_id VARCHAR(50),
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Mexico',
    database_name VARCHAR(100) NOT NULL UNIQUE,
    database_host VARCHAR(255) DEFAULT 'localhost',
    database_port INT DEFAULT 3306,
    database_user VARCHAR(255) NOT NULL,
    database_password VARCHAR(255) NOT NULL,
    status ENUM('active', 'suspended', 'cancelled') DEFAULT 'active',
    subscription_plan_id INT UNSIGNED,
    subscription_start_date DATE,
    subscription_end_date DATE,
    max_users INT DEFAULT 10,
    current_users INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_subscription_plan (subscription_plan_id),
    INDEX idx_database_name (database_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: subscription_plans (Planes de Suscripción)
-- ============================================
-- Define los planes disponibles para los clientes
-- ============================================
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    monthly_price DECIMAL(10, 2) NOT NULL,
    annual_price DECIMAL(10, 2) NOT NULL,
    max_users INT DEFAULT 10,
    max_products INT DEFAULT 1000,
    modules JSON,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: super_admin_users (Usuarios Super Admin)
-- ============================================
-- Usuarios con acceso al panel de Super Administrador
-- ============================================
CREATE TABLE IF NOT EXISTS super_admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: audit_logs (Registro de Auditoría Global)
-- ============================================
-- Registra todas las acciones críticas del sistema
-- ============================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    user_name VARCHAR(255),
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50),
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_module (module),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: system_settings (Configuración del Sistema)
-- ============================================
-- Configuraciones globales del sistema SaaS
-- ============================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Datos iniciales
-- ============================================

-- Insertar plan básico por defecto
INSERT INTO subscription_plans (name, description, monthly_price, annual_price, max_users, max_products, modules, status) VALUES
('Plan Básico', 'Plan inicial para pequeñas empresas', 29.99, 299.99, 5, 500, '["dashboard","ventas","inventario","clientes","caja"]', 'active'),
('Plan Profesional', 'Plan para empresas en crecimiento', 59.99, 599.99, 15, 2000, '["dashboard","ventas","inventario","clientes","caja","proveedores","cotizaciones","gastos","contabilidad","nomina","reportes"]', 'active'),
('Plan Enterprise', 'Plan completo para grandes empresas', 99.99, 999.99, 50, 10000, '["dashboard","ventas","inventario","clientes","caja","proveedores","cotizaciones","gastos","contabilidad","nomina","reportes"]', 'active');

-- Insertar super admin por defecto (password: Admin123!)
INSERT INTO super_admin_users (name, email, password, status) VALUES
('Super Admin', 'admin@softnova.com', '$argon2id$v=19$m=65536,t=4,p=3$c29tZXNhbHR2YWx1ZQ$MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA', 'active');
