-- RetinAI: migración única para MySQL 8.0 de AWS. Ejecutar en HeidiSQL conectado como administrador.

-- Respaldo cifrado creado y validado antes de generar este archivo.

-- Ejecute el archivo entero una sola vez. Si una sentencia falla, deténgase e informe el error.

USE `railway`;

CREATE TABLE IF NOT EXISTS migraciones_esquema (
    nombre VARCHAR(255) NOT NULL PRIMARY KEY,
    hash_archivo CHAR(64) NOT NULL,
    fecha_aplicacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 001_evolucion_clinica.sql
-- Evolución clínica local de RetinAI (RF-09, RF-10, RF-11 y RF-13).
-- Esta migración es aditiva: no elimina tablas ni datos existentes.

ALTER TABLE analisis_retinales
    ADD COLUMN ojo ENUM('derecho', 'izquierdo') NULL AFTER id_carpeta,
    ADD COLUMN fecha_captura DATETIME NULL AFTER ojo,
    ADD COLUMN version_modelo VARCHAR(50) NULL AFTER fecha_captura,
    ADD COLUMN es_retinografia TINYINT(1) NULL AFTER version_modelo,
    ADD COLUMN probabilidad_retinografia DECIMAL(5,2) NULL AFTER es_retinografia,
    ADD COLUMN es_evaluable TINYINT(1) NULL AFTER probabilidad_retinografia,
    ADD COLUMN probabilidad_calidad DECIMAL(5,2) NULL AFTER es_evaluable,
    ADD COLUMN motivo_rechazo VARCHAR(255) NULL AFTER probabilidad_calidad,
    ADD COLUMN salida_original_json LONGTEXT NULL AFTER motivo_rechazo,
    ADD COLUMN hash_imagen CHAR(64) NULL AFTER salida_original_json;

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
WHERE TRUE
ON DUPLICATE KEY UPDATE id_establecimiento = VALUES(id_establecimiento);

INSERT INTO migraciones_esquema (nombre, hash_archivo) VALUES ('001_evolucion_clinica.sql', 'ae123df431ae2d1295cf20a3423bd4152af41312074d624a67683bc04009d84b');


-- 002_ubicacion_establecimientos.sql
ALTER TABLE solicitudes_establecimiento
    ADD COLUMN latitud DECIMAL(10,7) NULL AFTER direccion,
    ADD COLUMN longitud DECIMAL(10,7) NULL AFTER latitud;

ALTER TABLE establecimientos
    ADD COLUMN latitud DECIMAL(10,7) NULL AFTER direccion,
    ADD COLUMN longitud DECIMAL(10,7) NULL AFTER latitud;

INSERT INTO migraciones_esquema (nombre, hash_archivo) VALUES ('002_ubicacion_establecimientos.sql', '1ec17b3cc069d8a94421e02f583ef5fdb8c52d1ca8abe3f082ed8951eee6020f');


-- 003_eliminar_resultados_demostracion.sql
-- Los resultados del proveedor local eran útiles para probar el flujo, pero no son evidencia clínica.
-- Se eliminan por versión para conservar intactos los análisis históricos y los futuros resultados remotos.
DELETE s
FROM sincronizaciones_informes s
INNER JOIN informes_clinicos i ON i.id = s.id_informe
INNER JOIN analisis_retinales a ON a.id = i.id_analisis
WHERE a.version_modelo = 'demostracion-local-no-clinica';

DELETE v
FROM valoraciones_ia v
INNER JOIN analisis_retinales a ON a.id = v.id_analisis
WHERE a.version_modelo = 'demostracion-local-no-clinica';

DELETE i
FROM informes_clinicos i
INNER JOIN analisis_retinales a ON a.id = i.id_analisis
WHERE a.version_modelo = 'demostracion-local-no-clinica';

DELETE FROM analisis_retinales
WHERE version_modelo = 'demostracion-local-no-clinica';

INSERT INTO migraciones_esquema (nombre, hash_archivo) VALUES ('003_eliminar_resultados_demostracion.sql', 'c970b874eeabb964ff93ecd36a394895761fa4954694a392e278e03a7625e10d');


-- 004_resultados_rechazados_sin_probabilidades.sql
-- Una imagen rechazada no tiene interpretación clínica; NULL expresa ausencia de resultado sin inventar ceros.
ALTER TABLE analisis_retinales
    MODIFY COLUMN resultado_principal VARCHAR(50) NULL,
    MODIFY COLUMN probabilidad_principal DECIMAL(5,2) NULL,
    MODIFY COLUMN probabilidad_normal DECIMAL(5,2) NULL,
    MODIFY COLUMN probabilidad_diabetes DECIMAL(5,2) NULL,
    MODIFY COLUMN probabilidad_glaucoma DECIMAL(5,2) NULL,
    MODIFY COLUMN probabilidad_catarata DECIMAL(5,2) NULL;

INSERT INTO migraciones_esquema (nombre, hash_archivo) VALUES ('004_resultados_rechazados_sin_probabilidades.sql', 'a4b782ee105f1af90582bc8ed76ab4e54736008b5ea3741be4eb8077224bc6f3');


-- 005_eliminar_usuarios_automatizacion.sql
-- Las pruebas funcionales antiguas escribían médicos en la base de revisión y no los eliminaban.
-- Los patrones incluyen un sello de tiempo y dominios reservados exclusivamente para automatización.
DELETE FROM usuarios
WHERE rol_codigo = 'MED'
  AND (
      correo REGEXP '^cypress_doc_[0-9]+@hospital\\.com$'
      OR correo REGEXP '^temp_login_[0-9]+@hospital\\.com$'
      OR (nombre = 'Doctor Temporal Login' AND correo REGEXP '^rf03\\.[0-9]+@example\\.test$')
      OR correo = 'test.auto@medico.com'
  );

INSERT INTO migraciones_esquema (nombre, hash_archivo) VALUES ('005_eliminar_usuarios_automatizacion.sql', '7dc93ac8d5eac85778d5b2efc38d25091a8863df321322f53aba360456d0e0da');


-- 006_almacenamiento_oauth.sql
-- Google Drive y OneDrive quedan disponibles por establecimiento sin alterar informes previos.
ALTER TABLE configuraciones_almacenamiento
    MODIFY COLUMN proveedor ENUM('local', 'google_drive', 'sharepoint', 'onedrive') NOT NULL DEFAULT 'local';

INSERT INTO migraciones_esquema (nombre, hash_archivo) VALUES ('006_almacenamiento_oauth.sql', 'cd5b7c362e0c54781f4241fc0f3f106703bbee0d7d743a54eb4c9dd606ca3e3e');


SELECT nombre, fecha_aplicacion FROM migraciones_esquema ORDER BY nombre;
