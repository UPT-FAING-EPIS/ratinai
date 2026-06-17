

|CASO DE PRUEBA||||||||
| :-: | :- | :- | :- | :- | :- | :- | :- |
|**CASO DE USO**|<p>CU06 </p><p>Registro de alimentos</p>|**CASO N°**|CPAM02|||||
|||**VERSION DE EJECUCION**|v1.0|||||
|||**FECHA EJECUCION**|<p>30/03/2023</p><p>07:13 p.m.</p>|||||
|||**MODULO DEL SISTEMA**|Gestión de Alimentos|||||
|**Descripción de caso**|Se probará el proceso de agregación y eliminación de alimentos básicos |||||||
|<p>1. **Caso de Prueba**</p><p>&emsp;*Tipo de prueba: Funcional*</p>||||||||
|a. **Precondiciones que deben cumplir para realizar la prueba**||||||||
|Tener una cuenta creada, tener sesión iniciada, tener por lo menos un alimento agregado en el dispositivo móvil para seleccionarlo y poder eliminarlo, en el caso que desee agregar un nuevo alimento debe desplazarse por las vistas dependiendo del alimento (Desayuno, Almuerzo, Cena)||||||||
|<p>b. **Pasos secuenciales para poder ejecutar**</p><p>*Representa la secuencia de pasos que deberán ser ejecutadas para verificar el caso de Prueba*</p>||||||||
|**N°**|**Pasos**|**Resultado Esperado**||||||
|**Flujo Normal: Agregar Alimento**||||||||
|**1**|Estando en la sección de “Menu Principal” el usuario presiona el botón con el icono ‘+’ para agregar un nuevo alimento.|El sistema muestra la lista de alimentos y los muestra.||||||
|**2**|El usuario selecciona la opción alimento.|El sistema muestra layout con un icono + para agregar el alimento.||||||
|**3**|El usuario presionara el botón ‘+’ del layout.|El sistema muestra un dialogo con aparecen un text para agregar la comida y un botón “Agregar”.||||||
|**4**|Estando en el Dialogo el usuario agregar el nombre de la comida y presiona el botón “Agregar Alimento”.|El sistema muestra en el layout principal el alimento con sus calorías.||||||
|**5**|El usuario regresa al menú principal|El sistema actualiza el progress bar de alimentación con las calorías anteriores.||||||
|**Flujo Normal: Eliminar Alimento**||||||||
|**1**|Estando en la sección de “Menu principal” el usuario presiona el botón con el icono ‘+’ ubicado a la izquierda de alimento.|El sistema muestra un layout con los alimentos registrados.||||||
||El usuario selecciona en el icono de tres puntos.|El sistema muestra un dialog con el texto Remove.||||||
||El usuario selecciona el botón “Remove.”|El sistema Muestra un Dialog preguntando si se desea eliminar el alimento.||||||
||***Presiona botón “Sí”***|||||||
|**2**|El usuario presiona el botón “Yes”.|<p>El sistema muestra un mensaje indicando que el alimento se eliminó correctamente de la lista de alimentos.</p><p>Finalmente, ya no se muestra el alimento.</p>||||||
||***Presiona botón “No”***|||||||
|**3**|El usuario presiona el botón “No”.|El sistema cierra el mensaje y no pasa nada con el alimento seleccionado.||||||
|c. **Post condiciones que deben cumplirse para la realización de la prueba.**||||||||
|Almacenar o eliminar los alimentos que el usuario escoja. ||||||||
|2. **RESULTADOS DE LA PRUEBA**||||||||
|**Defectos y desviaciones encontradas en las pruebas.**|**RESULTADO (marcar x)**|||||||
|La prueba se realizó exitosamente sin encontrar algún error o bug durante la ejecución de esta en sus diferentes escenarios.|x|Con éxito||||||
|||Paralizada||||||
|||Suspendida||||||
|Observaciones Generales|Responsable de Prueba|||||||
|Ninguna.|<p>**Firma**</p><p></p><p>**Nombre** </p><p>**Fecha**  </p><p>\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_</p><p></p><p>**Firma** </p><p></p><p>**Nombre:** </p><p>**Fecha:** </p><p></p><p></p>|||||||



