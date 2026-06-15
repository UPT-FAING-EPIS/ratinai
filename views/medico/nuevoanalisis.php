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

$_page = 'nuevoanalisis.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RetinAI — Nuevo Análisis</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<script>const BASE_URL = '<?= $base ?>';</script>
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div class="page-header-row">
                <div>
                <h1 class="page-title">Nuevo análisis retinal</h1>
                <p class="page-sub">Cargue una retinografía para obtener el diagnóstico asistido por IA.</p>
                </div>
            </div>
        </div>

        <div class="stepper">
            <div class="step"><div class="step-circle active" id="step1">1</div><span class="step-label active">Cargar imagen</span></div>
            <div class="step-line" id="line1"></div>
            <div class="step"><div class="step-circle pending" id="step2">2</div><span class="step-label" id="lbl-step2">Procesando</span></div>
            <div class="step-line" id="line2"></div>
            <div class="step"><div class="step-circle pending" id="step3">✓</div><span class="step-label" id="lbl-step3">Resultado</span></div>
        </div>

        <div class="grid-2" style="gap:20px">
            <div>
                <div class="card mb-16" id="upload-card">
                <div class="card-title">
                    <div class="card-title-icon blue">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12" stroke="#1A56DB" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    Cargar retinografía
                </div>
                <div class="upload-zone" id="drop-zone" onclick="document.getElementById('file-input').click()">
                    <div class="upload-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><rect x="2" y="2" width="20" height="20" rx="3" stroke="#1A56DB" stroke-width="1.5"/><circle cx="12" cy="12" r="5" stroke="#1A56DB" stroke-width="1.5"/><circle cx="12" cy="12" r="2" fill="#1A56DB" opacity=".4"/></svg>
                    </div>
                    <h3>Arrastra tu retinografía aquí o haz clic para seleccionar</h3>
                    <p>JPG o PNG · Máximo 10 MB</p>
                    <input type="file" id="file-input" accept=".jpg,.jpeg,.png" style="display:none">
                </div>
                
                <!-- Quality warning area -->
                <div id="quality-warning" class="alert-box alert-warning" style="display:none; margin-top:16px;">
                    <p>La imagen puede presentar baja calidad. Los resultados podrían ser menos precisos. Puede continuar el análisis.</p>
                </div>

                <div id="preview-area" style="display:none;margin-top:16px;">
                    <div class="retina-img" id="preview-img" style="max-width:100%; display:flex; justify-content:center; align-items:center; background:#f3f4f6; border-radius:8px; overflow:hidden;">
                        <img id="preview-image-element" src="" style="max-width:100%; max-height:300px; object-fit:contain;">
                    </div>
                    <div style="margin-top:12px;text-align:center;">
                    <p id="file-name" class="text-sm text-muted"></p>
                    <button class="btn btn-primary mt-8" id="analyze-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M12 5l7 7-7 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Analizar imagen
                    </button>
                    </div>
                </div>
                </div>

                <div class="card">
                <div class="card-title">
                    <div class="card-title-icon green">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke="#059669" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="7" r="4" stroke="#059669" stroke-width="1.8"/></svg>
                    </div>
                    Identificación del paciente
                </div>
                <div class="form-group-d">
                    <label class="form-label-d" for="dni-input">DNI del paciente</label>
                    <input class="form-input-d" type="text" id="dni-input" placeholder="12345678" maxlength="8">
                </div>
                <button class="btn btn-secondary btn-sm" id="btn-buscar-paciente">Buscar / registrar paciente</button>
                <div id="paciente-result" style="margin-top:12px;display:none"></div>
                </div>
            </div>

            <div id="result-col" style="display:none;">
                <div class="card">
                <div class="card-title">
                    <div class="card-title-icon red">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#DC2626" stroke-width="1.5"/><path d="M12 8v5M12 15h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round"/></svg>
                    </div>
                    Resultado del análisis
                </div>
                
                <div class="big-alert big-alert-danger" id="result-main">
                    <div class="big-alert-icon" id="result-icon">⚠️</div>
                    <div>
                    <h3 id="result-title">Retinopatía Diabética</h3>
                    <p id="result-sub">Alta probabilidad de anomalía detectada</p>
                    </div>
                </div>

                <div class="warning-banner">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:2px"><circle cx="10" cy="10" r="9" stroke="#D97706" stroke-width="1.5"/><path d="M10 6v5M10 13h.01" stroke="#D97706" stroke-width="1.5" stroke-linecap="round"/></svg>
                    <p>Este resultado es de carácter <strong>referencial</strong>. No reemplaza el diagnóstico médico definitivo.</p>
                </div>
                
                <div class="result-bar">
                    <div class="result-bar-label"><span>Retinopatía Diabética</span><span id="r_diabetes">0%</span></div>
                    <div class="result-track"><div class="result-fill fill-danger" id="f_diabetes" style="width:0%"></div></div>
                </div>
                <div class="result-bar">
                    <div class="result-bar-label"><span>Glaucoma</span><span id="r_glaucoma">0%</span></div>
                    <div class="result-track"><div class="result-fill fill-warning" id="f_glaucoma" style="width:0%"></div></div>
                </div>
                <div class="result-bar">
                    <div class="result-bar-label"><span>Catarata</span><span id="r_catarata">0%</span></div>
                    <div class="result-track"><div class="result-fill fill-info" id="f_catarata" style="width:0%"></div></div>
                </div>
                <div class="result-bar">
                    <div class="result-bar-label"><span>Normal</span><span id="r_normal">0%</span></div>
                    <div class="result-track"><div class="result-fill fill-success" id="f_normal" style="width:0%"></div></div>
                </div>
                
                <div style="margin-top:16px;">
                    <button class="btn btn-secondary btn-sm" id="btn-pdf">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Descargar reporte PDF
                    </button>
                    <button class="btn btn-ghost btn-sm" style="margin-left:8px" id="btn-reset">Nuevo análisis</button>
                </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="toast" class="toast" style="display:none"></div>

<script src="<?= $base ?>assets/js/medico_analisis.js"></script>
</body>
</html>
