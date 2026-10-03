-- Una imagen rechazada no tiene interpretación clínica; NULL expresa ausencia de resultado sin inventar ceros.
ALTER TABLE analisis_retinales
    MODIFY COLUMN resultado_principal VARCHAR(50) NULL,
    MODIFY COLUMN probabilidad_principal DECIMAL(5,2) NULL,
    MODIFY COLUMN probabilidad_normal DECIMAL(5,2) NULL,
    MODIFY COLUMN probabilidad_diabetes DECIMAL(5,2) NULL,
    MODIFY COLUMN probabilidad_glaucoma DECIMAL(5,2) NULL,
    MODIFY COLUMN probabilidad_catarata DECIMAL(5,2) NULL;
