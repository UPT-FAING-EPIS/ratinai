# **Requerimiento: RF-01 Crear cuenta médico**

✔ **Prueba Unitaria**

**Descripción:** Se validará el método `generateTemp(12)` de la clase `PasswordHelper`. Debe producir una cadena alfanumérica única de exactamente 12 caracteres, sin llamadas externas a la base de datos. También se validará que `MailService` rechace direcciones de correo con formato inválido antes de intentar una conexión SMTP.

**Componente probado:** `PasswordHelper::generateTemp` y validación local de destinatarios en `MailService`.

# Casos cubiertos (automatizados):

- `testGenerateTempLength`: Verifica que la cadena generada tenga exactamente 12 caracteres.
- `testGenerateTempIsAlphanumeric`: Verifica que todos los caracteres sean alfanuméricos.
- `testGenerateTempIsUnique`: Compara dos contraseñas generadas y verifica que sean distintas.
- `testSendVerificationCodeInvalidEmail`: Verifica el rechazo de un correo inválido para verificación.
- `testSendSolicitudAprobadaInvalidEmail`: Verifica el rechazo de un correo inválido para aprobación.
- `testSendTempPasswordInvalidEmail`: Verifica el rechazo de un correo inválido para credenciales temporales.

**Resultado:** Éxito. PHPUnit informó OK en ambos archivos de prueba.

# Evidencia (salida CLI):

Gráfico 01. Ejecución de Pruebas Unitarias: RF-01 — Creación de cuenta médica

*Fuente: Elaboración propia. 2026*

*Nota: La prueba no acredita la recepción del correo; valida generación de credenciales y validación local.*

# ✔ Prueba Funcional

**Tipo:** Automatizada con Cypress.

# Pasos ejecutados:

- Iniciar sesión como Administrador.
- Abrir el formulario de creación de médico.
- Ingresar nombre, correo, CMP y especialidad.
- Elegir establecimiento cuando corresponde y enviar.
- Abrir el listado y buscar nombre, correo y CMP.

**Resultado esperado:** El médico queda activo y el sistema intenta enviar su contraseña temporal.

**Resultado obtenido:** Éxito. Cypress aprobó los dos casos: creación y consulta en listado.

# Evidencia (salida de Cypress):

Gráfico 02. Prueba Funcional: RF-01 — Creación y consulta de cuenta médica

*Fuente: Elaboración propia. 2026*

**Evidencia (capturas):**

**Evidencia 1:** Formulario de creación con datos profesionales.

**Evidencia 2:** Mensaje mostrado tras guardar.

**Evidencia 3:** Médico registrado en el listado.

# ✔ Prueba de Aceptación

- **Escenario validado:** El Administrador registra un oftalmólogo y comprueba su disponibilidad inmediata.
- **Criterio de aceptación:** La cuenta pertenece al establecimiento, está activa y tiene una clave temporal.
- **Estado de la evidencia:** La recepción del correo requiere comprobación manual del destinatario.

# ✔ Prueba de Rendimiento

- **Tiempo de respuesta:** No se registró una medición de creación ni de SMTP.
- **Número de usuarios:** Un Administrador y un médico.
- **Resultado:** Sin medición verificable para RF-01.

# **Requerimiento: RF-03 Gestionar médicos activos del establecimiento**

✔ **Prueba Unitaria**

**Descripción:** Se validará el método `resetPassword` del modelo de médicos. Debe actualizar la contraseña cifrada de un médico perteneciente a un establecimiento autorizado y marcar la cuenta con contraseña temporal para el siguiente acceso.

**Componente probado:** `DoctorModel::resetPassword`.

# Casos cubiertos (automatizados):

- `testResetPassword`: Verifica que se ejecute el `UPDATE` con hash, identificador del médico y establecimiento permitidos, y que `es_password_temporal` quede en `1`.

**Resultado:** Éxito. PHPUnit informó OK (1 test, 3 assertions).

# Evidencia (salida CLI):

Gráfico 03. Ejecución de Pruebas Unitarias: RF-03 — Gestión de médicos

*Fuente: Elaboración propia. 2026*

# ✔ Prueba Funcional

**Tipo:** Automatizada con Cypress.

# Pasos ejecutados:

- Iniciar sesión como Administrador.
- Editar un médico de prueba.
- Restablecer su contraseña.
- Desactivar su acceso.

**Resultado esperado:** Las acciones responden correctamente y el estado de acceso cambia.

**Resultado obtenido:** Éxito. Cypress aprobó edición, restablecimiento y desactivación.

# Evidencia (salida de Cypress):

Gráfico 04. Prueba Funcional: RF-03 — Gestión de médicos

*Fuente: Elaboración propia. 2026*

**Evidencia (capturas):**

**Evidencia 1:** Listado y filtros de médicos.

**Evidencia 2:** Modal de edición o restablecimiento.

**Evidencia 3:** Estado inactivo y acción Activar.

# ✔ Prueba de Aceptación

- **Escenario validado:** El Administrador gestiona datos y acceso de un médico de su establecimiento.
- **Criterio de aceptación:** Los cambios se aplican al médico autorizado y el estado coincide con su acceso.
- **Estado de la evidencia:** Aprobado automáticamente; recepción del correo requiere revisión manual.

# ✔ Prueba de Rendimiento

- **Tiempo de respuesta:** No se registró medición formal.
- **Número de usuarios:** Un Administrador y un médico.
- **Resultado:** Sin medición verificable para RF-03.

# **Requerimiento: RF-04 Gestionar Centro Oftalmológico**

✔ **Prueba Unitaria**

**Descripción:** Se validarán los métodos del modelo de establecimientos mediante objetos simulados de PDO. La prueba confirma que los datos institucionales se actualicen correctamente y que el sistema identifique al titular responsable o a un administrador alternativo del centro.

**Componente probado:** `EstablecimientoModel`.

# Casos cubiertos (automatizados):

- `testUpdateEjecutaQueryCorrectamente`: Verifica la consulta y parámetros de actualización institucional.
- `testGetAdminPriorizaIdUsuario`: Verifica que el titular asignado por `id_usuario` tenga prioridad.
- `testGetAdminUsaFallbackSiNoHayIdUsuario`: Verifica la búsqueda alternativa de administrador por establecimiento.
- `testGetMedicosUsaRolMED`: Verifica la consulta de médicos activos e inactivos con rol `MED`.

**Resultado:** Éxito. PHPUnit informó OK (4 tests, 12 assertions).

# Evidencia (salida CLI):

Gráfico 05. Ejecución de Pruebas Unitarias: RF-04 — Gestión de establecimiento

*Fuente: Elaboración propia. 2026*

# ✔ Prueba Funcional

**Tipo:** Automatizada con Cypress.

# Pasos ejecutados:

- Iniciar sesión como Super Administrador.
- Abrir un establecimiento desde el listado.
- Editar datos institucionales y guardar.
- Validar campo obligatorio, responsable y botón Volver.

**Resultado esperado:** Los datos se guardan y el sistema intenta notificar al responsable.

**Resultado obtenido:** Éxito. Cypress aprobó los cinco casos del flujo.

# Evidencia (salida de Cypress):

Gráfico 06. Prueba Funcional: RF-04 — Consulta y edición del centro

*Fuente: Elaboración propia. 2026*

**Evidencia (capturas):**

**Evidencia 1:** Listado con búsqueda y estado.

**Evidencia 2:** Formulario, coordenadas y mapa.

**Evidencia 3:** Métricas y gráficos del modelo.

# ✔ Prueba de Aceptación

- **Escenario validado:** El Super Administrador actualiza un centro registrado.
- **Criterio de aceptación:** Los datos quedan persistidos y se intenta notificar al titular.
- **Estado de la evidencia:** Edición aprobada; recepción SMTP requiere comprobación manual.

# ✔ Prueba de Rendimiento

- **Tiempo de respuesta:** No se registró medición formal.
- **Número de usuarios:** Un Super Administrador y un responsable.
- **Resultado:** Sin medición verificable para RF-04.

# **Requerimiento: RF-05 Procesar análisis de imagen retinal**

✔ **Prueba Unitaria**

**Descripción:** Se validarán el modelo y controlador de análisis retinal. Deben recuperar casos críticos de forma segura y rechazar solicitudes no válidas antes de procesar archivos o invocar el servicio CNN.

**Componente probado:** `AnalisisModel` y `AnalisisController`.

# Casos cubiertos (automatizados):

- `testGetSeguimientoCasosCriticos`: Verifica la consulta de alertas ordenadas por probabilidad principal.
- `testAnalizarFailsWithInvalidHttpMethod`: Verifica el rechazo de un método HTTP no permitido.
- `testAnalizarFailsWithoutSession`: Verifica el rechazo cuando no existe sesión médica.
- `testAnalizarFailsWithNoFile`: Verifica el rechazo cuando no se adjunta una imagen.
- `testAnalizarFailsWithInvalidFileType`: Verifica el rechazo de un archivo que no es JPG o PNG.
- `testAnalizarFailsWithFileSizeTooLarge`: Verifica el rechazo de una imagen superior a 10 MB.

**Resultado:** Éxito. PHPUnit informó OK (6 tests, 17 assertions entre ambos archivos).

# Evidencia (salida CLI):

Gráfico 07. Ejecución de Pruebas Unitarias: RF-05 — Análisis retinal

*Fuente: Elaboración propia. 2026*

# ✔ Prueba Funcional

**Tipo:** Automatizada con Cypress.

# Pasos ejecutados:

- Iniciar sesión como médico.
- Identificar al paciente por DNI.
- Cargar JPG o PNG, seleccionar ojo y validar imagen.
- Analizar con CNN o comprobar aviso de indisponibilidad.

**Resultado esperado:** El flujo valida la captura y muestra resultado o aviso real de disponibilidad.

**Resultado obtenido:** Éxito. Cypress aprobó imagen válida y rechazo de imagen no apta.

# Evidencia (salida de Cypress):

Gráfico 08. Prueba Funcional: RF-05 — Procesamiento de retinografía

*Fuente: Elaboración propia. 2026*

**Evidencia (capturas):**

**Evidencia 1:** Paciente y vista previa.

**Evidencia 2:** Validación y paso CNN.

**Evidencia 3:** Resultado, rechazo o aviso de indisponibilidad.

# ✔ Prueba de Aceptación

- **Escenario validado:** El médico identifica al paciente, carga y valida una retinografía.
- **Criterio de aceptación:** El sistema acepta imágenes válidas y no inventa resultados si el CNN no está disponible.
- **Estado de la evidencia:** Flujo aprobado; PDF requiere evidencia adicional con informe aprobado.

# ✔ Prueba de Rendimiento

- **Tiempo de respuesta:** No se registró medición del CNN remoto.
- **Número de usuarios:** Un médico y un paciente.
- **Resultado:** Sin medición verificable para RF-05.
