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
<style>
/* ── Carpeta cards ─────────────────────────────────────────────────────────── */
.folder-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 10px;
  margin-bottom: 14px;
}
.folder-card {
  border: 2px solid var(--border);
  border-radius: var(--radius);
  padding: 12px 14px;
  cursor: pointer;
  transition: all 0.18s;
  background: var(--surface);
  display: flex;
  align-items: flex-start;
  gap: 10px;
  position: relative;
}
.folder-card:hover {
  border-color: var(--accent);
  background: var(--accent2);
}
.folder-card.selected {
  border-color: var(--accent);
  background: var(--accent2);
  box-shadow: 0 0 0 3px rgba(26,86,219,.12);
}
.folder-card-icon {
  font-size: 20px;
  flex-shrink: 0;
  margin-top: 2px;
}
.folder-card-info {
  min-width: 0;
}
.folder-card-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.folder-card-meta {
  font-size: 11px;
  color: var(--text3);
  margin-top: 2px;
}
.folder-card.selected .folder-card-name { color: var(--accent); }
.folder-check {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 18px;
  height: 18px;
  background: var(--accent);
  border-radius: 50%;
  display: none;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
}
.folder-card.selected .folder-check { display: flex; }

/* ── Nueva carpeta inline form ──────────────────────────────────────────────── */
.new-folder-form {
  background: var(--surface2);
  border: 1.5px dashed var(--border2);
  border-radius: var(--radius);
  padding: 14px 16px;
  margin-top: 10px;
  display: none;
  flex-direction: column;
  gap: 10px;
}
.new-folder-form.open { display: flex; }
.new-folder-row {
  display: flex;
  gap: 8px;
  align-items: flex-end;
}

/* ── Skip carpeta link ───────────────────────────────────────────────────────── */
.skip-link {
  font-size: 12px;
  color: var(--text3);
  cursor: pointer;
  text-decoration: underline;
  display: inline-block;
  margin-top: 8px;
}
.skip-link:hover { color: var(--text2); }

/* ── Carpeta section card ───────────────────────────────────────────────────── */
#carpeta-section {
  display: none;
  margin-top: 0;
}
</style>
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
            <div class="step"><div class="step-circle pending" id="step3">3</div><span class="step-label" id="lbl-step3">Resultado / Diagnóstico</span></div>
        </div>

        <div class="grid-2" style="gap:20px">
            <div>
                <!-- ── Cargar imagen ─────────────────────────────────── -->
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
                    <button class="btn btn-primary mt-8" id="analyze-btn" disabled>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M12 5l7 7-7 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Analizar imagen
                    </button>
                    </div>
                </div>
                </div>

                <!-- ── Identificación del paciente ─────────────────────── -->
                <div class="card mb-16">
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

                <!-- ── Carpeta del análisis ────────────────────────────── -->
                <div class="card" id="carpeta-section">
                <div class="card-title">
                    <div class="card-title-icon" style="background:#F3E8FF;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" stroke="#7C3AED" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    Carpeta del análisis
                    <span style="margin-left:auto;font-size:11px;font-weight:400;color:var(--text3)">Opcional</span>
                </div>

                <p style="font-size:12px;color:var(--text3);margin-bottom:12px;">
                    Seleccione una carpeta existente o cree una nueva para organizar este análisis.
                </p>

                <!-- Lista de carpetas existentes -->
                <div id="folder-list-loading" style="display:none;text-align:center;padding:16px;">
                    <span style="color:var(--text3);font-size:13px;">Cargando carpetas…</span>
                </div>
                <div class="folder-grid" id="folder-grid"></div>

                <!-- Botón crear nueva carpeta -->
                <button class="btn btn-ghost btn-sm" id="btn-toggle-new-folder" style="margin-bottom:4px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Nueva carpeta
                </button>

                <!-- Formulario inline nueva carpeta -->
                <div class="new-folder-form" id="new-folder-form">
                    <div class="new-folder-row">
                        <div class="form-group-d" style="flex:1;margin-bottom:0;">
                            <label class="form-label-d" for="folder-name-input">Nombre de la carpeta</label>
                            <input class="form-input-d" type="text" id="folder-name-input" placeholder="Ej. Control 2024, Ojo derecho…" maxlength="100">
                        </div>
                    </div>
                    <div class="form-group-d" style="margin-bottom:0;">
                        <label class="form-label-d" for="folder-desc-input">Descripción <span style="font-weight:400;color:var(--text3)">(opcional)</span></label>
                        <input class="form-input-d" type="text" id="folder-desc-input" placeholder="Breve descripción de la carpeta" maxlength="255">
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <button class="btn btn-primary btn-sm" id="btn-create-folder">Crear y seleccionar</button>
                        <button class="btn btn-ghost btn-sm" id="btn-cancel-new-folder">Cancelar</button>
                    </div>
                </div>

                <!-- Carpeta seleccionada actualmente -->
                <div id="carpeta-seleccionada" style="display:none;margin-top:12px;">
                    <div class="alert-box alert-info" style="margin-bottom:0;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" stroke="#1A56DB" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <p>Carpeta seleccionada: <strong id="carpeta-seleccionada-nombre"></strong>
                        <button onclick="deseleccionarCarpeta()" style="border:none;background:none;color:var(--danger);cursor:pointer;font-size:11px;margin-left:8px;font-weight:600;">✕ Quitar</button></p>
                    </div>
                </div>

                <span class="skip-link" id="skip-carpeta-link" onclick="saltarCarpeta()">Continuar sin seleccionar carpeta</span>
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
                
                <!-- Diagnostico Medico -->
                <div style="margin-top:20px; border-top: 1px solid var(--border); padding-top: 16px;">
                    <div class="form-group-d">
                        <label class="form-label-d" for="diagnostico-input">Diagnóstico médico y observaciones</label>
                        <textarea class="form-input-d" id="diagnostico-input" rows="4" maxlength="1000" placeholder="Escriba aquí su diagnóstico, interpretación del resultado o cualquier observación relevante..."></textarea>
                    </div>
                </div>
                
                <div style="margin-top:16px;">
                    <button class="btn btn-primary btn-sm" id="btn-save-final">Guardar análisis</button>
                    <button class="btn btn-secondary btn-sm" id="btn-pdf" style="display:none;">
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="<?= $base ?>assets/js/medico_analisis.js"></script>
</body>
</html>
