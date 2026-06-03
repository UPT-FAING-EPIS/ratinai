<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_role('ADM');

$user     = current_user();
$initials = get_initials($user['nombre']);
$base     = get_base_path();
$est_id   = (int)($user['establecimiento_id'] ?? 0);
$logout_url = $base . 'controllers/AuthController.php?action=logout';

$role_label   = '🛡️ Administrador';
$role_class   = 'role-adm';
$avatar_class = 'avatar-adm';

try {
    $db = (new Database())->getConnection();
    $cnt_pendientes = (int)$db->query(
        "SELECT COUNT(*) FROM usuarios WHERE rol_codigo='MED' AND activo=0 AND establecimiento_id=$est_id"
    )->fetchColumn();
} catch (Exception $ex) {
    $cnt_pendientes = 0;
}

$error_msg = null;
if (isset($_SESSION['solicitud_error'])) {
    $error_msg = $_SESSION['solicitud_error'];
    unset($_SESSION['solicitud_error']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Añadir Establecimiento</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<link rel="stylesheet" href="<?= $base ?>assets/css/auth/solicitud_registro.css">
<style>
/* Ajustes para integrar el css de auth al dashboard */
.auth-card {
    box-shadow: none;
    border: 1px solid #e2e8f0;
    max-width: 800px;
    margin: 0;
    padding: 30px;
    border-radius: 12px;
    background: #fff;
}
.page-title-wrap { margin-bottom: 24px; }
.page-title { margin-bottom: 4px; }
.back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 500; color: #64748b; text-decoration: none; margin-bottom: 20px; transition: color .2s; }
.back-link:hover { color: #0f172a; }

/* Estilos de inputs faltantes copiados de login.css */
.form-group { margin-bottom: 18px; text-align: left; }
.form-row { display: flex; gap: 14px; }
.form-row .form-group { flex: 1; }
.form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text2); margin-bottom: 6px; letter-spacing: 0.2px; text-transform: uppercase; }
.form-input { width: 100%; padding: 11px 14px; border: 1.5px solid var(--border2); border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 14px; color: var(--text); background: var(--surface); transition: border 0.15s, box-shadow 0.15s; outline: none; }
.form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(26,86,219,0.12); }
.form-input.error { border-color: var(--danger); box-shadow: 0 0 0 3px rgba(220,38,38,0.10); }
.form-input::placeholder { color: var(--text3); }
.form-hint  { font-size: 11px; color: var(--text3); margin-top: 4px; display: block; }
.form-error { font-size: 11px; color: var(--danger); margin-top: 4px; display: block; }
</style>
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">

        <a href="misestablecimientos.php" class="back-link">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M15 10H5M10 5l-5 5 5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Volver a Mis Establecimientos
        </a>

        <div class="page-title-wrap">
            <h1 class="page-title">Añadir nuevo establecimiento</h1>
            <p class="page-sub">Complete la información para solicitar el registro de un nuevo centro oftalmológico.</p>
        </div>

        <?php if ($error_msg): ?>
        <div class="alert-box alert-danger" role="alert" style="margin-bottom:20px; max-width:800px;">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px">
                <circle cx="10" cy="10" r="9" stroke="#DC2626" stroke-width="1.5"/>
                <path d="M10 6v5M10 13h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <p><?= htmlspecialchars($error_msg) ?></p>
        </div>
        <?php endif; ?>

        <div class="auth-card">
            <form action="<?= $base ?>controllers/SolicitudController.php" method="POST" id="form-solicitud" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="action" value="solicitar_admin">
                <input type="hidden" name="evidencia_1_b64"    id="evidencia_1_b64">
                <input type="hidden" name="evidencia_1_nombre" id="evidencia_1_nombre">
                <input type="hidden" name="evidencia_2_b64"    id="evidencia_2_b64">
                <input type="hidden" name="evidencia_2_nombre" id="evidencia_2_nombre">

                <div class="form-section-title">🏥 Datos del Centro</div>

                <div class="form-group">
                    <label class="form-label" for="nombre_centro">Nombre del centro <span class="req">*</span></label>
                    <input class="form-input" type="text" id="nombre_centro" name="nombre_centro" placeholder="Ej. Clínica Visión Tacna" maxlength="100" required>
                    <span class="form-error" id="err-nombre" style="display:none">Campo obligatorio.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="direccion">Dirección <span class="req">*</span></label>
                    <input class="form-input" type="text" id="direccion" name="direccion" placeholder="Ej. Av. Bolognesi 245, Tacna" maxlength="200" required>
                    <span class="form-error" id="err-direccion" style="display:none">Campo obligatorio.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Tipo de establecimiento <span class="req">*</span></label>
                    <div class="tipo-selector">
                        <div class="tipo-option">
                            <input type="radio" id="tipo_publico" name="tipo" value="publico">
                            <label for="tipo_publico"><span class="tipo-icon">🏛️</span>Público</label>
                        </div>
                        <div class="tipo-option">
                            <input type="radio" id="tipo_privado" name="tipo" value="privado">
                            <label for="tipo_privado"><span class="tipo-icon">🏥</span>Privado</label>
                        </div>
                    </div>
                    <span class="form-error" id="err-tipo" style="display:none">Seleccione el tipo.</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="ruc">RUC del establecimiento <span class="req">*</span></label>
                    <input class="form-input" type="text" id="ruc" name="ruc" placeholder="00000000000" maxlength="11" inputmode="numeric" autocomplete="off" required>
                    <span class="form-hint">Exactamente 11 dígitos.</span>
                    <span class="form-error" id="err-ruc" style="display:none"></span>
                </div>

                <div class="form-section-title">👤 Datos del Titular / Dueño</div>

                <div class="form-group">
                    <label class="form-label" for="dni_titular">DNI del titular <span class="req">*</span></label>
                    <input class="form-input" type="text" id="dni_titular" name="dni_titular" placeholder="00000000" maxlength="8" inputmode="numeric" autocomplete="off" required>
                    <span class="form-hint">8 dígitos.</span>
                    <span class="form-error" id="err-dni" style="display:none"></span>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="nombres_titular">Nombres <span class="req">*</span></label>
                        <input class="form-input" type="text" id="nombres_titular" name="nombres_titular" placeholder="Ej. Juan Carlos" maxlength="100" required>
                        <span class="form-error" id="err-nombres" style="display:none">Campo obligatorio.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="apellidos_titular">Apellidos <span class="req">*</span></label>
                        <input class="form-input" type="text" id="apellidos_titular" name="apellidos_titular" placeholder="Ej. Pérez García" maxlength="100" required>
                        <span class="form-error" id="err-apellidos" style="display:none">Campo obligatorio.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="telefono">Número telefónico <span class="req">*</span></label>
                    <input class="form-input" type="tel" id="telefono" name="telefono" placeholder="Ej. 952 345 678" maxlength="15" required>
                    <span class="form-error" id="err-telefono" style="display:none">Ingrese un número válido.</span>
                </div>

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

                <div id="client-error-box" class="alert-box alert-danger" style="display:none;margin-top:16px;" role="alert">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px">
                        <circle cx="10" cy="10" r="9" stroke="#DC2626" stroke-width="1.5"/>
                        <path d="M10 6v5M10 13h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <p id="client-error-msg"></p>
                </div>

                <button type="submit" class="btn btn-primary btn-full" id="submit-btn" style="margin-top:24px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Enviar solicitud
                </button>
            </form>
        </div>
    </main>
</div>

<script src="<?= $base ?>assets/js/session.service.js"></script>
<script src="<?= $base ?>assets/js/admin/nuevoestablecimiento.js"></script>
<script>
SessionService.init({ timeout: 300000, loginUrl: '<?= htmlspecialchars($base . "views/auth/login.php") ?>' });
let remaining = 300;
const cd = document.getElementById('session-countdown');
if(cd) {
    setInterval(() => {
        const m = Math.floor(remaining / 60).toString().padStart(2, '0');
        const s = (remaining % 60).toString().padStart(2, '0');
        cd.textContent = m + ':' + s;
        if (remaining > 0) remaining--;
    }, 1000);
}
</script>
</body>
</html>
