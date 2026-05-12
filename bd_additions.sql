-- =========================================================================
-- RetinAI — BD Additions para Pruebas del RF-02 (Login y Clave Temporal)
-- =========================================================================
-- Este archivo no reemplaza bd.sql, sino que sirve para inyectar o asegurar
-- los datos necesarios para probar las reglas de negocio del login.

-- 1. Estado de cuenta (Aprobada / Pendiente / Desactivada)
-- La tabla original define `activo BOOLEAN DEFAULT TRUE`.
-- Lógica: activo=1 (Aprobada/Activa), activo=0 (Pendiente o Desactivada).
-- El controlador ya filtra: si activo=0, muestra error de cuenta no aprobada.

-- 2. Médico para probar la redirección a cambio de contraseña
-- El test de Cypress y manual asume que existe 'medico@hospital.com'
-- con password 'admin123' y la variable `es_password_temporal` en 1.

INSERT INTO usuarios (rol_codigo, establecimiento_id, nombre, correo, password, cmp, especialidad, es_password_temporal, activo) 
VALUES (
    'MED', 
    1, 
    'Dr. Prueba Temporal', 
    'medico@hospital.com', 
    '$2y$10$Wwk04DqoCefNnKTCcrajjuYktY3q2saxD6.BvH/eB2KhY7RR5vpzK', -- hash para "admin123"
    '998877', 
    'Oftalmología', 
    1, 
    1
)
ON DUPLICATE KEY UPDATE es_password_temporal = 1, activo = 1;

-- 3. Super Administrador (SAD) para probar el Dashboard SAD
-- Password "admin123"
INSERT INTO usuarios (rol_codigo, establecimiento_id, nombre, correo, password, es_password_temporal, activo)
VALUES (
    'SAD',
    NULL,
    'Super Admin',
    'superadmin@ratinai.com',
    '$2y$10$Wwk04DqoCefNnKTCcrajjuYktY3q2saxD6.BvH/eB2KhY7RR5vpzK',
    0,
    1
)
ON DUPLICATE KEY UPDATE activo = 1;

-- 4. Administrador de Establecimiento (ADM) para probar el Dashboard ADM
-- Password "admin123"
INSERT INTO usuarios (rol_codigo, establecimiento_id, nombre, correo, password, es_password_temporal, activo)
VALUES (
    'ADM',
    1,
    'Admin Hospital',
    'admin@hospital.com',
    '$2y$10$Wwk04DqoCefNnKTCcrajjuYktY3q2saxD6.BvH/eB2KhY7RR5vpzK',
    0,
    1
)
ON DUPLICATE KEY UPDATE activo = 1;
