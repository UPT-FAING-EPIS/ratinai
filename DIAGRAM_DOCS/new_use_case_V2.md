## Requerimientos funcionales de RetinAI

| Código | Requerimiento | Descripción | Prioridad |
|---|---|---|---|
| **RF-01** | Crear cuenta médica | El administrador de un establecimiento registra al médico. RetinAI crea una contraseña temporal, la envía al correo registrado y activa la cuenta. | 3 — M |
| **RF-02** | Iniciar sesión | Médicos y administradores con cuentas activas acceden según su rol. La sesión expira tras cinco minutos de inactividad y quien use una contraseña temporal debe cambiarla antes de continuar. | 3 — M |
| **RF-03** | Gestionar médicos del establecimiento | El administrador del establecimiento consulta sus médicos, edita sus datos, restablece sus contraseñas mediante una nueva clave temporal y desactiva accesos. | 3 — M |
| **RF-04** | Gestionar establecimientos | El Super Admin registra establecimientos, asigna un administrador responsable y edita sus datos conforme al procedimiento de autorización establecido. | 3 — M |
| **RF-05** | Analizar una retinografía | El médico carga una imagen JPG o PNG. RetinAI la procesa con su CNN y presenta resultados referenciales por categoría, probabilidades y alertas visuales en el tiempo máximo definido para el análisis. | 3 — M |
| **RF-06** | Consultar el historial del paciente | RetinAI asigna un código único al paciente en su primer análisis. El médico consulta los análisis asociados, ordenados cronológicamente. | 2 — S |
| **RF-07** | Recuperar el código de historial | El médico busca el código único de un paciente mediante su DNI cuando este se desconoce o se ha extraviado. | 2 — S |
| **RF-08** | Solicitar el registro de un establecimiento | El dueño de un centro oftalmológico envía una solicitud con los datos del establecimiento para que sea revisada por el administrador de la plataforma. | 2 — S |
| **RF-09** | Generar un borrador de informe clínico editable | Tras el análisis, un servicio de lenguaje conectado por API redacta un borrador a partir de los resultados de RetinAI y los controles previos pertinentes del mismo paciente. El médico revisa, corrige y aprueba el texto. El PDF final distingue los resultados referenciales de la IA de la valoración médica y registra quién lo aprobó y cuándo. | 3 — M |
| **RF-10** | Validar el contenido y la calidad de la imagen | La nueva versión de la CNN comprueba si la imagen corresponde a una retinografía y si es evaluable antes de presentar resultados de enfermedades. Si no supera estas comprobaciones, RetinAI indica el motivo y solicita otra imagen sin mostrar una clasificación clínica como válida. | 3 — M |
| **RF-11** | Comparar controles de un paciente | El médico compara imágenes y resultados de controles anteriores del **mismo ojo**. RetinAI muestra las fechas, probabilidades y versiones del modelo utilizadas, y advierte cuando estas versiones difieren. La interpretación de los cambios corresponde al médico. | 2 — S |
| **RF-12** | Sincronizar informes aprobados con la nube | RetinAI conserva el PDF final aprobado y lo sincroniza con el almacenamiento institucional conectado por el establecimiento. Organiza los documentos por código de paciente, informa si el envío está pendiente, completado o fallido y permite reintentar los fallos. | 2 — S |
| **RF-14** | Registrar la valoración médica del resultado de IA | De forma opcional, el médico selecciona **«Coincido»**, **«Discrepo»** o **«Evaluar después»**. Al discrepar, puede registrar el motivo y un comentario. Su dashboard muestra los resultados aún sin evaluar. RetinAI presenta estadísticas de discrepancia solo entre los resultados efectivamente valorados, sin modificar automáticamente la CNN. | 2 — S |

### Límite funcional

RetinAI apoya el análisis de retinografías y la revisión de sus resultados por médicos. Esta versión no contempla agenda de citas, pagos, administración clínica integral, integración con equipos de captura, historias clínicas electrónicas ni telemedicina.