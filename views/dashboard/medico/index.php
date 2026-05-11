<?php
require_once __DIR__ . '/../../../config/session_guard.php';
require_auth();
require_role('MED');
$user = current_user();
$ini = get_initials($user['nombre']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RetinAI — Panel Médico</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../../assets/css/dashboard/dashboard.css">
</head>
<body>

<!-- TOP BAR -->
<header class="topbar">
  <a href="../../../index.php" class="topbar-brand">
    <div class="topbar-brand-mark">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="1.5"/><circle cx="12" cy="12" r="3.5" fill="white" opacity=".6"/><circle cx="12" cy="12" r="1.2" fill="white"/></svg>
    </div>
    Retin<span>AI</span>
  </a>
  <div class="topbar-spacer"></div>
  <span class="topbar-user-role">Médico</span>
  <div class="topbar-user">
    <div class="avatar avatar-green"><?= $ini ?></div>
    <span class="topbar-user-name"><?= htmlspecialchars($user['nombre']) ?></span>
  </div>
  <a href="../../../controllers/AuthController.php?action=logout" class="topbar-logout">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Salir
  </a>
</header>

<div class="app-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-section">
      <div class="sidebar-label">Análisis</div>
      <a href="#analisis" class="sidebar-item active" onclick="showPanel('analisis',this)">
        <svg class="s-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.2"/><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.2"/><circle cx="8" cy="8" r="1" fill="currentColor"/></svg>
        Nuevo análisis
      </a>
      <a href="#historial" class="sidebar-item" onclick="showPanel('historial',this)">
        <svg class="s-icon" viewBox="0 0 16 16" fill="none"><path d="M3 4h10M3 8h7M3 12h5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
        Historial de pacientes
      </a>
    </div>
    <div class="sidebar-section">
      <div class="sidebar-label">Sistema</div>
      <a href="#modelo" class="sidebar-item" onclick="showPanel('modelo',this)">
        <svg class="s-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.2"/><path d="M8 5v4l2.5 2.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
        Información del modelo
      </a>
    </div>
    <div class="sidebar-footer">
      <div class="sidebar-user-info">
        <div class="avatar avatar-green"><?= $ini ?></div>
        <div>
          <div class="avatar-name"><?= htmlspecialchars($user['nombre']) ?></div>
          <div class="avatar-role">Médico Oftalmólogo</div>
        </div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main-content">

    <!-- PANEL: NUEVO ANÁLISIS -->
    <div id="panel-analisis">
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
        <div class="step"><div class="step-circle pending" id="step2">2</div><span class="step-label">Procesando</span></div>
        <div class="step-line" id="line2"></div>
        <div class="step"><div class="step-circle pending" id="step3">✓</div><span class="step-label">Resultado</span></div>
      </div>

      <div class="grid-2" style="gap:20px">
        <div>
          <!-- Upload -->
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
              <h3>Arrastra la imagen aquí</h3>
              <p>JPG o PNG · Máximo 10 MB</p>
              <input type="file" id="file-input" accept=".jpg,.jpeg,.png" style="display:none" onchange="previewFile(this)">
            </div>
            <div id="preview-area" style="display:none;margin-top:16px;">
              <div class="retina-img" id="preview-img" style="max-width:100%;"></div>
              <div style="margin-top:12px;text-align:center;">
                <p id="file-name" class="text-sm text-muted"></p>
                <button class="btn btn-primary mt-8" onclick="runAnalysis()" id="analyze-btn">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M12 5l7 7-7 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  Analizar imagen
                </button>
              </div>
            </div>
          </div>

          <!-- DNI del paciente -->
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
            <button class="btn btn-secondary btn-sm" onclick="buscarPaciente()">Buscar / registrar paciente</button>
            <div id="paciente-result" style="margin-top:12px;display:none"></div>
          </div>
        </div>

        <!-- RESULTADO -->
        <div id="result-col" style="display:none;">
          <div class="card">
            <div class="card-title">
              <div class="card-title-icon red">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#DC2626" stroke-width="1.5"/><path d="M12 8v5M12 15h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round"/></svg>
              </div>
              Resultado del análisis
            </div>

            <div class="big-alert big-alert-danger" id="result-main">
              <div class="big-alert-icon">⚠️</div>
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
              <div class="result-bar-label"><span>Retinopatía Diabética</span><span id="r1">72%</span></div>
              <div class="result-track"><div class="result-fill fill-danger" id="f1" style="width:0%"></div></div>
            </div>
            <div class="result-bar">
              <div class="result-bar-label"><span>Glaucoma</span><span id="r2">15%</span></div>
              <div class="result-track"><div class="result-fill fill-warning" id="f2" style="width:0%"></div></div>
            </div>
            <div class="result-bar">
              <div class="result-bar-label"><span>Catarata</span><span id="r3">8%</span></div>
              <div class="result-track"><div class="result-fill fill-info" id="f3" style="width:0%"></div></div>
            </div>
            <div class="result-bar">
              <div class="result-bar-label"><span>Normal</span><span id="r4">5%</span></div>
              <div class="result-track"><div class="result-fill fill-success" id="f4" style="width:0%"></div></div>
            </div>

            <div style="margin-top:16px;">
              <button class="btn btn-secondary btn-sm" onclick="showToast('Descarga de PDF simulada','success')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Descargar PDF
              </button>
              <button class="btn btn-ghost btn-sm" style="margin-left:8px" onclick="resetAnalysis()">Nuevo análisis</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- PANEL: HISTORIAL -->
    <div id="panel-historial" style="display:none;">
      <div class="page-header">
        <h1 class="page-title">Historial de pacientes</h1>
        <p class="page-sub">Consulte el historial de análisis por DNI o código de paciente.</p>
      </div>
      <div class="card mb-16">
        <div class="card-title">Buscar paciente</div>
        <div class="flex gap-8">
          <input class="form-input-d" style="flex:1" type="text" id="hist-dni" placeholder="DNI (8 dígitos) o código de historial">
          <button class="btn btn-primary" onclick="showToast('Búsqueda realizada','success')">Buscar</button>
        </div>
      </div>
      <div class="card">
        <div class="card-title">Análisis recientes</div>
        <div class="history-item">
          <div class="history-dot" style="background:var(--danger)"></div>
          <div style="flex:1">
            <p style="font-size:13px;font-weight:600;color:var(--text)">Paciente #PAC-00142</p>
            <p class="text-sm text-muted">Retinopatía Diabética · 72% · 10 may 2026</p>
          </div>
          <span class="badge badge-pending">Alerta</span>
        </div>
        <div class="history-item">
          <div class="history-dot" style="background:var(--success)"></div>
          <div style="flex:1">
            <p style="font-size:13px;font-weight:600;color:var(--text)">Paciente #PAC-00089</p>
            <p class="text-sm text-muted">Normal · 95% · 08 may 2026</p>
          </div>
          <span class="badge badge-active">Normal</span>
        </div>
        <div class="history-item">
          <div class="history-dot" style="background:var(--warning)"></div>
          <div style="flex:1">
            <p style="font-size:13px;font-weight:600;color:var(--text)">Paciente #PAC-00231</p>
            <p class="text-sm text-muted">Glaucoma · 63% · 05 may 2026</p>
          </div>
          <span class="badge badge-pending">Alerta</span>
        </div>
      </div>
    </div>

    <!-- PANEL: MODELO -->
    <div id="panel-modelo" style="display:none;">
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
    </div>

  </main>
</div>

<!-- Toast -->
<div id="toast" class="toast" style="display:none"></div>

<script>
function showPanel(name, el) {
  ['analisis','historial','modelo'].forEach(p => document.getElementById('panel-'+p).style.display='none');
  document.getElementById('panel-'+name).style.display='block';
  document.querySelectorAll('.sidebar-item').forEach(i=>i.classList.remove('active'));
  if(el) el.classList.add('active');
}

function previewFile(input) {
  if (!input.files[0]) return;
  const file = input.files[0];
  if (file.size > 10*1024*1024) { showToast('Archivo supera 10 MB','danger'); return; }
  document.getElementById('file-name').textContent = file.name;
  document.getElementById('preview-area').style.display='block';
}

function runAnalysis() {
  const btn = document.getElementById('analyze-btn');
  btn.disabled=true; btn.textContent='Analizando...';
  document.getElementById('step2').className='step-circle active';
  document.getElementById('line1').className='step-line done';
  setTimeout(()=>{
    document.getElementById('step2').className='step-circle done';
    document.getElementById('step2').textContent='✓';
    document.getElementById('step3').className='step-circle active';
    document.getElementById('line2').className='step-line done';
    document.getElementById('result-col').style.display='block';
    animateBars();
    btn.disabled=false; btn.textContent='Analizar imagen';
  }, 1800);
}

function animateBars() {
  setTimeout(()=>{
    document.getElementById('f1').style.width='72%';
    document.getElementById('f2').style.width='15%';
    document.getElementById('f3').style.width='8%';
    document.getElementById('f4').style.width='5%';
  },100);
}

function resetAnalysis() {
  document.getElementById('result-col').style.display='none';
  document.getElementById('preview-area').style.display='none';
  document.getElementById('file-input').value='';
  ['step2','step3'].forEach(s=>{ document.getElementById(s).className='step-circle pending'; });
  document.getElementById('step2').textContent='2';
  document.getElementById('step3').textContent='✓';
  document.getElementById('step1').className='step-circle active';
  ['line1','line2'].forEach(l=>document.getElementById(l).className='step-line');
}

function buscarPaciente() {
  const dni = document.getElementById('dni-input').value.trim();
  const res = document.getElementById('paciente-result');
  if(dni.length!==8||!/^\d+$/.test(dni)){ showToast('Ingrese un DNI válido de 8 dígitos','danger'); return; }
  res.style.display='block';
  res.innerHTML=`<div class="alert-box alert-success"><p>Paciente identificado · Código: <strong>PAC-${Math.floor(10000+Math.random()*90000)}</strong></p></div>`;
}

function showToast(msg, type='') {
  const t = document.getElementById('toast');
  t.textContent=msg; t.className='toast '+(type||'');
  t.style.display='flex';
  setTimeout(()=>t.style.display='none',3000);
}
</script>
</body>
</html>
