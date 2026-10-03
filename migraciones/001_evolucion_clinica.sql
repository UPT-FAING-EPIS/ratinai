-- Evolución clínica local de RetinAI (RF-09, RF-10, RF-11 y RF-13).
-- Esta migración es aditiva: no elimina tablas ni datos existentes.

ALTER TABLE analisis_retinales
    ADD COLUMN IF NOT EXISTS ojo ENUM('derecho', 'izquierdo') NULL AFTER id_carpeta,
    ADD COLUMN IF NOT EXISTS fecha_captura DATETIME NULL AFTER ojo,
    ADD COLUMN IF NOT EXISTS version_modelo VARCHAR(50) NULL AFTER fecha_captura,
    ADD COLUMN IF NOT EXISTS es_retinografia TINYINT(1) NULL AFTER version_modelo,
    ADD COLUMN IF NOT EXISTS probabilidad_retinografia DECIMAL(5,2) NULL AFTER es_retinografia,
    ADD COLUMN IF NOT EXISTS es_evaluable TINYINT(1) NULL AFTER probabilidad_retinografia,
    ADD COLUMN IF NOT EXISTS probabilidad_calidad DECIMAL(5,2) NULL AFTER es_evaluable,
    ADD COLUMN IF NOT EXISTS motivo_rechazo VARCHAR(255) NULL AFTER probabilidad_calidad,
    ADD COLUMN IF NOT EXISTS salida_original_json LONGTEXT NULL AFTER motivo_rechazo,
    ADD COLUMN IF NOT EXISTS hash_imagen CHAR(64) NULL AFTER salida_original_json;

CREATE TABLE IF NOT EXISTS informes_clinicos (
    id INT NOT NULL AUTO_INCREMENT,
    id_analisis INT NOT NULL,
    texto_generado TEXT NOT NULL,
    texto_editado TEXT NOT NULL,
    estado ENUM('borrador', 'aprobado') NOT NULL DEFAULT 'borrador',
    version INT NOT NULL DEFAULT 1,
    id_autor_borrador INT NOT NULL,
    id_aprobador INT NULL,
    fecha_generacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fecha_aprobacion DATETIME NULL,
    ruta_pdf VARCHAR(500) NULL,
    hash_pdf CHAR(64) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_informe_analisis (id_analisis),
    KEY idx_informe_estado (estado),
    KEY idx_informe_autor (id_autor_borrador),
    CONSTRAINT fk_informe_analisis FOREIGN KEY (id_analisis) REFERENCES analisis_retinales(id),
    CONSTRAINT fk_informe_autor FOREIGN KEY (id_autor_borrador) REFERENCES usuarios(id),
    CONSTRAINT fk_informe_aprobador FOREIGN KEY (id_aprobador) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS valoraciones_ia (
    id INT NOT NULL AUTO_INCREMENT,
    id_analisis INT NOT NULL,
    id_medico INT NOT NULL,
    valoracion ENUM('coincido', 'discrepo', 'evaluar_despues') NOT NULL,
    motivo VARCHAR(255) NULL,
    comentario VARCHAR(1000) NULL,
    fecha_valoracion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_valoracion_analisis (id_analisis),
    KEY idx_valoracion_medico (id_medico),
    KEY idx_valoracion_tipo (valoracion),
    CONSTRAINT fk_valoracion_analisis FOREIGN KEY (id_analisis) REFERENCES analisis_retinales(id),
    CONSTRAINT fk_valoracion_medico FOREIGN KEY (id_medico) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuraciones_almacenamiento (
    id INT NOT NULL AUTO_INCREMENT,
    id_establecimiento INT NOT NULL,
    proveedor ENUM('local', 'google_drive', 'sharepoint') NOT NULL DEFAULT 'local',
    configuracion_cifrada LONGTEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    ultima_prueba_estado ENUM('sin_probar', 'correcta', 'fallida') NOT NULL DEFAULT 'sin_probar',
    ultima_prueba_mensaje VARCHAR(500) NULL,
    ultima_prueba_fecha DATETIME NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_configuracion_establecimiento (id_establecimiento),
    CONSTRAINT fk_configuracion_establecimiento FOREIGN KEY (id_establecimiento) REFERENCES establecimientos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sincronizaciones_informes (
    id INT NOT NULL AUTO_INCREMENT,
    id_informe INT NOT NULL,
    id_establecimiento INT NOT NULL,
    proveedor VARCHAR(50) NOT NULL,
    clave_idempotencia CHAR(64) NOT NULL,
    estado ENUM('pendiente', 'completada', 'fallida') NOT NULL DEFAULT 'pendiente',
    ruta_remota VARCHAR(1000) NULL,
    intentos INT NOT NULL DEFAULT 0,
    ultimo_error VARCHAR(1000) NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fecha_completada DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sincronizacion_idempotente (clave_idempotencia),
    KEY idx_sincronizacion_estado (estado),
    KEY idx_sincronizacion_establecimiento (id_establecimiento),
    CONSTRAINT fk_sincronizacion_informe FOREIGN KEY (id_informe) REFERENCES informes_clinicos(id),
    CONSTRAINT fk_sincronizacion_establecimiento FOREIGN KEY (id_establecimiento) REFERENCES establecimientos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuraciones_almacenamiento (id_establecimiento, proveedor, activo)
SELECT id, 'local', 1
FROM establecimientos
ON DUPLICATE KEY UPDATE id_establecimiento = VALUES(id_establecimiento);
