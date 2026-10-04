<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_auth();
require_role('MED');
$user = current_user();
$initials = get_initials($user['nombre']);
$base = get_base_path();
$logout_url = $base . 'controllers/AuthController.php?action=logout';

$role_label   = 'Médico';
$role_class   = 'role-med';
$avatar_class = 'avatar-green';
$header_sub   = 'Médico Oftalmólogo';

$_page = 'modeloinfo.php';
$servicioDisponible = servicioAnalisisRemotoDisponible();
$proveedor = $servicioDisponible ? 'Servicio CNN remoto' : 'No configurado';
$versionActiva = $servicioDisponible ? 'Informada por el servicio remoto en cada análisis' : 'Sin versión clínica activa';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RetinAI — Información del Modelo</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Información del modelo IA</h1>
            <p class="page-sub">Contrato activo, alcance y evidencia disponible del análisis retinal.</p>
        </div>
        <div class="grid-2" style="gap:20px">
            <div>
              <div class="card">
                <div class="card-title">Versión activa</div>
                <?php if (!$servicioDisponible): ?>
                <div class="alert-box alert-warning" style="margin-bottom:14px"><p>El servicio CNN remoto todavía no está configurado. No se muestran resultados ni métricas de demostración como si fueran clínicos.</p></div>
                <?php endif; ?>
                <div class="metric-row"><span>Proveedor</span><strong><?= htmlspecialchars($proveedor) ?></strong></div>
                <div class="metric-row"><span>Versión</span><strong><?= htmlspecialchars($versionActiva) ?></strong></div>
                <div class="metric-row"><span>Entrada</span><strong>JPG o PNG</strong></div>
                <div class="metric-row"><span>Contenido retinal</span><strong>No evaluado por CNN v1</strong></div>
                <div class="metric-row"><span>Calidad de captura</span><strong>No evaluada por CNN v1</strong></div>
                <div class="metric-row"><span>Métricas clínicas verificadas</span><strong>Pendientes</strong></div>
                <p class="text-muted" style="font-size:12px;margin-top:12px">No se publican cifras de precisión, sensibilidad o especificidad hasta contar con un conjunto de prueba separado por paciente y un protocolo documentado.</p>
              </div>
            </div>
            <div>
              <div class="card">
                <div class="card-title">Categorías detectables</div>
                <div class="metric-row"><span><svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="#dc2626"/></svg> Retinopatía Diabética</span><strong>Clase 1</strong></div>
                <div class="metric-row"><span><svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="#ea580c"/></svg> Glaucoma</span><strong>Clase 2</strong></div>
                <div class="metric-row"><span><svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="#2563eb"/></svg> Catarata</span><strong>Clase 3</strong></div>
                <div class="metric-row"><span><svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="#16a34a"/></svg> Normal</span><strong>Clase 4</strong></div>
              </div>
              <div class="card mt-8">
                <div class="card-title">Limitaciones</div>
                <div class="warning-banner" style="margin:0">
                  <svg width="14" height="14" viewBox="0 0 20 20" fill="none" style="flex-shrink:0"><circle cx="10" cy="10" r="9" stroke="#D97706" stroke-width="1.5"/><path d="M10 6v5M10 13h.01" stroke="#D97706" stroke-width="1.5" stroke-linecap="round"/></svg>
                  <p>Este sistema ofrece apoyo referencial. La salida requiere revisión y conclusión del médico especialista. Cuando el servicio remoto no está configurado, el análisis permanece bloqueado.</p>
                </div>
                <p class="text-muted" style="font-size:12px;margin-top:12px">La CNN v1 analiza archivos JPG o PNG válidos, pero no determina si la imagen muestra una retina ni si su calidad es suficiente. El médico debe revisar la captura. La comparación entre versiones distintas muestra una advertencia porque sus valores pueden no ser equivalentes.</p>
              </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
