ALTER TABLE solicitudes_establecimiento
    ADD COLUMN latitud DECIMAL(10,7) NULL AFTER direccion,
    ADD COLUMN longitud DECIMAL(10,7) NULL AFTER latitud;

ALTER TABLE establecimientos
    ADD COLUMN latitud DECIMAL(10,7) NULL AFTER direccion,
    ADD COLUMN longitud DECIMAL(10,7) NULL AFTER latitud;
