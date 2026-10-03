# Registro de cambios de base de datos

Este documento mantiene la trazabilidad entre el esquema local y la futura migración a nube. Las migraciones ejecutables viven en `migraciones/` y se aplican con `php migraciones/aplicar.php`.

## Línea base local

Tablas existentes antes de la evolución: `usuarios`, `establecimientos`, `solicitudes_establecimiento`, `pacientes`, `carpetas_paciente`, `analisis_retinales` y `maestro`.

## Migración 001 — evolución clínica

Estado: aplicada a la base local el 29 de septiembre de 2026. Se conservaron los 53 análisis existentes.

### Tabla modificada: `analisis_retinales`

Columnas añadidas: `ojo`, `fecha_captura`, `version_modelo`, `es_retinografia`, `probabilidad_retinografia`, `es_evaluable`, `probabilidad_calidad`, `motivo_rechazo`, `salida_original_json` y `hash_imagen`.

Motivo: conservar lateralidad, fecha de captura, versión y salida original del modelo, además de las dos condiciones previas del RF-10.

### Tablas añadidas

- `informes_clinicos`: borrador generado, edición médica, aprobación, versión y archivo PDF.
- `valoraciones_ia`: respuesta opcional e independiente del informe para RF-13.
- `configuraciones_almacenamiento`: proveedor elegido por establecimiento y estado de conexión.
- `sincronizaciones_informes`: cola idempotente, intentos, errores y ubicación del documento.
- `migraciones_esquema`: historial técnico de migraciones aplicadas y su hash.

### Eliminaciones

Ninguna.

## Migración 002 — ubicación opcional de establecimientos

Estado: aplicada a la base local el 29 de septiembre de 2026 y verificada como idempotente por el ejecutor de migraciones.

### Tablas modificadas

- `solicitudes_establecimiento`: columnas anulables `latitud` y `longitud`.
- `establecimientos`: columnas anulables `latitud` y `longitud`.

Motivo: permitir que el solicitante confirme las coordenadas del centro sin depender de un mapa ni bloquear el registro cuando el navegador deniega la ubicación. Al aprobar una solicitud, las coordenadas se copian al establecimiento.

### Eliminaciones

Ninguna.

## Migración 003 — eliminación de resultados demostrativos

Estado: aplicada a la base local el 29 de septiembre de 2026.

### Datos eliminados

- 19 análisis cuya `version_modelo` era `demostracion-local-no-clinica`.
- 15 informes derivados de esos análisis.
- 6 sincronizaciones locales derivadas de esos informes.
- 25 archivos de imagen o PDF asociados.

Se conservaron los 53 análisis históricos anteriores. No se eliminó ninguna tabla ni columna. La limpieza se ejecuta por versión de modelo para no afectar resultados reales.

## Migración 004 — imágenes rechazadas sin probabilidades clínicas

Estado: aplicada a la base local el 29 de septiembre de 2026.

### Tabla modificada: `analisis_retinales`

Las columnas `resultado_principal`, `probabilidad_principal`, `probabilidad_normal`, `probabilidad_diabetes`, `probabilidad_glaucoma` y `probabilidad_catarata` ahora admiten `NULL`.

Motivo: una imagen no retinal o no evaluable no tiene interpretación clínica. Guardar cero podía confundirse con una probabilidad real; `NULL` expresa ausencia de resultado.

## Migración 005 — eliminación de usuarios de automatización

Estado: aplicada a la base local el 29 de septiembre de 2026.

### Datos eliminados

Se eliminaron 39 médicos creados por Cypress con correos inequívocos `cypress_doc_*`, `temp_login_*` o del dominio reservado `example.test`. Ninguno tenía análisis, carpetas ni establecimientos asociados.

Los usuarios operativos, los 53 análisis heredados y los establecimientos se conservaron. Su procedencia debe validarse antes de producción porque existen registros con apariencia de ejemplo que no pueden borrarse sin un inventario canónico.

## Migración 006 — cuentas documentales por establecimiento

Estado: aplicada a la base local el 3 de octubre de 2026.

- Tabla modificada: `configuraciones_almacenamiento`. El proveedor admite `onedrive` además de `google_drive`, `sharepoint` y `local`.
- `configuracion_cifrada` existente guarda el correo y los tokens OAuth cifrados. La restricción única por establecimiento impide mantener dos proveedores activos.
- No elimina filas, columnas ni archivos.

## Reversión

No se automatiza una reversión destructiva. Antes de desplegar en nube se generará un respaldo y se validará la migración sobre una copia. Las columnas nuevas aceptan valores nulos para conservar compatibilidad con análisis históricos.
