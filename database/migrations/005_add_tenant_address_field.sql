-- ============================================
-- Agregar campo direccion a tenants
-- ============================================

USE softnova_master;

ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS address TEXT NULL AFTER phone;
