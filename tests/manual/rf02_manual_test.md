# RF-02 — Prueba Manual: Inicio de Sesión

**Sistema**: RetinAI v2  
**Fecha**: ___________________  
**Ejecutado por**: ___________________  
**Entorno**: ☐ Local  ☐ Azure (retinai.azurewebsites.net)

---

## Objetivo

Verificar manualmente que:
1. Un médico con clave temporal sea bloqueado de acceder a otras funciones
2. El sistema redirija obligatoriamente al cambio de contraseña
3. La sesión expire tras el tiempo configurado de inactividad

---

## Pre-condiciones

- [ ] La BD Railway está accesible
- [ ] El usuario `medico@hospital.com` existe con `es_password_temporal = 1`
- [ ] Password del médico: `admin123`
- [ ] Navegador limpio (sin cookies de sesión anteriores)

---

## Caso 1: Inicio de sesión con clave temporal

| # | Paso | Resultado Esperado | ✓/✗ | Observaciones |
|---|------|--------------------|-----|---------------|
| 1 | Navegar a `/views/auth/login.php` | Se muestra el formulario de login con logo RetinAI | | |
| 2 | Ingresar `medico@hospital.com` en el campo correo | El campo acepta el texto | | |
| 3 | Ingresar `admin123` en el campo contraseña | El campo muestra ••••••• | | |
| 4 | Hacer clic en "Iniciar sesión" | El botón muestra estado de carga (spinner) | | |
| 5 | **Verificar redirección** | La URL cambia a `.../views/auth/change_password.php` | | |
| 6 | Verificar que el formulario de cambio de contraseña está visible | Se muestran los campos "Nueva contraseña" y "Confirmar contraseña" | | |
| 7 | Intentar navegar manualmente a `/views/dashboard/medico/index.php` | **El sistema DEBE redirigir de vuelta a `change_password.php`** (sesión con clave temporal bloquea acceso) | | |

> **CRITERIO PRINCIPAL**: El paso 7 es crítico. Si el médico puede acceder al dashboard con clave temporal, el test FALLA.

---

## Caso 2: Bloqueo de funciones hasta cambio de clave

| # | Paso | Resultado Esperado | ✓/✗ |
|---|------|--------------------|-----|
| 1 | Con sesión de clave temporal activa, intentar acceder a `/views/dashboard/medico/index.php` | Redirigir a `change_password.php` | |
| 2 | Intentar acceder a cualquier otra ruta protegida | Redirigir a `change_password.php` | |
| 3 | En el formulario de cambio, ingresar `nueva123` y `nueva123` y enviar | Redirigir al dashboard del médico | |
| 4 | Verificar que ahora SÍ puede acceder al dashboard | El dashboard carga correctamente | |
| 5 | Verificar en BD que `es_password_temporal = 0` para el usuario | Campo actualizado correctamente | |

---

## Caso 3: Expiración de sesión por inactividad

**Duración estimada del test**: 60+ minutos (o simular con timeout reducido)

| # | Paso | Resultado Esperado | ✓/✗ |
|---|------|--------------------|-----|
| 1 | Iniciar sesión como médico (con clave permanente) | Acceso al dashboard | |
| 2 | Verificar que el contador en la barra lateral muestra `60:00` | Contador visible y activo | |
| 3 | No interactuar con el sistema durante el tiempo configurado | Contador decrece visualmente | |
| 4 | **Al llegar a `00:00`** | El sistema redirige a `/views/auth/login.php?expired=1` | |
| 5 | Verificar el mensaje de sesión expirada | Se muestra alerta amarilla: "Su sesión expiró por inactividad" | |
| 6 | Intentar navegar al dashboard directamente | Redirigir al login | |

> **NOTA PARA TEST RÁPIDO**: Para no esperar 60 minutos, modificar temporalmente en `session_guard.php`:  
> `define('SESSION_TIMEOUT', 60); // 1 minuto para testing`  
> Y en el `SessionService.init()` de la vista del dashboard:  
> `timeout: 60000` → `timeout: 10000` (10 segundos)

---

## Caso 4: Credenciales incorrectas

| # | Paso | Resultado Esperado | ✓/✗ |
|---|------|--------------------|-----|
| 1 | Ingresar correo válido + contraseña incorrecta | Mostrar alerta roja "Credenciales incorrectas" | |
| 2 | Ingresar correo inexistente | Mostrar alerta roja "Credenciales incorrectas" | |
| 3 | Enviar formulario vacío | Mostrar alerta cliente (sin request al servidor) | |

---

## Resultado Global

| Caso | Estado | Notas |
|------|--------|-------|
| Caso 1: Login con clave temporal | ☐ PASS ☐ FAIL | |
| Caso 2: Bloqueo de funciones | ☐ PASS ☐ FAIL | |
| Caso 3: Expiración de sesión | ☐ PASS ☐ FAIL | |
| Caso 4: Credenciales incorrectas | ☐ PASS ☐ FAIL | |

**Veredicto final**: ☐ APROBADO  ☐ RECHAZADO

**Firma**: ___________________ **Fecha**: ___________________
