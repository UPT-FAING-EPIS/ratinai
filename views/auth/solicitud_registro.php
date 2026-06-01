<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$success_msg = null;
$error_msg   = null;

if (isset($_SESSION['solicitud_success'])) {
    $success_msg = $_SESSION['solicitud_success'];
    unset($_SESSION['solicitud_success']);
}
if (isset($_SESSION['solicitud_error'])) {
    $error_msg = $_SESSION['solicitud_error'];
    unset($_SESSION['solicitud_error']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RetinAI — Registrar Centro Oftalmológico</title>
    <meta name="description" content="Solicite el registro de su centro oftalmológico en RetinAI. Complete el formulario con los datos del establecimiento y del titular.">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/auth/login.css">
    <link rel="stylesheet" href="../../assets/css/auth/solicitud_registro.css">
</head>
<body>

<div class="auth-wrap">
    <div class="auth-card">

        <!-- Logo -->
        <div class="auth-logo">
            <div class="auth-logo-mark">
                <svg viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" stroke="white" stroke-width="1.5"/>
                    <circle cx="12" cy="12" r="3.5" fill="white" opacity=".6"/>
                    <circle cx="12" cy="12" r="1.2" fill="white"/>
                </svg>
            </div>
            <h1>Retin<span>AI</span></h1>
            <p>Solicitud de Registro de Centro Oftalmológico</p>
        </div>

        <?php if ($success_msg): ?>
        <!-- ── Pantalla de éxito ── -->
        <div class="success-screen">
            <div class="success-icon">✅</div>
            <h2>¡Solicitud enviada!</h2>
            <p><?= htmlspecialchars($success_msg) ?></p>
            <a href="../../index.php" class="back-home-link">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M15 10H5M10 5l-5 5 5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Volver al inicio
            </a>
        </div>

        <?php else: ?>

        <?php if ($error_msg): ?>
        <div class="alert-box alert-danger" id="server-error-box" role="alert">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px">
                <circle cx="10" cy="10" r="9" stroke="#DC2626" stroke-width="1.5"/>
                <path d="M10 6v5M10 13h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <p><?= htmlspecialchars($error_msg) ?></p>
        </div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════ FORMULARIO ═══════════════════════════════════════ -->
        <form
            action="../../controllers/SolicitudController.php"
            method="POST"
            id="form-solicitud"
            enctype="multipart/form-data"
            novalidate
        >
            <input type="hidden" name="action" value="solicitar">
            <!-- Evidencias en base64 (llenadas por JS) -->
            <input type="hidden" name="evidencia_1_b64"    id="evidencia_1_b64">
            <input type="hidden" name="evidencia_1_nombre" id="evidencia_1_nombre">
            <input type="hidden" name="evidencia_2_b64"    id="evidencia_2_b64">
            <input type="hidden" name="evidencia_2_nombre" id="evidencia_2_nombre">

            <!-- ──────────────────────────────────────
                 SECCIÓN 1: Datos del Centro
            ────────────────────────────────────── -->
            <div class="form-section-title">🏥 Datos del Centro</div>

            <div class="form-group">
                <label class="form-label" for="nombre_centro">Nombre del centro <span class="req">*</span></label>
                <input class="form-input" type="text" id="nombre_centro" name="nombre_centro"
                    placeholder="Ej. Clínica Visión Tacna" maxlength="100" required>
                <span class="form-error" id="err-nombre" style="display:none">Campo obligatorio.</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="direccion">Dirección <span class="req">*</span></label>
                <input class="form-input" type="text" id="direccion" name="direccion"
                    placeholder="Ej. Av. Bolognesi 245, Tacna" maxlength="200" required>
                <span class="form-error" id="err-direccion" style="display:none">Campo obligatorio.</span>
            </div>

            <div class="form-group">
                <label class="form-label">Tipo de establecimiento <span class="req">*</span></label>
                <div class="tipo-selector">
                    <div class="tipo-option">
                        <input type="radio" id="tipo_publico" name="tipo" value="publico">
                        <label for="tipo_publico">
                            <span class="tipo-icon">🏛️</span>Público
                        </label>
                    </div>
                    <div class="tipo-option">
                        <input type="radio" id="tipo_privado" name="tipo" value="privado">
                        <label for="tipo_privado">
                            <span class="tipo-icon">🏥</span>Privado
                        </label>
                    </div>
                </div>
                <span class="form-error" id="err-tipo" style="display:none">Seleccione el tipo.</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="ruc">RUC del establecimiento <span class="req">*</span></label>
                <input class="form-input" type="text" id="ruc" name="ruc"
                    placeholder="00000000000" maxlength="11" inputmode="numeric" autocomplete="off" required>
                <span class="form-hint">Exactamente 11 dígitos.</span>
                <span class="form-error" id="err-ruc" style="display:none"></span>
            </div>

            <!-- ──────────────────────────────────────
                 SECCIÓN 2: Datos del Titular
            ────────────────────────────────────── -->
            <div class="form-section-title">👤 Datos del Titular / Dueño</div>

            <div class="form-group">
                <label class="form-label" for="dni_titular">DNI del titular <span class="req">*</span></label>
                <input class="form-input" type="text" id="dni_titular" name="dni_titular"
                    placeholder="00000000" maxlength="8" inputmode="numeric" autocomplete="off" required>
                <span class="form-hint">8 dígitos.</span>
                <span class="form-error" id="err-dni" style="display:none"></span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="nombres_titular">Nombres <span class="req">*</span></label>
                    <input class="form-input" type="text" id="nombres_titular" name="nombres_titular"
                        placeholder="Ej. Juan Carlos" maxlength="100" required>
                    <span class="form-error" id="err-nombres" style="display:none">Campo obligatorio.</span>
                </div>
                <div class="form-group">
                    <label class="form-label" for="apellidos_titular">Apellidos <span class="req">*</span></label>
                    <input class="form-input" type="text" id="apellidos_titular" name="apellidos_titular"
                        placeholder="Ej. Pérez García" maxlength="100" required>
                    <span class="form-error" id="err-apellidos" style="display:none">Campo obligatorio.</span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="telefono">Número telefónico <span class="req">*</span></label>
                <input class="form-input" type="tel" id="telefono" name="telefono"
                    placeholder="Ej. 952 345 678" maxlength="15" required>
                <span class="form-error" id="err-telefono" style="display:none">Ingrese un número válido.</span>
            </div>

            <!-- ──────────────────────────────────────
                 SECCIÓN 3: Verificación de correo
            ────────────────────────────────────── -->
            <div class="form-section-title">📧 Correo y Verificación</div>

            <p class="form-hint" style="margin-bottom:14px;font-size:12px;color:var(--text2);">
                El correo ingresado será usado para crear su cuenta de acceso a RetinAI una vez aprobada la solicitud.
            </p>

            <div class="form-group">
                <label class="form-label" for="correo_contacto">Correo electrónico <span class="req">*</span></label>
                <div class="verify-row">
                    <div class="form-group">
                        <input class="form-input" type="email" id="correo_contacto" name="correo_contacto"
                            placeholder="contacto@micentro.pe" maxlength="100" required autocomplete="email">
                    </div>
                    <button type="button" class="btn-send-code" id="btn-send-code" onclick="enviarCodigo()">
                        Enviar código
                    </button>
                </div>
                <span class="form-error" id="err-correo" style="display:none"></span>
            </div>

            <div class="code-sent-msg" id="code-sent-msg">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M16 4l-10 10-4-4" stroke="#059669" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span id="code-sent-text">Código enviado.</span>
                <span class="code-resend-link disabled" id="resend-link" onclick="reenviarCodigo()">Reenviar (<span id="resend-cd">60</span>s)</span>
            </div>

            <div class="code-input-wrap" id="code-input-wrap">
                <div class="form-group">
                    <label class="form-label" for="codigo_verificacion">Código de verificación <span class="req">*</span></label>
                    <input class="form-input code-input" type="text" id="codigo_verificacion" name="codigo_verificacion"
                        placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
                    <div class="countdown-text">Expira en <span id="expire-cd">10:00</span></div>
                    <span class="form-error" id="err-codigo" style="display:none"></span>
                </div>
            </div>

            <!-- ──────────────────────────────────────
                 SECCIÓN 4: Evidencias
            ────────────────────────────────────── -->
            <div class="form-section-title">📎 Evidencias del Establecimiento</div>

            <p class="form-hint" style="margin-bottom:12px;font-size:12px;color:var(--text2);">
                Adjunte documentos o imágenes que acrediten la existencia del establecimiento (ej. licencia, fachada, etc.). <strong>Mínimo 1, máximo 2 archivos. Máx. 200 KB cada uno.</strong>
            </p>

            <div class="upload-area" id="upload-area" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event)">
                <div class="upload-icon-wrap">📁</div>
                <h4>Arrastra aquí o haz clic para seleccionar</h4>
                <p>JPG, PNG o PDF — máx. 200 KB por archivo</p>
                <input type="file" id="file-input" accept=".jpg,.jpeg,.png,.pdf" multiple>
            </div>
            <span class="form-error-upload" id="err-upload" style="display:none"></span>

            <div class="upload-previews" id="upload-previews"></div>

            <!-- ──────────────────────────────────────
                 ERROR GLOBAL y SUBMIT
            ────────────────────────────────────── -->
            <div id="client-error-box" class="alert-box alert-danger" style="display:none;margin-top:16px;" role="alert">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px">
                    <circle cx="10" cy="10" r="9" stroke="#DC2626" stroke-width="1.5"/>
                    <path d="M10 6v5M10 13h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <p id="client-error-msg"></p>
            </div>

            <button type="submit" class="btn btn-primary btn-full" id="submit-btn" style="margin-top:12px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Enviar solicitud
            </button>
        </form>

        <div class="divider"></div>
        <p class="auth-footer-text">
            ¿Ya tiene cuenta? <a href="login.php">Ingresar a la plataforma</a>
        </p>

        <?php endif; ?>
    </div>
</div>

<script src="../../assets/js/auth/solicitud_registro.js"></script>
</body>
</html>
