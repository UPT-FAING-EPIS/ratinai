-- =============================================================================
-- MIGRACIÓN: Agregar id_usuario a la tabla establecimientos
-- Permite que un dueño (ADM) tenga múltiples establecimientos a su nombre.
-- =============================================================================

-- 1. Agregar columna id_usuario a establecimientos
ALTER TABLE `establecimientos`
    ADD COLUMN `id_usuario` INT DEFAULT NULL
        COMMENT 'ID del usuario ADM propietario del establecimiento'
        AFTER `ruc`;

-- 2. Crear la FK hacia usuarios (nullable para establecimientos históricos)
ALTER TABLE `establecimientos`
    ADD CONSTRAINT `establecimientos_ibfk_1`
        FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- 3. Poblar id_usuario en los establecimientos ya existentes
--    usando la relación inversa: usuarios.establecimiento_id → establecimientos.id
UPDATE `establecimientos` e
INNER JOIN `usuarios` u
    ON u.establecimiento_id = e.id
   AND u.rol_codigo = 'ADM'
SET e.id_usuario = u.id
WHERE e.id_usuario IS NULL;

-- 4. Añadir un índice para agilizar las búsquedas por propietario
ALTER TABLE `establecimientos`
    ADD INDEX `idx_establecimientos_id_usuario` (`id_usuario`);

-- 5. Agregar también id_usuario_solicitante a solicitudes_establecimiento
--    para poder vincular solicitudes pendientes a un usuario ya registrado.
ALTER TABLE `solicitudes_establecimiento`
    ADD COLUMN `id_usuario_solicitante` INT DEFAULT NULL
        COMMENT 'ID del usuario ADM que generó esta solicitud (NULL si fue solicitud pública)'
        AFTER `correo_contacto`,
    ADD CONSTRAINT `solicitudes_ibfk_1`
        FOREIGN KEY (`id_usuario_solicitante`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Verificar resultado
SELECT
    e.id,
    e.nombre,
    e.id_usuario,
    u.nombre  AS propietario,
    u.correo  AS correo_propietario
FROM establecimientos e
LEFT JOIN usuarios u ON u.id = e.id_usuario
ORDER BY e.id;
