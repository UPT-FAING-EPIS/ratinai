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
