-- Google Drive y OneDrive quedan disponibles por establecimiento sin alterar informes previos.
ALTER TABLE configuraciones_almacenamiento
    MODIFY COLUMN proveedor ENUM('local', 'google_drive', 'sharepoint', 'onedrive') NOT NULL DEFAULT 'local';
