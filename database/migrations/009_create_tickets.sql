-- ============================================
-- Módulo: Tickets de Soporte
-- Sistema de tickets con chat interno para clientes
-- ============================================

USE softnova_master;

-- ============================================
-- Tabla: tickets (Tickets de soporte)
-- ============================================
CREATE TABLE IF NOT EXISTS tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    ticket_code VARCHAR(50) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    category ENUM('support', 'consultation', 'bug', 'feature_request', 'other') DEFAULT 'support',
    assigned_to INT UNSIGNED NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_category (category),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: ticket_messages (Mensajes del chat del ticket)
-- ============================================
CREATE TABLE IF NOT EXISTS ticket_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    user_name VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_staff TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;