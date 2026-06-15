<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_auth();
require_role('MED');
$user = current_user();
$initials = get_initials($user['nombre']);
$base = get_base_path();
$logout_url = $base . 'controllers/AuthController.php?action=logout';

$role_label   = '🩺 Médico';
$role_class   = 'role-med';
$avatar_class = 'avatar-green';
$header_sub   = 'Médico Oftalmólogo';

$_page = 'modeloinfo.php';
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
            <p class="page-sub">Métricas de rendimiento y características del modelo CNN.</p>
        </div>
        <div class="grid-2" style="gap:20px">
            <div>
              <div class="card">
                <div class="card-title">Métricas de rendimiento</div>
                <div class="metric-row"><span>Precisión (Accuracy)</span><strong>95.4%</strong></div>
                <div class="metric-row"><span>Sensibilidad</span><strong>93.8%</strong></div>
                <div class="metric-row"><span>Especificidad</span><strong>96.1%</strong></div>
                <div class="metric-row"><span>Dataset de entrenamiento</span><strong>ODIR-5K</strong></div>
                <div class="metric-row"><span>Arquitectura</span><strong>CNN · TF Lite</strong></div>
                <div class="metric-row"><span>Resolución de entrada</span><strong>224×224 px</strong></div>
              </div>
            </div>
            <div>
              <div class="card">
                <div class="card-title">Categorías detectables</div>
                <div class="metric-row"><span>🔴 Retinopatía Diabética</span><strong>Clase 1</strong></div>
                <div class="metric-row"><span>🟠 Glaucoma</span><strong>Clase 2</strong></div>
                <div class="metric-row"><span>🔵 Catarata</span><strong>Clase 3</strong></div>
                <div class="metric-row"><span>🟢 Normal</span><strong>Clase 4</strong></div>
              </div>
              <div class="card mt-8">
                <div class="card-title">Advertencia clínica</div>
                <div class="warning-banner" style="margin:0">
                  <svg width="14" height="14" viewBox="0 0 20 20" fill="none" style="flex-shrink:0"><circle cx="10" cy="10" r="9" stroke="#D97706" stroke-width="1.5"/><path d="M10 6v5M10 13h.01" stroke="#D97706" stroke-width="1.5" stroke-linecap="round"/></svg>
                  <p>Este sistema es de apoyo diagnóstico referencial. Siempre debe ser validado por el criterio clínico del médico especialista.</p>
                </div>
              </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
