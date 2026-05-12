# Link virtual - Microsoft Azure

[retinai-ehcadnergkbkd9dr.eastus2-01.azurewebsites.net](https://retinai-ehcadnergkbkd9dr.eastus2-01.azurewebsites.net/)

## Pruebas Automatizadas (Testing)

El sistema cuenta con un suite de pruebas automatizadas para el Requerimiento Funcional 02 (Expiración de Sesión y Control de Acceso por Clave Temporal).

### ¿Cómo probar localmente?
Para ejecutar las pruebas en tu computadora, asegúrate de tener instalado **Node.js**. Abre la terminal en esta carpeta y sigue estos pasos:

1. **Instalar las herramientas de prueba (Jest y Cypress):**
   ```bash
   npm install
   ```

2. **Correr las pruebas unitarias (Jest):**
   Valida que el servicio `session.service.js` hace expirar la sesión exactamente a los 5 minutos de inactividad, usando cronómetros acelerados (Fake Timers).
   ```bash
   npm test
   ```

3. **Correr las pruebas End-to-End (Cypress):**
   Abre un navegador fantasma (robot) que ingresa las credenciales del médico, da clic en Iniciar Sesión y valida que el sistema lo redirija de forma obligatoria a cambiar su clave (`change_password.php`).
   ```bash
   npm run cypress:run
   ```
   *Nota: Si deseas ver al robot interactuando visualmente con la plataforma, ejecuta `npm run cypress:open`.*

### Pruebas en el Entorno Desplegado (Azure)
Estas pruebas han sido configuradas en el archivo `.github/workflows/main_retinai.yml` para que se ejecuten automáticamente:
- **Jest:** Se ejecuta en la fase de construcción (`build`) antes de desplegar.
- **Cypress:** Se ejecuta automáticamente contra el servidor en la nube de Azure después de que el despliegue haya sido exitoso.