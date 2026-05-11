<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RetinAI — Solicitar Acceso</title>
    <meta name="description" content="Solicite acceso al sistema RetinAI como médico oftalmólogo. Su solicitud será revisada por el administrador del establecimiento.">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/auth/login.css">
</head>
<body>

<div class="auth-wrap">
    <div class="auth-card" style="max-width:500px;">

        <div class="auth-logo">
            <div class="auth-logo-mark">
                <svg viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" stroke="white" stroke-width="1.5"/>
                    <circle cx="12" cy="12" r="3.5" fill="white" opacity=".6"/>
                    <circle cx="12" cy="12" r="1.2" fill="white"/>
                </svg>
            </div>
            <h1>Retin<span>AI</span></h1>
            <p>Registrar cuenta médica</p>
        </div>

        <!-- Success state (hidden) -->
        <div id="success-box" style="display:none">
            <div class="alert-box alert-success">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px">
                    <circle cx="10" cy="10" r="9" stroke="#059669" stroke-width="1.5"/>
                    <path d="M6.5 10.5l2.5 2.5 4.5-5" stroke="#059669" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <p><strong>Registro enviado.</strong> Su solicitud quedará pendiente hasta la aprobación del administrador del establecimiento.</p>
            </div>
            <p style="text-align:center;font-size:13px;color:var(--text3);margin-top:12px">
                <a href="login.php" style="color:var(--accent);font-weight:600;text-decoration:none">← Volver al inicio de sesión</a>
            </p>
        </div>

        <!-- Error state -->
        <div id="error-box" class="alert-box alert-danger" style="display:none">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px">
                <circle cx="10" cy="10" r="9" stroke="#DC2626" stroke-width="1.5"/>
                <path d="M10 6v5M10 13h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <p id="error-msg">Error al enviar solicitud.</p>
        </div>

        <!-- Form -->
        <form id="register-form" novalidate>

            <p class="auth-section-title">Información personal</p>

            <div class="form-group">
                <label class="form-label" for="nombre">Nombre completo</label>
                <input class="form-input" type="text" id="nombre" name="nombre" placeholder="Dr. Juan Pérez García" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="correo">Correo electrónico</label>
                <input class="form-input" type="email" id="correo" name="correo" placeholder="medico@hospital.pe" required>
            </div>

            <p class="auth-section-title" style="margin-top:20px;">Datos profesionales</p>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="cmp">Número CMP</label>
                    <input class="form-input" type="text" id="cmp" name="cmp" placeholder="082341" required>
                    <p class="form-hint">Colegio Médico del Perú</p>
                </div>
                <div class="form-group">
                    <label class="form-label" for="especialidad">Especialidad</label>
                    <select class="form-select" id="especialidad" name="especialidad">
                        <option value="Oftalmología">Oftalmología</option>
                        <option value="Retinología">Retinología</option>
                        <option value="Glaucoma">Glaucoma</option>
                        <option value="Otra">Otra</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="institucion">Institución de origen</label>
                <input class="form-input" type="text" id="institucion" name="institucion" placeholder="Hospital Nacional Edgardo Rebagliati" required>
            </div>

            <div class="alert-box alert-info" style="margin-bottom:20px;">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px">
                    <circle cx="10" cy="10" r="9" stroke="#1A56DB" stroke-width="1.5"/>
                    <path d="M10 9v5M10 7h.01" stroke="#1A56DB" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <p>Su solicitud quedará <strong>pendiente de aprobación</strong> por el administrador del establecimiento.</p>
            </div>

            <button type="submit" class="btn btn-primary btn-full" id="reg-btn">
                Enviar solicitud de registro
            </button>
        </form>

        <div class="divider"></div>
        <p class="auth-footer-text">
            ¿Ya tiene cuenta? <a href="login.php">Iniciar sesión</a>
        </p>

    </div>
</div>

<script>
// Nota: Este formulario es RF-01 (prototipo). En producción haría POST al RegisterController.
// Por ahora muestra el estado de éxito del prototipo.
document.getElementById('register-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const nombre = document.getElementById('nombre').value.trim();
    const correo = document.getElementById('correo').value.trim();
    const cmp    = document.getElementById('cmp').value.trim();
    const inst   = document.getElementById('institucion').value.trim();
    const errBox = document.getElementById('error-box');
    const errMsg = document.getElementById('error-msg');

    // Validación cliente
    if (!nombre || !correo || !cmp || !inst) {
        errMsg.textContent = 'Por favor complete todos los campos obligatorios.';
        errBox.style.display = 'flex';
        return;
    }
    if (!/^\S+@\S+\.\S+$/.test(correo)) {
        errMsg.textContent = 'Ingrese un correo electrónico válido.';
        errBox.style.display = 'flex';
        return;
    }
    if (!/^\d{4,8}$/.test(cmp)) {
        errMsg.textContent = 'El número CMP debe tener entre 4 y 8 dígitos numéricos.';
        errBox.style.display = 'flex';
        return;
    }

    errBox.style.display = 'none';

    // Mostrar loading
    const btn = document.getElementById('reg-btn');
    btn.classList.add('loading');
    btn.textContent = '';

    // Simular delay (en producción esto sería un fetch al RegisterController)
    setTimeout(function() {
        document.getElementById('register-form').style.display = 'none';
        document.getElementById('success-box').style.display = 'block';
    }, 800);
});
</script>
</body>
</html>
