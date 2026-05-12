CREATE DATABASE IF NOT EXISTS railway;
USE railway;

-- Tabla Maestro (Reemplaza la tabla de roles)
CREATE TABLE IF NOT EXISTS maestro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    codigo VARCHAR(20) NOT NULL,
    descripcion VARCHAR(100) NOT NULL,
    orden INT DEFAULT 0
);

INSERT INTO maestro (tipo, codigo, descripcion, orden) VALUES 
('ROL_SISTEMA', 'SAD', 'Super Administrador', 1),
('ROL_SISTEMA', 'ADM', 'Admin Establecimiento', 2),
('ROL_SISTEMA', 'MED', 'Médico Oftalmólogo', 3);

-- Tabla de establecimientos
CREATE TABLE IF NOT EXISTS establecimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(200)
);

INSERT INTO establecimientos (nombre, direccion) VALUES 
('Hospital de la Solidaridad', 'Av. Ejercito 123');

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_codigo VARCHAR(20) NOT NULL, -- Usamos el código del maestro
    establecimiento_id INT NULL,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    cmp VARCHAR(20) NULL,
    especialidad VARCHAR(100) NULL,
    es_password_temporal BOOLEAN DEFAULT FALSE,
    activo BOOLEAN DEFAULT TRUE,
    ultimo_acceso DATETIME NULL,
    FOREIGN KEY (establecimiento_id) REFERENCES establecimientos(id)
    -- Podríamos relacionar rol_codigo con maestro(codigo) de forma lógica 
    -- o agregar CONSTRAINT FOREIGN KEY dependiendo del motor.
);

-- Insertar Super Admin (Password: admin123)
INSERT INTO usuarios (rol_codigo, establecimiento_id, nombre, correo, password, activo) VALUES 
('SAD', NULL, 'Super Administrador', 'superadmin@ratinai.com', '$2y$10$Wwk04DqoCefNnKTCcrajjuYktY3q2saxD6.BvH/eB2KhY7RR5vpzK', 1);

-- Insertar Admin Establecimiento (Password: admin123)
INSERT INTO usuarios (rol_codigo, establecimiento_id, nombre, correo, password, activo) VALUES 
('ADM', 1, 'Admin Hospital', 'admin@hospital.com', '$2y$10$Wwk04DqoCefNnKTCcrajjuYktY3q2saxD6.BvH/eB2KhY7RR5vpzK', 1);

-- Insertar Médico Oftalmólogo (Password: medico123 -> misma hash para prueba)
INSERT INTO usuarios (rol_codigo, establecimiento_id, nombre, correo, password, cmp, especialidad, es_password_temporal, activo) VALUES 
('MED', 1, 'Dr. Juan Perez', 'medico@hospital.com', '$2y$10$Wwk04DqoCefNnKTCcrajjuYktY3q2saxD6.BvH/eB2KhY7RR5vpzK', '123456', 'Retina', 1, 1);

superadmin@ratinai.com
admin123

admin@hospital.com
admin123

medico@hospital.com
medico123