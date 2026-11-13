<?php
require_once __DIR__ . "/../../config/session_guard.php";
require_once __DIR__ . "/../../config/config.php";
require_once __DIR__ . "/../../controllers/DoctorController.php";
require_role("ADM");

$user = current_user();
$initials = get_initials($user["nombre"]);
$base = get_base_path();
$est_id = (int) ($user["establecimiento_id"] ?? 0);
$logout_url = $base . "controllers/AuthController.php?action=logout";

$role_label = "Administrador";
$role_class = "role-adm";
$avatar_class = "avatar-adm";

try {
    $cnt_pendientes = DoctorController::contarSolicitudesPendientes($est_id);
} catch (Exception $ex) {
    $cnt_pendientes = 0;
}

$error_msg = null;
if (isset($_SESSION["solicitud_error"])) {
    $error_msg = $_SESSION["solicitud_error"];
    unset($_SESSION["solicitud_error"]);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>RetinAI — Añadir Establecimiento</title>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/auth/solicitud_registro.css">

    <link rel="stylesheet" href="<?= $base ?>assets/css/views/admin/nuevoestablecimiento.css">
</head>

<body>

    <?php require_once __DIR__ . "/../shared/header.php"; ?>

    <div class="app-shell">
        <?php require_once __DIR__ . "/../shared/sidebar.php"; ?>

        <main class="main-content">

            <a href="misestablecimientos.php" class="back-link">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                    <path d="M15 10H5M10 5l-5 5 5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                        stroke-linejoin="round" />
                </svg>
                Volver a Mis Establecimientos
            </a>

            <div class="page-title-wrap">
                <h1 class="page-title">Añadir nuevo establecimiento</h1>
                <p class="page-sub">Complete la información para solicitar el registro de un nuevo centro oftalmológico.
                </p>
            </div>

            <?php if ($error_msg): ?>
            <div class="alert-box alert-danger ajuste-margen-inferior-20px-ancho-maximo-800px" role="alert">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" class="ajuste-reduccion-flex-0-margen-superior-1px">
                    <circle cx="10" cy="10" r="9" stroke="#DC2626" stroke-width="1.5" />
                    <path d="M10 6v5M10 13h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round" />
                </svg>
                <p><?= htmlspecialchars($error_msg) ?></p>
            </div>
            <?php endif; ?>

            <div class="auth-card">
                <form action="<?= $base ?>controllers/SolicitudController.php" method="POST" id="form-solicitud"
                    enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="action" value="solicitar_admin">
                    <input type="hidden" name="evidencia_1_b64" id="evidencia_1_b64">
                    <input type="hidden" name="evidencia_1_nombre" id="evidencia_1_nombre">
                    <input type="hidden" name="evidencia_2_b64" id="evidencia_2_b64">
                    <input type="hidden" name="evidencia_2_nombre" id="evidencia_2_nombre">

                    <div class="form-section-title">Datos del Centro</div>

                    <div class="form-group">
                        <label class="form-label" for="nombre_centro">Nombre del centro <span
                                class="req">*</span>
                        </label>
                        <input class="form-input" type="text" id="nombre_centro" name="nombre_centro"
                            placeholder="Ej. Clínica Visión Tacna" maxlength="100" required>
                        <span class="form-error oculto-inicialmente" id="err-nombre">Campo obligatorio.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="direccion">Dirección <span class="req">*</span>
                        </label>
                        <input class="form-input" type="text" id="direccion" name="direccion"
                            placeholder="Ej. Av. Bolognesi 245, Tacna" maxlength="200" required>
                        <span class="form-error oculto-inicialmente" id="err-direccion">Campo obligatorio.</span>
                    </div>
                    <div class="form-group">
                        <input type="hidden" id="latitud" name="latitud">
                        <input type="hidden" id="longitud" name="longitud">
                        <button class="btn btn-outline" type="button" id="obtener-ubicacion">Confirmar ubicación
                            opcional</button>
                        <button class="btn btn-outline" type="button" id="elegir-en-mapa">Elegir en el mapa</button>
                        <button class="btn btn-outline" type="button" id="buscar-en-mapa">Buscar dirección en el
                            mapa</button>
                        <span class="form-hint" id="estado-ubicacion">Si no concede acceso, la dirección escrita sigue
                            siendo suficiente.</span>
                        <div id="mapa-establecimiento" aria-label="Mapa para elegir la ubicación del establecimiento"
                            class="ajuste-visualizacion-none-height-300px-margen-superior-12px-border-radius-8px"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tipo de establecimiento <span class="req">*</span>
                        </label>
                        <div class="tipo-selector">
                            <div class="tipo-option">
                                <input type="radio" id="tipo_publico" name="tipo" value="publico">
                                <label for="tipo_publico">
                                    <span class="tipo-icon">
                                        <svg viewBox="0 0 24 24" fill="none"
                                            aria-hidden="true">
                                            <path d="m3 9 9-5 9 5M5 10v8m4-8v8m6-8v8m4-8v8M3 20h18"
                                                stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
                                                stroke-linejoin="round" />
                                        </svg>
                                    </span>Público
                                </label>
                            </div>
                            <div class="tipo-option">
                                <input type="radio" id="tipo_privado" name="tipo" value="privado">
                                <label for="tipo_privado">
                                    <span class="tipo-icon">
                                        <svg viewBox="0 0 24 24" fill="none"
                                            aria-hidden="true">
                                            <path d="M4 21V5h11v16M15 9h5v12M8 9h3M8 13h3M8 17h3" stroke="currentColor"
                                                stroke-width="1.7" stroke-linecap="round" />
                                        </svg>
                                    </span>Privado
                                </label>
                            </div>
                        </div>
                        <span class="form-error oculto-inicialmente" id="err-tipo">Seleccione el tipo.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="ruc">RUC del establecimiento <span class="req">*</span>
                        </label>
                        <input class="form-input" type="text" id="ruc" name="ruc" placeholder="00000000000"
                            maxlength="11" inputmode="numeric" autocomplete="off" required>
                        <span class="form-hint">Exactamente 11 dígitos.</span>
                        <span class="form-error oculto-inicialmente" id="err-ruc"></span>
                    </div>

                    <div class="form-section-title">Datos del Titular / Dueño</div>

                    <div class="form-group">
                        <label class="form-label" for="dni_titular">DNI del titular <span class="req">*</span>
                        </label>
                        <input class="form-input" type="text" id="dni_titular" name="dni_titular" placeholder="00000000"
                            maxlength="8" inputmode="numeric" autocomplete="off" required>
                        <span class="form-hint">8 dígitos.</span>
                        <span class="form-error oculto-inicialmente" id="err-dni"></span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="nombres_titular">Nombres <span class="req">*</span>
                            </label>
                            <input class="form-input" type="text" id="nombres_titular" name="nombres_titular"
                                placeholder="Ej. Juan Carlos" maxlength="100" required>
                            <span class="form-error oculto-inicialmente" id="err-nombres">Campo obligatorio.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="apellidos_titular">Apellidos <span
                                    class="req">*</span>
                            </label>
                            <input class="form-input" type="text" id="apellidos_titular" name="apellidos_titular"
                                placeholder="Ej. Pérez García" maxlength="100" required>
                            <span class="form-error oculto-inicialmente" id="err-apellidos">Campo obligatorio.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="telefono">Número telefónico <span class="req">*</span>
                        </label>
                        <input class="form-input" type="tel" id="telefono" name="telefono" placeholder="Ej. 952 345 678"
                            maxlength="15" required>
                        <span class="form-error oculto-inicialmente" id="err-telefono">Ingrese un número válido.</span>
                    </div>

                    <div class="form-section-title">Evidencias del Establecimiento</div>

                    <p class="form-hint ajuste-margen-inferior-12px-tamano-texto-12px-color-text2">
                        Adjunte documentos o imágenes que acrediten la existencia del establecimiento (ej. licencia,
                        fachada, etc.). <strong>Mínimo 1, máximo 2 archivos. Máx. 200 KB cada uno.</strong>
                    </p>

                    <div class="upload-area" id="upload-area" ondragover="handleDragOver(event)"
                        ondragleave="handleDragLeave(event)" ondrop="handleDrop(event)">
                        <div class="upload-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M3 7h7l2 2h9v11H3z" stroke="currentColor" stroke-width="1.7"
                                    stroke-linejoin="round" />
                                <path d="M12 17v-5m0 0-2 2m2-2 2 2" stroke="currentColor" stroke-width="1.7"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <h4>Arrastra aquí o haz clic para seleccionar</h4>
                        <p>JPG, PNG o PDF — máx. 200 KB por archivo</p>
                        <input type="file" id="file-input" accept=".jpg,.jpeg,.png,.pdf" multiple>
                    </div>
                    <span class="form-error-upload oculto-inicialmente" id="err-upload"></span>

                    <div class="upload-previews" id="upload-previews"></div>

                    <div id="client-error-box" class="alert-box alert-danger ajuste-visualizacion-none-margen-superior-16px" role="alert">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="none" class="ajuste-reduccion-flex-0-margen-superior-1px">
                            <circle cx="10" cy="10" r="9" stroke="#DC2626" stroke-width="1.5" />
                            <path d="M10 6v5M10 13h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                        <p id="client-error-msg"></p>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full ajuste-margen-superior-24px" id="submit-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z" stroke="white" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Enviar solicitud
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script src="<?= $base ?>assets/js/session.service.js"></script>
    <script src="<?= $base ?>assets/js/admin/nuevoestablecimiento.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="<?= $base ?>assets/js/ubicacion_establecimiento.js"></script>
    <script>
        SessionService.init({
            timeout: 300000,
            loginUrl: '<?= htmlspecialchars(
    $base . "views/auth/login.php",
) ?>'
        });
    </script>
</body>

</html>