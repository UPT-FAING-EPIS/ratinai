# Plan inicial de evolución de RetinAI

**Estado:** plan de trabajo; no implica que las funciones descritas estén implementadas.  
**Fecha:** 28 de septiembre de 2026.  
**Objetivo:** convertir el prototipo en una plataforma de apoyo al análisis retinal con un flujo médico claro, trazable y una presentación pública profesional. RetinAI no gestiona citas, pagos ni la operación clínica completa del establecimiento.

## Decisiones de alcance

- Requerimientos nuevos: RF-09 (borrador editable), RF-10 (validación de imagen con la nueva CNN), RF-11 (comparación de controles), RF-12 (sincronización institucional) y RF-13 (valoración opcional del resultado de IA). RF-13 es el antiguo RF-14; actualizar la numeración en la matriz formal cuando se consolide.
- La CNN propia tendrá una nueva versión que determine si la entrada es una retinografía, si es evaluable y, solo entonces, las probabilidades de las categorías clínicas. Los dos primeros resultados actúan como condición de entrada; una imagen rechazada no recibe interpretación de enfermedades.
- El borrador se redactará mediante un servicio de lenguaje llamado desde el servidor, a partir de datos estructurados del análisis actual y controles previos pertinentes. La valoración clínica, edición y aprobación son del médico. La clave del proveedor nunca se expone en el navegador.
- Se conservarán la imagen y la salida original de la CNN, la versión del modelo, el ojo analizado, el borrador y el informe aprobado como estados distinguibles. El documento final se generará y almacenará en el servidor para poder sincronizarlo y recuperarlo.
- RF-13 es opcional e independiente de aprobar el informe. Las respuestas son «Coincido», «Discrepo» y «Evaluar después». No responder equivale a «sin evaluar», nunca a concordancia.
- La integración en la nube pertenece al establecimiento. Se implementará primero un proveedor institucional y luego se podrá incorporar otro. La configuración y los permisos serán por establecimiento.

## Navegación y pantallas por rol

| Área | Cambio previsto | Navegación recomendada |
| --- | --- | --- |
| Médico: `nuevoanalisis.php` | Reorganizar el flujo en **1. Cargar e identificar** (incluye ojo), **2. Validar y analizar**, **3. Revisar el resultado** (incluye valoración opcional de la IA) y **4. Redactar, editar y aprobar el informe**. Conservar visible la diferencia entre salida de CNN y conclusión médica. El límite temporal de RF-05 se mide hasta mostrar la salida de la CNN, sin incluir la redacción posterior. | Mantener «Nuevo análisis»; no crear una opción lateral por cada paso. |
| Médico: `pacientes.php` | Añadir ficha del paciente con controles del mismo ojo, comparación de dos fechas, versiones del modelo, estados de borrador/PDF y estado de sincronización. Permitir retomar borradores y descargar el informe aprobado. | Mantener «Historial de pacientes». Añadir «Informes» solo si los borradores pendientes necesitan una bandeja propia; evitar dos listas que repitan los mismos datos. |
| Médico: `dashboard.php` | Mostrar borradores pendientes, resultados sin valoración opcional de la IA y últimos informes. Cada indicador lleva a la acción correspondiente. | Mantener «Dashboard». La valoración pendiente se muestra como tarea de retroalimentación, no como informe clínico pendiente. |
| Médico: `seguimiento.php` | Mantener las alertas clínicas. Permitir abrir el análisis y la comparación pertinente; no mezclar alertas de enfermedad con tareas administrativas de evaluación del modelo. | Mantener «Seguimiento de alertas». |
| Médico: `modeloinfo.php` | Mostrar versión activa, clases, métricas realmente verificadas, datos de evaluación y limitaciones del nuevo modelo. | Mantener «Información del modelo». |
| Administrador del establecimiento: `views/admin/` | Configurar el almacenamiento institucional para cada centro, probar la conexión, revisar fallos de sincronización y reintentarlos. Mostrar estado resumido en su dashboard. | Preferir «Mis establecimientos → [centro] → Almacenamiento». Si crecen las integraciones, añadir una entrada «Integraciones» al sidebar ADM. |
| Super Admin: `views/superadmin/` | Ver estado agregado de integraciones y métricas agregadas de valoración del modelo por versión/centro, sin administrar las credenciales de nube de cada centro ni mostrar datos clínicos identificables por defecto. | Considerar una entrada «Calidad del modelo» cuando exista el panel agregado. No usar el sidebar SAD para conectar la cuenta de nube de un centro. |
| Público: `index.php` | Rediseñar narrativa, tipografía, jerarquía, llamadas a la acción y contenido verificable; incorporar la animación del ojo como recurso visual progresivo. | No requiere sidebar; conservar rutas claras a ingreso, información y solicitud de registro. |
| Público: `views/auth/solicitud_registro.php` | Mejorar la dirección ya existente con selección opcional de punto en mapa y confirmación de ubicación, sin impedir el registro si el mapa falla. | No requiere sidebar. En administración, mostrar la dirección y ubicación en el detalle del establecimiento. |

## Flujos y dependencias

1. **Modelo y datos (RF-10, base de RF-11).** Definir etiquetas y ejemplos para retina/no retina y evaluable/no evaluable; separar los datos de entrenamiento y prueba por paciente; evaluar los rechazos y cada clase clínica. Persistir lateralidad, fecha de captura, versión del modelo, resultado de calidad y salida original. Revalidar el tiempo de análisis de RF-05.
2. **Experiencia clínica (RF-09 y RF-13).** Diseñar el nuevo paso 3 y el paso 4; construir el borrador con hechos estructurados y controles comparables; permitir edición, guardado, reanudación y aprobación. La valoración de la IA puede hacerse ahora o después y no bloquea la aprobación médica.
3. **Historial (RF-11).** Comparar dos imágenes del mismo ojo con sus fechas y contexto. Cuando las versiones de CNN difieren, informar que las probabilidades no son directamente equivalentes. La aplicación no concluye por sí sola que una enfermedad progresó.
4. **Documento final y nube (RF-12).** Generar el PDF aprobado en el servidor, registrar versión y autor, guardar una copia recuperable, conectarse por autorización del establecimiento, sincronizar con estados pendiente/completado/fallido y permitir reintentos sin duplicados. Usar códigos internos de paciente en nombres de archivos, no DNI.
5. **Panel de valoración (RF-13).** Registrar concordancia, discrepancia con motivo o evaluación posterior. El dashboard médico muestra casos sin evaluar; el panel de calidad presenta denominador de casos evaluados y número de casos sin evaluar por separado. Ningún comentario modifica automáticamente la CNN.

## Rediseño público y administrativo

### Portada

- Propuesta visual: un ojo robótico estilizado que sigue suavemente el puntero dentro de un límite pequeño. Al elegir «Ingresar a la Plataforma», hacer una transición corta de apertura del iris y continuar al login. El enlace debe funcionar también con teclado, pantalla táctil, conexión lenta y preferencia de movimiento reducido; la animación no debe retrasar ni impedir el acceso.
- El contenido debe explicar en pocos bloques: para quién es RetinAI, qué imagen recibe, qué devuelve la CNN, qué revisa el médico y cuáles son sus límites. Añadir una demostración visual del flujo con datos de ejemplo y una sección de seguridad/privacidad redactada según las capacidades reales.
- Revisar antes de publicar las afirmaciones actuales de «mapa de calor», «sensibilidad y especificidad superior al 95 %», «más económico», costos comparativos y «validación en entorno real». Mantener solo las que tengan evidencia verificable de la versión publicada. Sustituir «diagnóstico automatizado» por lenguaje de apoyo referencial cuando corresponda.
- Sección de captura: explicar que se requieren fotografías de fondo de ojo/retinografías en JPG o PNG, y mostrar un retinógrafo o cámara de fondo de ojo como ilustración. No prometer compatibilidad con una marca, lente o equipo específico hasta ensayar imágenes de ese equipo con la nueva versión del modelo. Incluir requisitos de calidad comprobados y ejemplos de imágenes aceptables/no evaluables.

### Registro y gestión de establecimientos

- La solicitud ya recoge dirección. Añadir ubicación sobre mapa solo si facilita verificar el centro: dirección escrita editable, pin ajustable y coordenadas opcionales para administración. Si se adopta Google Maps, revisar configuración de clave, restricciones, cuotas y facturación; mantener alternativa manual.
- Aplicar un diseño compartido a solicitudes, establecimientos, médicos, estados vacíos, formularios, validaciones y confirmaciones. Mejorar primero los recorridos completos y los permisos por rol; no hacer un rediseño visual aislado de cada CRUD.
- Las vistas administrativas deben distinguir claramente «solicitud», «establecimiento aprobado», «médico activo» y «sincronización fallida» mediante estados consistentes y acciones explicadas en lenguaje sencillo.

## Criterios para considerar lista cada fase

- **Clínica:** una imagen ajena o no evaluable no produce un resultado clínico; el médico puede completar el informe aunque omita la valoración opcional de RF-13.
- **Trazabilidad:** cada informe permite identificar análisis fuente, ojo, fecha, versión de CNN, texto generado, cambios médicos, aprobador y versión del PDF.
- **Historial:** solo se comparan controles del mismo paciente y ojo; el usuario ve la advertencia si cambió el modelo.
- **Nube:** la desconexión de un proveedor no hace perder el informe aprobado; el centro puede ver y reintentar fallos.
- **Interfaz:** los recorridos principales funcionan en escritorio y móvil, con teclado y con movimiento reducido; los textos públicos coinciden con capacidades y métricas verificadas.

## Decisiones aún abiertas

1. Dataset y protocolo de validación de la nueva CNN, incluidos negativos no retinales y calidad no evaluable.
2. Proveedor del modelo de lenguaje, condiciones para enviar datos de pacientes y evaluación de calidad de los borradores.
3. Primer proveedor de almacenamiento institucional: Google Workspace Shared Drive o Microsoft 365/SharePoint, según los centros piloto.
4. Equipo o tipo de retinógrafo cuyas imágenes se usarán en validación. Mientras no se defina, la web describirá el **tipo de imagen requerido**, no una compatibilidad certificada con hardware.
5. Si «Informes» tendrá su propia bandeja en el menú médico o bastará con abrir borradores desde dashboard e historial.

## Fuentes técnicas para revisar al ejecutar

- Google Maps JavaScript API: https://developers.google.com/maps/documentation/javascript/get-api-key
- Google Places Autocomplete: https://developers.google.com/maps/documentation/places/web-service/place-autocomplete
- Movimiento reducido: https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/%40media/prefers-reduced-motion
- Ejemplo de flujo de calidad de imagen retinal: https://www.accessdata.fda.gov/cdrh_docs/pdf22/K223357.pdf
