# Plan de evolución de RetinAI V2

**Actualizado:** 3 de octubre de 2026.  
**Objetivo inmediato:** completar las integraciones del sistema usando la CNN v1 que ya funciona en EC2.  
**Objetivo posterior:** implementar la CNN v2 con validación de retinografía y calidad como una de las últimas etapas.  
**Directriz obligatoria:** `directrices_de_prompts_y_auditor_a.md`.

## 1. Orden correcto de implementación

1. Conectar la aplicación a la **CNN v1 existente en EC2**.
2. Generar el borrador híbrido del informe con la API de OpenAI.
3. Permitir que cada administrador conecte **Google Drive o OneDrive**, uno por establecimiento.
4. Completar mapa, correo, base de datos, cifrado y configuración de App Service.
5. Probar los recorridos completos con servicios reales.
6. Migrar y validar en nube.
7. Implementar la CNN v2 y sus reglas de entrada al final.

La aplicación no ejecutará la CNN en XAMPP ni en Azure App Service. PHP enviará la imagen a la API que ya está desplegada en EC2.

## 2. Lista corta de lo que necesito ahora

No envíes secretos por chat. Colócalos en **Azure App Service → Configuration → Application settings** y, cuando corresponda, en `/etc/retinai-ai.env` de EC2. Para local se colocan en `.env`, que está excluido de Git.

| Prioridad | Integración | Lo que debes crear o localizar |
| --- | --- | --- |
| 1 | CNN v1 en EC2 | URL HTTPS actual, clave API actual, tipo de certificado TLS y respuesta real de `/health`. |
| 2 | Informe híbrido | Proyecto de OpenAI con facturación y una clave API de proyecto. |
| 3 | Google Drive | Proyecto de Google Cloud, Drive API habilitada y cliente OAuth web. |
| 4 | OneDrive | Registro de aplicación en Microsoft Entra con cuentas personales y corporativas. |
| 5 | Seguridad OAuth | Clave aleatoria de 32 bytes para cifrar los tokens de los establecimientos. |
| 6 | URL pública | Dominio HTTPS definitivo del Azure App Service para registrar los retornos OAuth. |
| 7 | Mapa | Clave restringida de Google Maps si el mapa debe quedar incluido ahora. |
| 8 | Operación | Base de datos de nube y SMTP real del remitente de RetinAI. |

## 3. CNN v1 existente en EC2

### No necesito un modelo nuevo ahora

La CNN v1 ya desplegada devuelve:

- `resultado_principal`;
- `probabilidad_principal`;
- probabilidades de `normal`, `diabetes`, `glaucoma` y `catarata`;
- `alerta_anomalia`;
- `modelo_version`;
- `tiempo_analisis`.

Esta versión no devuelve validación de retina/no retina ni calidad evaluable. La interfaz debe mostrar esos dos campos como **“No evaluado por CNN v1”**. No se guardarán valores inventados para aparentar dichas validaciones.

### Variables en Azure App Service

```text
ANALYSIS_AI_ENABLED=true
ANALYSIS_PROVIDER=remoto
ANALYSIS_CONTRACT_VERSION=v1
ANALYSIS_API_URL=https://DOMINIO_O_IP_ACTUAL_DE_EC2
ANALYSIS_API_KEY=CLAVE_QUE_YA_USA_EL_SERVICIO
```

### Variable en EC2

El despliegue existente usa esta variable. Su valor debe coincidir con `ANALYSIS_API_KEY` de App Service:

```text
RETINAI_API_KEY=LA_MISMA_CLAVE
```

### También necesito confirmar

- URL que responde a `/health`.
- Si HTTPS usa certificado público válido o una CA privada.
- Si EC2 acepta tráfico público en 443 o restringe por IP.
- Si restringe por IP, añadir las IP salientes de Azure App Service al grupo de seguridad o al proxy de EC2.
- Un ejemplo real de la respuesta de `/health`, sin compartir la clave.

Si utiliza una CA privada, necesito el archivo `.crt`. Si utiliza un certificado público válido, PHP debe usar el almacén normal de certificados y no se requiere un archivo especial.

## 4. Modelo híbrido para el informe final

### Modelo elegido

Se usará **OpenAI `gpt-5.6-terra`** mediante **Responses API** y salida estructurada. Es un modelo actual orientado al equilibrio entre capacidad y costo, admite Responses y Structured Outputs.

El enfoque híbrido funciona así:

1. RetinAI construye en PHP los hechos verificables: resultado CNN, probabilidades, ojo, fecha y controles previos.
2. OpenAI redacta únicamente un borrador con esos hechos mediante un esquema JSON cerrado.
3. PHP comprueba que el borrador no cambie las probabilidades ni agregue categorías.
4. El médico revisa, edita y aprueba el texto.
5. El PDF se genera después de la aprobación médica.

No se enviarán a OpenAI el DNI, nombre, correo, imagen retinal ni credenciales del paciente. La petición se hará con `store=false`.

### Lo que debes crear

1. Un proyecto separado en OpenAI Platform, por ejemplo `RetinAI-reportes`.
2. Facturación activa y un límite mensual de gasto.
3. Una clave API de proyecto con acceso solo a ese proyecto.
4. El identificador del proyecto, si la cuenta lo utiliza para separar consumo.

### Variables en Azure App Service

```text
REPORT_AI_ENABLED=true
OPENAI_API_KEY=CLAVE_DEL_PROYECTO
OPENAI_PROJECT_ID=IDENTIFICADOR_DEL_PROYECTO
REPORT_AI_MODEL=gpt-5.6-terra
REPORT_AI_STORE=false
```

`OPENAI_PROJECT_ID` puede quedar vacío si la clave ya está limitada al proyecto. No se necesita ninguna variable de OpenAI en EC2.

### Condición para producción clínica

Aunque la API no usa datos de API para entrenamiento por defecto, el uso de información clínica requiere revisar retención y el acuerdo aplicable. Para avanzar ahora se enviarán datos mínimos sin identificadores. Si después se decide enviar información protegida, se deberá tramitar el acuerdo correspondiente y los controles de retención antes de producción.

## 5. Google Drive por establecimiento

### Comportamiento

- El administrador elige Google Drive.
- Inicia sesión con una cuenta personal o corporativa de Google.
- Autoriza a RetinAI mediante OAuth.
- RetinAI crea una carpeta propia para informes y conserva acceso a los archivos creados por la aplicación.
- El establecimiento no puede mantener OneDrive activo al mismo tiempo.

### Lo que debes crear en Google Cloud

1. Proyecto, por ejemplo `retinai-documentos`.
2. Habilitar **Google Drive API**.
3. Configurar la pantalla de consentimiento OAuth.
4. Crear credenciales **OAuth Client ID → Web application**.
5. Registrar la URL de retorno exacta de producción:

```text
https://TU_APP_SERVICE.azurewebsites.net/controllers/AutorizacionGoogleDriveController.php
```

6. Mientras la aplicación OAuth esté en modo prueba, agregar las cuentas que usarán la integración como usuarios de prueba.

### Datos que necesito

```text
GOOGLE_DRIVE_CLIENT_ID=CLIENT_ID
GOOGLE_DRIVE_CLIENT_SECRET=CLIENT_SECRET
GOOGLE_DRIVE_REDIRECT_URI=https://TU_APP_SERVICE.azurewebsites.net/controllers/AutorizacionGoogleDriveController.php
```

El permiso solicitado será:

```text
openid email https://www.googleapis.com/auth/drive.file
```

Para cargar los PDF no se requiere una “API key” de Google Drive. Se requieren el `Client ID`, el `Client Secret` y la autorización OAuth de cada establecimiento. El alcance `drive.file` limita el acceso a archivos creados o seleccionados para la aplicación.

## 6. OneDrive por establecimiento

### Comportamiento

- El administrador elige OneDrive.
- Inicia sesión con una cuenta Microsoft personal o una cuenta corporativa/de institución.
- RetinAI recibe autorización delegada mediante OAuth y Microsoft Graph.
- RetinAI crea una carpeta propia para informes.
- El establecimiento no puede mantener Google Drive activo al mismo tiempo.

### Lo que debes crear en Microsoft Entra

1. **App registrations → New registration**.
2. Nombre sugerido: `RetinAI Documentos`.
3. Tipos de cuenta admitidos: cuentas de cualquier directorio organizacional **y cuentas Microsoft personales**.
4. Plataforma Web con la URL de retorno:

```text
https://TU_APP_SERVICE.azurewebsites.net/controllers/AutorizacionOneDriveController.php
```

5. Crear un secreto de cliente y copiar su **valor** en el momento de crearlo.
6. Configurar permisos delegados de Microsoft Graph:

```text
openid
profile
email
offline_access
User.Read
Files.ReadWrite
```

### Datos que necesito

```text
MICROSOFT_ONEDRIVE_CLIENT_ID=APPLICATION_CLIENT_ID
MICROSOFT_ONEDRIVE_CLIENT_SECRET=CLIENT_SECRET_VALUE
MICROSOFT_ONEDRIVE_TENANT=common
MICROSOFT_ONEDRIVE_REDIRECT_URI=https://TU_APP_SERVICE.azurewebsites.net/controllers/AutorizacionOneDriveController.php
```

`common` permite cuentas personales y cuentas corporativas. `offline_access` permite obtener un token de renovación para sincronizar informes después de que el administrador cierre sesión.

## 7. Exclusividad y cifrado de Google Drive/OneDrive

Las credenciales OAuth globales identifican a RetinAI. Cada administrador conecta su propia cuenta y los tokens resultantes se guardan cifrados por establecimiento.

### Variable obligatoria

```text
DOCUMENT_TOKEN_ENCRYPTION_KEY=CLAVE_BASE64_DE_32_BYTES
```

Puede generarse una vez con PHP:

```text
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

La regla de negocio será:

- un registro de almacenamiento por establecimiento;
- proveedor activo `google_drive` u `onedrive`;
- al cambiar de proveedor se revoca y elimina el token anterior;
- nunca se muestran `access_token`, `refresh_token` ni secretos en HTML, JSON o registros;
- si el token deja de funcionar, el administrador debe volver a conectar su cuenta.

## 8. Ubicación del establecimiento

La geolocalización del navegador y el enlace de OpenStreetMap ya permiten guardar coordenadas sin clave. Si se requiere mapa interactivo y búsqueda de direcciones ahora, necesito una clave distinta:

```text
GOOGLE_MAPS_BROWSER_KEY=CLAVE_RESTRINGIDA
```

En Google Cloud debes:

1. habilitar facturación;
2. habilitar Maps JavaScript API;
3. habilitar Places API (New) para búsqueda/autocompletado;
4. restringir la clave por referencias HTTP al dominio de App Service y al dominio final;
5. restringir la clave solo a esas APIs.

Esta clave puede aparecer en el navegador porque es una clave de cliente, pero debe quedar restringida por dominio y APIs. No debe reutilizarse como credencial OAuth de Drive.

## 9. Otras variables de Azure App Service

### Aplicación y base de datos

```text
APP_URL=https://TU_APP_SERVICE.azurewebsites.net
DB_HOST=HOST_DE_BASE_DE_DATOS
DB_PORT=PUERTO
DB_DATABASE=NOMBRE_BASE
DB_USERNAME=USUARIO
DB_PASSWORD=CONTRASENA
```

### Correo real

```text
SMTP_HOST=SERVIDOR_SMTP
SMTP_PORT=587
SMTP_USER=USUARIO_REMITENTE
SMTP_PASS=CLAVE_O_APP_PASSWORD
SMTP_FROM=CORREO_REMITENTE
SMTP_FROM_NAME=RetinAI
```

Estas variables ya existen en el proyecto. Para nube deben contener una cuenta real autorizada para enviar códigos, aprobaciones y claves temporales.

## 10. Qué necesito que me entregues o configures primero

### Bloque A — imprescindible para comenzar

1. Dominio HTTPS actual de la CNN de EC2.
2. Confirmación de que `ANALYSIS_API_KEY` ya está configurada en App Service o autorización para colocarla allí.
3. Dominio HTTPS definitivo del App Service.
4. `OPENAI_API_KEY` configurada en App Service.
5. `DOCUMENT_TOKEN_ENCRYPTION_KEY` configurada en App Service.

### Bloque B — documentos

6. Google OAuth Client ID y Client Secret configurados en App Service.
7. Microsoft Application Client ID y Client Secret configurados en App Service.
8. URLs de retorno registradas exactamente como aparecen en este documento.

### Bloque C — si el mapa entra en esta entrega

9. `GOOGLE_MAPS_BROWSER_KEY` restringida y configurada.

No necesito las contraseñas personales de Google o Microsoft de los establecimientos. Cada administrador autorizará su propia cuenta en la pantalla OAuth.

## 11. Plan de ejecución al recibir las credenciales

| Orden | Trabajo | Resultado comprobable |
| --- | --- | --- |
| 1 | Adaptador CNN v1 | Análisis real desde EC2, sin afirmar validaciones que v1 no realiza. |
| 2 | Informe híbrido | Borrador estructurado con OpenAI, editable y aprobado por el médico. |
| 3 | OAuth Google Drive | Conectar, guardar PDF, comprobar archivo y reintentar fallos. |
| 4 | OAuth OneDrive | Conectar, guardar PDF, comprobar archivo y reintentar fallos. |
| 5 | Exclusividad | Solo un proveedor activo por establecimiento y revocación al cambiar. |
| 6 | Mapa y secretos | Ubicación real, restricciones de claves y revisión de variables. |
| 7 | Pruebas | Recorrido médico y administrativo de principio a fin con servicios reales. |

## 12. CNN v2 al final

La CNN v2 añadirá:

- detección de retina/no retina;
- evaluación de calidad;
- rechazo sin probabilidades clínicas cuando la entrada no sea válida;
- nuevo contrato y versión del modelo;
- validación con dataset separado por paciente.

Hasta entonces se usará la salida clínica real de la CNN v1 y la interfaz indicará que las validaciones de entrada no están disponibles en esa versión.

## 13. Vistas de aceptación

| Rol | Vista |
| --- | --- |
| Médico | `views/medico/nuevoanalisis.php` |
| Médico | `views/medico/pacientes.php` |
| Médico | `views/medico/modeloinfo.php` |
| Administrador | `views/admin/integraciones.php` |
| Administrador | `views/admin/index.php` |
| Super Admin | `views/superadmin/calidad_modelo.php` |
| Registro | `views/auth/solicitud_registro.php` |

## 14. Fuentes técnicas verificadas

- OpenAI Responses API y generación de texto: https://developers.openai.com/api/docs/guides/text
- OpenAI Structured Outputs: https://developers.openai.com/api/docs/guides/structured-outputs
- Modelo `gpt-5.6-terra`: https://developers.openai.com/api/docs/models/gpt-5.6-terra
- Controles de datos de OpenAI API: https://developers.openai.com/api/docs/guides/your-data
- OAuth y alcance `drive.file`: https://developers.google.com/workspace/drive/api/guides/api-specific-auth
- Microsoft Graph y OneDrive: https://learn.microsoft.com/en-us/graph/api/resources/onedrive
- Autoridad Microsoft `common`: https://learn.microsoft.com/en-us/entra/identity-platform/v2-protocols-oidc
- Permisos Microsoft Graph: https://learn.microsoft.com/en-us/graph/permissions-reference
- Seguridad de claves de Google Maps: https://developers.google.com/maps/api-security-best-practices
