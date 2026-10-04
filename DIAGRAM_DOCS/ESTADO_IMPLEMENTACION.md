# Estado de implementación del plan de evolución

Fecha de inicio: 29 de septiembre de 2026.

## Convenciones de ejecución

- Fuente de verdad funcional: `PLAN_EVOLUCION_RETINAI.md`.
- Fuente de verdad de calidad: `directrices_de_prompts_y_auditor_a.md`.
- Entorno inicial: `http://ratinai.local` y base de datos local configurada.
- Regla de avance: cada hito debe incluir validación automatizada y recorrido funcional antes de marcarse como terminado.

## Línea base observada

- La portada local responde con HTTP 200.
- Jest: 6 de 6 pruebas pasan.
- PHPUnit 10 no es compatible con el PHP 8.0.30 local.
- PHPUnit 9 era la versión compatible con PHP 8.0.30; se incorporó como dependencia de desarrollo y se corrigió el envío de encabezados durante pruebas.
- La CNN v1 funciona en EC2 y Azure ya tiene sus variables. El código local admite su contrato sin inventar validaciones. XAMPP no tiene URL ni clave de EC2 y no puede ejecutar un análisis remoto real desde esta red.

## Hitos

| Hito | Alcance | Estado | Evidencia |
| --- | --- | --- | --- |
| 0 | Auditoría, migraciones y registro de decisiones | Parcial | Migraciones 001 a 006 aplicadas localmente; falta certificar o depurar los 53 análisis y establecimientos heredados |
| 1 | RF-10: identificación, ojo, análisis y persistencia trazable | Adaptador CNN v1 implementado; prueba remota pendiente | Valida todas las probabilidades y conserva nulas las validaciones que v1 no ejecuta; prueba unitaria aprobada; falta recorrer EC2 desde Azure |
| 2 | RF-09 y RF-13: borrador, edición, aprobación y valoración opcional | OpenAI integrado; prueba remota pendiente | Responses API, salida estructurada, sin identificadores ni imagen y `store=false`; falta prueba con saldo y clave reales |
| 3 | RF-11: historial y comparación del mismo ojo | Terminado | Comparación restringida a paciente y ojo, advertencia entre versiones y prueba funcional |
| 4 | RF-12: PDF y almacenamiento | OAuth y subida Google Drive/OneDrive implementados; prueba remota pendiente | Un proveedor activo por centro, tokens cifrados y reintento idempotente; falta autorización y archivo real en cada servicio |
| 5 | Paneles médico, administrador y Super Admin | Estructura local | Se aclaró el alcance; los indicadores reales dependen de CNN y almacenamiento institucional |
| 6 | Registro, ubicación, accesibilidad y pruebas | Parcial | GPS, selección en Leaflet/OpenStreetMap y búsqueda explícita de dirección; falta recorrido visual y pruebas con proveedores reales |

## Evidencia local anterior y su límite

- PHPUnit 9: 26 pruebas y 73 aserciones correctas después de retirar el proveedor demostrativo.
- Jest: 6 pruebas correctas.
- Cypress: 11 especificaciones y 27 pruebas funcionales pasaron con el antiguo proveedor demostrativo. Esa evidencia prueba la estructura de interfaz, pero no la integración real y deberá repetirse en una base aislada.
- Composer: dependencias sin avisos de seguridad conocidos.
- CNN: el contrato v1 desplegado puede entregar el resultado clínico actual; no entrega validación retina/no retina ni calidad, por lo que esos campos deben mostrarse como no evaluados y permanecer nulos.
- MariaDB: seis migraciones figuran en `migraciones_esquema`; la sexta amplía el proveedor documental a OneDrive sin eliminar datos.
- Verificación actual: PHPUnit 9, 30 pruebas y 86 aserciones; Jest, 6 pruebas; análisis sintáctico PHP y JavaScript correcto; formulario público HTTP 200 con controles y biblioteca de mapa presentes. Falta comprobar la interacción visual en navegador.

## Decisiones provisionales reversibles

- El sistema de archivos local es una copia temporal, no una integración institucional terminada.
- Los tokens OAuth se cifran con `DOCUMENT_TOKEN_ENCRYPTION_KEY` si existe. Como Azure ya tiene `ANALYSIS_API_KEY`, se deriva una clave separada con HKDF cuando no se define la primera. Cambiar esa clave existente requiere reconectar las cuentas documentales.
- El borrador combina hechos estructurados exactos con un párrafo de OpenAI `gpt-5.6-terra`; la aprobación médica sigue siendo obligatoria.
- La CNN v1 usa un adaptador de contrato. La validación retina/no retina y calidad permanece nula hasta la CNN v2 de otro día.
- Los análisis históricos conservarán valores nulos en los nuevos campos y se mostrarán como datos no disponibles.

## Bloqueos externos conocidos

- Desplegar el código y aplicar migración 006 en la base de Azure antes de probar allí.
- Confirmar saldo habilitado de OpenAI y conectar una cuenta real desde cada proveedor OAuth para completar pruebas de extremo a extremo.
- El acceso de EC2 está restringido: la llamada CNN real solo puede comprobarse desde el entorno Azure configurado.
- Dataset y pesos validados de la CNN v2, únicamente para la etapa final.
- Equipo de captura que formará parte de la validación.

La CNN v2 queda fuera de esta entrega. Las credenciales ya configuradas en Azure no se copiaron al entorno local ni al repositorio.

## Criterio previo al despliegue (registro histórico)

Antes de migrar a nube se debe respaldar la base destino, aplicar `php migraciones/aplicar.php` sobre una copia, comparar conteos y hashes, y recorrer RF-10, RF-09, RF-11 y RF-12 con las credenciales del proveedor institucional elegido. Las migraciones aplicadas no se editan; cualquier ajuste posterior se agrega como una nueva versión numerada.

## Verificación del flujo médico en Azure — 3 de octubre de 2026

- El análisis real `#72` del médico autorizado conserva la salida de la CNN v1 (`1.0`), su imagen y los controles previos en la base de Azure. No se creó un análisis de prueba.
- `generar_borrador` produjo y guardó el informe `#7` con hechos estructurados y texto real de OpenAI. La segunda solicitud recuperó el mismo informe sin regenerarlo.
- `guardar_borrador` respondió correctamente incluso cuando el médico no modificó el texto. El PDF se generó en memoria a partir de ese análisis e informe reales; no se aprobó clínicamente ni se publicó un PDF sin intervención médica.
- La conexión OneDrive del establecimiento `#1` respondió correctamente a la comprobación OAuth. La sincronización de un informe aprobado queda pendiente de una aprobación médica real.
- En la vista de nuevo análisis, tras la respuesta válida de la CNN se ocultan la identificación, carpetas, carga y botón de análisis. Permanecen resultado, edición y retinografía. Aprobar queda deshabilitado si no existe borrador guardado.
- El 404 HTML que Azure mostraba para el borrador ocultaba un error de la aplicación: OpenAI había incluido cifras en la redacción y la validación la rechazaba. La generación ahora vuelve a solicitar texto sin cifras y las fallas se devuelven como JSON legible. El 401 indica sesión ausente o expirada y redirige al acceso.
- Verificación: PHPUnit 30/30, Jest 6/6, sintaxis PHP y JavaScript correcta, rutas HTTP y lectura/guardado reales en Azure. En esta iteración no hubo cambios de esquema ni migraciones.
