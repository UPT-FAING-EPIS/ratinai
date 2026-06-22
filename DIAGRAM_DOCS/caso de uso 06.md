|CASO DE USO — Gestionar Historial por Paciente  |  CU-06||
| :- | :- |
|Actor|Médico Oftalmólogo · Sistema|
|Descripción|Permite al médico identificar a un paciente mediante su DNI y acceder a su historial completo de análisis. El sistema presenta el historial del paciente organizado mediante carpetas que agrupan los análisis, o análisis sin carpeta, permitiendo una fácil lectura cronológica y estructural.|
|Precondiciones|El médico debe tener sesión activa. El médico debe estar en la vista "Historial de pacientes".|
|FLUJO NORMAL||
|Acción del actor|Curso del sistema|
|1\. El médico ingresa al módulo "Historial de pacientes" desde el menú lateral.|2\. El sistema consulta la base de datos y presenta la lista de pacientes recientes que tienen análisis registrados, mostrando datos principales como DNI, código de paciente, número de carpetas, total de análisis, alertas, y fecha del último análisis.|
|3\. El médico puede usar la barra de búsqueda para filtrar localmente por DNI o código de paciente.|4\. El sistema filtra instantáneamente la lista de tarjetas de pacientes coincidentes.|
|5\. El médico presiona el botón "Ver detalle" en la tarjeta de un paciente específico.|6\. El sistema solicita de forma asíncrona (AJAX) el detalle del historial del paciente seleccionado.|
| |7\. El sistema despliega una vista detallada debajo de la tarjeta del paciente, mostrando todas las carpetas creadas para ese paciente. Cada carpeta indica la cantidad de análisis que contiene. Los análisis no asignados a ninguna carpeta aparecen agrupados bajo "Análisis sin carpeta".|
|8\. El médico hace clic en una carpeta específica.|9\. El sistema expande la carpeta, mostrando los análisis correspondientes ordenados cronológicamente descendente (del más reciente al más antiguo). Cada análisis muestra fecha y hora, resultado principal, probabilidad y opción de descargar el reporte PDF.|
|FLUJO ALTERNATIVO — Paciente sin historial de análisis||
|Acción del actor|Curso del sistema|
|1\.A El médico ingresa al módulo "Historial de pacientes" y el sistema no encuentra pacientes registrados con análisis para ese médico.|2\.A El sistema muestra el mensaje: "No hay pacientes con análisis registrados." en la pantalla en lugar de la lista de pacientes.|
|EXCEPCIONES||
|Error de conexión al cargar detalle: Si falla la petición AJAX para obtener las carpetas de un paciente, el sistema muestra: "Error al cargar el detalle." dentro de la sección desplegable.||
