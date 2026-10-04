# Directrices de Auditoría de Código y Nomenclatura en Español

A partir de ahora, todos los prompts y generaciones de código deben adherirse estrictamente a las siguientes reglas extraídas de "Revisión de Código (2).pptx", así como a la política de idioma español.

## 1. Reglas de Revisión y Calidad de Código

Cada vez que se genere o analice código, se deben auditar y cumplir los siguientes principios:

*   **No te repitas (DRY):** Prohibido duplicar código. Si hay lógica similar, debe abstraerse. El código duplicado es un riesgo de seguridad y mantenimiento.
*   **Comenta donde sea necesario:** Los comentarios deben explicar el *por qué* y no el *qué*. Se deben incluir especificaciones claras antes de métodos o clases (documentando su conducta, parámetros y retornos) y siempre citar la fuente si el código fue adaptado de otro lugar (ej. StackOverflow).
*   **Fail Fast (Falla rápido):** El código debe revelar sus errores lo antes posible. Implementar validaciones tempranas para evitar que errores silenciosos corrompan cálculos posteriores.
*   **Cero "Números Mágicos":** A excepción de 0, 1 y ocasionalmente 2, todo número literal debe ser reemplazado por una constante con un nombre claro y descriptivo.
*   **Un propósito para cada variable:** Prohibido reutilizar parámetros o variables locales para diferentes cálculos a lo largo de un método. Las variables son gratis; si el propósito cambia, declara una nueva.
*   **Utiliza nombres autodescriptivos:** Prohibido usar nombres perezosos como `tmp`, `temp`, `data` o abreviaturas oscuras. Los nombres deben ser largos y explicar claramente su contenido (ej. `segundosPorDia` en lugar de `tmp`).
*   **Convenciones de Lenguaje Estrictas:**
    *   `metodosYVariables`: camelCase (métodos son verbos, variables son sustantivos).
    *   `CONSTANTES`: UPPER_SNAKE_CASE.
    *   `ClasesEInterfaces`: PascalCase.
*   **Espacios en blanco y Formato:** Usar solo espacios (no tabs) para la indentación para evitar discrepancias entre editores. Dejar líneas en blanco para separar bloques lógicos y facilitar la lectura.
*   **No usar variables globales:** Evitar variables mutables que sean accesibles desde cualquier lugar del programa. Favorecer la inyección de dependencias o la encapsulación.
*   **Métodos que retornan, no que imprimen:** Los métodos de lógica de negocio deben retornar valores, no usar `print` o `System.out.println`. Las impresiones por consola deben limitarse a los niveles más altos de interacción con el usuario o estrictamente para depuración.

## 2. Política Estricta de Código 100% en Español

A partir de este punto, se establece una directiva inquebrantable para el idioma del código:

*   **Todo a Español:** Todas las interfaces, variables, nombres de archivos, módulos, funciones, clases, comentarios y mensajes de commit deben ser escritos íntegramente en español.
*   **Refactorización Automática:** Si en el análisis o contexto existe código previo escrito en inglés, es una obligación traducirlo y refactorizarlo al español en la respuesta generada. (Ejemplo: cambiar `getUserData()` a `obtenerDatosDeUsuario()`, `interface User` a `interface Usuario`).
*   **Consistencia:** Mantener una traducción coherente (ej. usar siempre `obtener` para `get`, `establecer` o `asignar` para `set`, `manejar` para `handle`).

## 3. Prohibición de simulaciones y datos ficticios

*   **Datos reales exclusivamente:** Está prohibido incorporar, presentar, almacenar o desplegar valores, resultados, métricas, pacientes, imágenes, credenciales, cuentas, estados o integraciones simuladas, de demostración, ficticias o de prueba como si pertenecieran al sistema.
*   **Sin sustitutos silenciosos:** Si una dependencia real no está configurada, no responde o no puede verificarse, la aplicación debe informar ese estado de forma explícita y detener el flujo afectado. Nunca debe fabricar una respuesta alternativa.
*   **Aplicación en todos los entornos:** Esta regla rige por igual para desarrollo local, pruebas, integración continua, Azure, AWS y cualquier despliegue. Una excepción solo puede aplicarse tras confirmación expresa del usuario para el caso concreto.
*   **CNN v2 en espera:** Todas las actividades que dependan de la CNN versión 2 permanecen en espera hasta una instrucción expresa del usuario. La CNN actualmente desplegada se conserva como servicio remoto real; no se reemplaza ni se emula.
