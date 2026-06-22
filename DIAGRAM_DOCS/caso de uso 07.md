|CASO DE USO — Filtrar historial médico |  RF-07||
| :- | :- |
|Actor|Médico Oftalmólogo · Sistema|
|Descripción|Permite al médico recuperar el código único de historial de un paciente a partir de su número de DNI, en caso de que el código haya sido extraviado o no esté disponible.|
|Precondiciones|El médico debe tener sesión activa. El paciente debe tener un código de historial previamente asignado en el sistema.|
|FLUJO NORMAL||
|Acción del actor|Curso del sistema|
|1\. El médico accede a la opción "Recuperar código de historial" desde el módulo de análisis.|2\. El sistema presenta un campo de texto para ingresar el DNI del paciente.|
|3\. El médico ingresa el DNI del paciente y confirma la búsqueda.|4\. El sistema valida que el DNI tenga el formato correcto (8 dígitos numéricos).|
| |5\. El sistema busca al paciente en la base de datos por el DNI ingresado.|
|7\. El médico anota el código de historial recuperado.|6\. El sistema muestra al médico: "El código de historial de este paciente es: [CÓDIGO]."|
|FLUJO ALTERNATIVO — Paciente no encontrado en el sistema||
|Acción del actor|Curso del sistema|
|3\.A El médico ingresa un DNI que no está registrado en el sistema.|5\.A El sistema no encuentra coincidencia y muestra: "No se encontró ningún paciente con ese número de DNI. Verifique el número o registre al paciente en un nuevo análisis."|
|EXCEPCIONES||
|Archivo corrupto o ilegible: Si el sistema no puede leer el archivo seleccionado, muestra el mensaje: "No se pudo leer el archivo. Verifique que no esté dañado e intente nuevamente."||

