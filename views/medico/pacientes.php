<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../models/PacienteModel.php';
require_once __DIR__ . '/../../models/AnalisisModel.php';
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

$_page = 'pacientes.php';

$pacienteModel = new PacienteModel();
$pacientes = $pacienteModel->listarPacientesConAnalisisMedico($user['id']);
$analisisModel = new AnalisisModel();
$casosCriticos = $analisisModel->getSeguimientoCasosCriticos($user['id'], 20);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RetinAI — Historial de Pacientes</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<style>
.paciente-card {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    margin-bottom: 12px;
    background: var(--surface);
    transition: all 0.2s;
}
.paciente-card:hover {
    border-color: var(--accent);
    box-shadow: var(--shadow);
}
.paciente-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}
.paciente-info-main {
    flex: 1;
}
.paciente-stats {
    display: flex;
    gap: 12px;
    align-items: center;
    font-size: 12px;
    color: var(--text3);
}
.stat-badge {
    background: var(--surface2);
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 600;
}
.stat-badge.alert {
    background: var(--danger2);
    color: var(--danger);
}
.detalle-btn {
    font-size: 12px;
    color: var(--accent);
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 600;
    padding: 6px;
}
.detalle-btn:hover {
    text-decoration: underline;
}
.paciente-detalle-wrapper {
    display: none;
    margin-top: 12px;
    border-top: 1px dashed var(--border);
    padding-top: 12px;
}
.recovery-result {
    display: none;
    margin-top: 12px;
}
.history-code {
    font-family: "DM Mono", monospace;
    font-weight: 700;
    letter-spacing: .02em;
}
.critical-list {
    display: grid;
    gap: 10px;
}
.critical-case {
    border: 1px solid #fecaca;
    border-left: 4px solid var(--danger);
    border-radius: var(--radius);
    padding: 14px;
    background: #fff7f7;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.critical-main {
    min-width: 0;
}
.critical-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 4px;
}
.critical-meta {
    font-size: 12px;
    color: var(--text2);
}
.critical-score {
    font-family: "DM Mono", monospace;
    font-weight: 700;
    color: var(--danger);
}
.critical-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}
.search-result-note {
    display: none;
    margin-top: 10px;
    font-size: 12px;
    color: var(--text3);
}
</style>
<script>const BASE_URL = '<?= $base ?>';</script>
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Historial de pacientes</h1>
            <p class="page-sub">Consulte los pacientes atendidos y sus resultados de retinografías.</p>
        </div>

        <div class="card mb-16">
            <div class="card-title">
                Seguimiento de casos críticos o alertas
                <?php if (!empty($casosCriticos)): ?>
                    <span class="badge badge-pending" style="margin-left:auto"><?= count($casosCriticos) ?> alertas</span>
                <?php endif; ?>
            </div>
            <?php if (empty($casosCriticos)): ?>
                <p class="text-muted">No hay casos críticos pendientes de seguimiento.</p>
            <?php else: ?>
                <div class="critical-list">
                    <?php foreach ($casosCriticos as $caso):
                        $codigo = $caso['codigo_paciente'] ?: 'Sin código';
                        $dni = $caso['dni'] ?: 'Sin DNI';
                        $resultado = ucfirst((string)$caso['resultado_principal']);
                        $probabilidad = number_format((float)$caso['probabilidad_principal'], 1);
                        $fecha = $caso['fecha_analisis'] ? date('d M Y H:i', strtotime($caso['fecha_analisis'])) : 'Sin fecha';
                    ?>
                    <div class="critical-case">
                        <div class="critical-main">
                            <div class="critical-title">
                                Paciente #<?= htmlspecialchars($codigo) ?> · <?= htmlspecialchars($resultado) ?>
                                <span class="critical-score"><?= $probabilidad ?>%</span>
                            </div>
                            <div class="critical-meta">
                                DNI: <?= htmlspecialchars($dni) ?> · Fecha: <?= htmlspecialchars($fecha) ?>
                                <?php if (!empty($caso['diagnostico_medico'])): ?>
                                    · Con diagnóstico médico
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="critical-actions">
                            <?php if (!empty($caso['codigo_paciente'])): ?>
                                <button class="btn btn-secondary btn-sm" type="button" onclick="buscarPacienteCritico('<?= htmlspecialchars($caso['codigo_paciente'], ENT_QUOTES) ?>')">Ver historial</button>
                            <?php endif; ?>
                            <button class="btn btn-ghost btn-sm" type="button" onclick="descargarPDF(<?= (int)$caso['id'] ?>)">PDF</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card mb-16">
            <div class="card-title">Buscar paciente (Filtro local)</div>
            <div class="flex gap-8">
                <input class="form-input-d" style="flex:1" type="text" id="hist-dni" placeholder="Buscar por DNI o código de paciente..." onkeyup="filterHistory()">
                <button class="btn btn-secondary btn-sm" type="button" onclick="filterHistory()">Buscar</button>
                <button class="btn btn-ghost btn-sm" type="button" onclick="clearHistorySearch()">Limpiar</button>
            </div>
            <p id="history-search-note" class="search-result-note"></p>
        </div>

        <div class="card mb-16">
            <div class="card-title">Recuperar código de historial por DNI</div>
            <div class="flex gap-8">
                <input class="form-input-d" style="flex:1" type="text" id="recover-dni" placeholder="Ingrese DNI de 8 dígitos" maxlength="8" inputmode="numeric">
                <button class="btn btn-secondary btn-sm" id="btn-recover-code" type="button">Recuperar código</button>
            </div>
            <div id="recover-result" class="recovery-result"></div>
        </div>

        <div class="card" id="history-container">
            <div class="card-title">Pacientes recientes</div>
            <?php if (empty($pacientes)): ?>
                <p class="text-muted">No hay pacientes con análisis registrados.</p>
            <?php else: ?>
                <?php foreach ($pacientes as $p): 
                    $search_text = strtolower($p['dni'] . ' ' . $p['codigo_paciente']);
                    $ultimo = date('d M Y', strtotime($p['ultimo_analisis']));
                ?>
                <div class="paciente-card" data-search="<?= htmlspecialchars($search_text) ?>">
                    <div class="paciente-header" onclick="toggleDetalle(<?= $p['id'] ?>, this)">
                        <div class="paciente-info-main">
                            <h3 style="font-size: 15px; color: var(--text); margin-bottom: 4px;">Paciente #<?= htmlspecialchars($p['codigo_paciente']) ?></h3>
                            <p style="font-size: 13px; color: var(--text2);">DNI: <?= htmlspecialchars($p['dni']) ?> · Último análisis: <?= $ultimo ?></p>
                        </div>
                        <div class="paciente-stats">
                            <span class="stat-badge"><?= $p['total_carpetas'] ?> Carpetas</span>
                            <span class="stat-badge"><?= $p['total_analisis'] ?> Análisis</span>
                            <?php if ($p['total_alertas'] > 0): ?>
                                <span class="stat-badge alert"><?= $p['total_alertas'] ?> Alertas</span>
                            <?php endif; ?>
                            <button class="detalle-btn">Ver detalle ▼</button>
                        </div>
                    </div>
                    <div id="detalle-<?= $p['id'] ?>" class="paciente-detalle-wrapper">
                        <div style="text-align: center; color: var(--text3); font-size: 13px;">Cargando historial...</div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<div id="toast" class="toast" style="display:none"></div>

<script>
function filterHistory() {
    const term = document.getElementById('hist-dni').value.toLowerCase();
    const items = document.querySelectorAll('.paciente-card');
    const note = document.getElementById('history-search-note');
    let matches = 0;
    items.forEach(item => {
        if (!term || item.getAttribute('data-search').includes(term)) {
            item.style.display = 'block';
            matches++;
        } else {
            item.style.display = 'none';
        }
    });

    if (note) {
        if (!term) {
            note.style.display = 'none';
            note.textContent = '';
        } else if (matches === 0) {
            note.style.display = 'block';
            note.textContent = 'No se encontraron pacientes que coincidan con la búsqueda.';
        } else {
            note.style.display = 'block';
            note.textContent = matches + ' paciente(s) encontrado(s).';
        }
    }
}

function clearHistorySearch() {
    document.getElementById('hist-dni').value = '';
    filterHistory();
}

function buscarPacienteCritico(codigo) {
    const input = document.getElementById('hist-dni');
    input.value = codigo;
    filterHistory();

    const firstMatch = Array.from(document.querySelectorAll('.paciente-card'))
        .find(item => item.style.display !== 'none');

    if (!firstMatch) return;

    firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const header = firstMatch.querySelector('.paciente-header');
    const detail = firstMatch.querySelector('.paciente-detalle-wrapper');
    if (header && detail && detail.style.display !== 'block') {
        toggleDetalle(detail.id.replace('detalle-', ''), header);
    }
}

document.getElementById('btn-recover-code').addEventListener('click', recuperarCodigoHistorial);
document.getElementById('recover-dni').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        recuperarCodigoHistorial();
    }
});

async function recuperarCodigoHistorial() {
    const input = document.getElementById('recover-dni');
    const resultBox = document.getElementById('recover-result');
    const btn = document.getElementById('btn-recover-code');
    const dni = input.value.trim();

    resultBox.style.display = 'none';
    resultBox.innerHTML = '';

    if (!/^\d{8}$/.test(dni)) {
        mostrarResultadoRecuperacion('danger', 'El DNI debe contener exactamente 8 dígitos numéricos.');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Buscando...';

    const fd = new FormData();
    fd.append('dni', dni);

    try {
        const res = await fetch(BASE_URL + 'controllers/PacienteController.php?action=recuperar_codigo', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data.success) {
            const codigo = escapeHtml(data.paciente.codigo_paciente);
            mostrarResultadoRecuperacion(
                'success',
                'El código de historial de este paciente es: <span class="history-code">' + codigo + '</span>'
            );
            document.getElementById('hist-dni').value = codigo;
            filterHistory();
        } else {
            if (data.expired) {
                window.location.href = BASE_URL + 'views/auth/login.php?expired=1';
                return;
            }
            mostrarResultadoRecuperacion('danger', escapeHtml(data.error || 'No se pudo realizar la búsqueda en este momento. Intente nuevamente.'));
        }
    } catch (e) {
        mostrarResultadoRecuperacion('danger', 'No se pudo realizar la búsqueda en este momento. Intente nuevamente.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Recuperar código';
    }
}

function mostrarResultadoRecuperacion(type, message) {
    const resultBox = document.getElementById('recover-result');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    resultBox.className = 'recovery-result alert-box ' + alertClass;
    resultBox.innerHTML = '<p>' + message + '</p>';
    resultBox.style.display = 'flex';
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

const cargados = {};

async function toggleDetalle(id_paciente, headerElement) {
    const wrapper = document.getElementById('detalle-' + id_paciente);
    const btn = headerElement.querySelector('.detalle-btn');

    if (wrapper.style.display === 'block') {
        wrapper.style.display = 'none';
        btn.textContent = 'Ver detalle ▼';
        return;
    }

    wrapper.style.display = 'block';
    btn.textContent = 'Ocultar ▲';

    if (!cargados[id_paciente]) {
        try {
            const res = await fetch(BASE_URL + 'controllers/PacienteController.php?action=detalle_html&id_paciente=' + id_paciente);
            const html = await res.text();
            wrapper.innerHTML = html;
            cargados[id_paciente] = true;
        } catch (e) {
            wrapper.innerHTML = '<p class="text-danger">Error al cargar el detalle.</p>';
        }
    }
}

function toggleAnalisis(folderId) {
    const div = document.getElementById(folderId);
    if (div) {
        div.style.display = div.style.display === 'none' ? 'block' : 'none';
    }
}

function filterFolders(input) {
    const term = input.value.toLowerCase();
    const container = input.closest('.paciente-detalle-container');
    const folders = container.querySelectorAll('.carpeta-box');
    folders.forEach(f => {
        const titleText = f.querySelector('strong').textContent.toLowerCase();
        if (titleText.includes(term)) {
            f.style.display = 'block';
        } else {
            f.style.display = 'none';
        }
    });
}

async function descargarPDF(id_analisis) {
    try {
        const res = await fetch(BASE_URL + 'controllers/AnalisisController.php?action=datos_pdf&id_analisis=' + id_analisis);
        const json = await res.json();
        if (json.success) {
            generarPDF(json.analisis);
            showToast('PDF descargado', 'success');
        } else {
            showToast(json.error || 'Error', 'danger');
        }
    } catch(e) {
        showToast('Error al descargar PDF', 'danger');
    }
}

// Generación PDF idéntica a la de nuevo análisis
async function generarPDF(a) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
    const W  = doc.internal.pageSize.getWidth();
    const pageH = doc.internal.pageSize.getHeight();
    let y = 0;

    doc.setFillColor(26, 86, 219);
    doc.rect(0, 0, W, 30, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(20);
    doc.text('RetinAI', 14, 13);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.text('Sistema de análisis retinal asistido por inteligencia artificial', 14, 20);
    doc.setFontSize(8);
    doc.text('Reporte #' + a.id, W - 14, 13, { align: 'right' });
    doc.text('Análisis retinal — Reporte médico', W - 14, 20, { align: 'right' });

    y = 38;
    doc.setDrawColor(230, 235, 245);
    doc.setFillColor(248, 250, 255);
    doc.roundedRect(10, y, W - 20, 28, 3, 3, 'FD');

    doc.setTextColor(26, 86, 219);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.text('MÉDICO TRATANTE', 16, y + 7);
    doc.setTextColor(30, 30, 40);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.text('Dr. ' + (a.nombre_medico || 'N/D'), 16, y + 15);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(90, 95, 110);
    const espLabel = a.especialidad_medico ? a.especialidad_medico : 'Médico Oftalmólogo';
    doc.text(espLabel + (a.cmp_medico ? '   ·   CMP: ' + a.cmp_medico : ''), 16, y + 22);

    let fechaStr = 'N/D';
    if(a.fecha_analisis) {
        const d = new Date(a.fecha_analisis.replace(' ', 'T'));
        fechaStr = d.toLocaleDateString('es-PE', { day:'2-digit', month:'long', year:'numeric' }) + '  —  ' + d.toLocaleTimeString('es-PE', { hour:'2-digit', minute:'2-digit' });
    }

    doc.setTextColor(26, 86, 219);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.text('FECHA Y HORA DEL ANÁLISIS', W - 14, y + 7, { align: 'right' });
    doc.setTextColor(30, 30, 40);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.text(fechaStr, W - 14, y + 15, { align: 'right' });
    if (a.codigo_paciente || a.dni_paciente) {
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(90, 95, 110);
        doc.text((a.codigo_paciente ? 'Cód. Paciente: ' + a.codigo_paciente : '') + (a.dni_paciente ? '   DNI: ' + a.dni_paciente : ''), W - 14, y + 22, { align: 'right' });
    }

    y += 36;
    const imgW  = 60;
    const imgH  = 55;
    const imgX  = 14;
    const imgY  = y;

    if (a.imagen_b64) {
        try {
            const ext = a.imagen_b64.startsWith('data:image/png') ? 'PNG' : 'JPEG';
            doc.addImage(a.imagen_b64, ext, imgX, imgY, imgW, imgH, '', 'MEDIUM');
            doc.setDrawColor(200, 210, 230);
            doc.setLineWidth(0.4);
            doc.rect(imgX, imgY, imgW, imgH);
        } catch(e) {}
    }

    doc.setFont('helvetica', 'italic');
    doc.setFontSize(7);
    doc.setTextColor(120, 125, 140);
    doc.text('Retinografía analizada', imgX + imgW / 2, imgY + imgH + 5, { align: 'center' });

    const rX = imgX + imgW + 8;
    const rW = W - rX - 10;
    const badgeColor = a.alerta_anomalia ? [220, 38, 38] : [5, 150, 105];

    doc.setFillColor(...badgeColor);
    doc.roundedRect(rX, imgY, rW, 22, 3, 3, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.text('RESULTADO PRINCIPAL', rX + rW / 2, imgY + 7, { align: 'center' });
    doc.setFontSize(14);
    doc.text((a.resultado_principal.charAt(0).toUpperCase() + a.resultado_principal.slice(1).toLowerCase()), rX + rW / 2, imgY + 17, { align: 'center' });

    doc.setFillColor(240, 245, 255);
    doc.setDrawColor(210, 220, 240);
    doc.roundedRect(rX, imgY + 25, rW, 12, 2, 2, 'FD');
    doc.setTextColor(26, 86, 219);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.text('Confianza: ' + Number(a.probabilidad_principal).toFixed(1) + '%', rX + rW / 2, imgY + 33, { align: 'center' });

    const barY0 = imgY + 40;
    const clases = [
        { label: 'Retinopatía Diabética', val: a.probabilidad_diabetes, color: [220, 38, 38]  },
        { label: 'Glaucoma',              val: a.probabilidad_glaucoma, color: [217, 119, 6]  },
        { label: 'Catarata',              val: a.probabilidad_catarata, color: [37, 99, 235]  },
        { label: 'Normal',                val: a.probabilidad_normal,   color: [5, 150, 105]  },
    ];
    const barMaxW = rW - 4;
    clases.forEach((c, i) => {
        const by = barY0 + i * 11;
        const pct = Math.min(100, Math.max(0, Number(c.val)));
        const filledW = (pct / 100) * barMaxW;
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(50, 55, 70);
        doc.text(c.label, rX, by + 3.5);
        doc.setTextColor(80, 85, 100);
        doc.text(pct.toFixed(1) + '%', rX + rW, by + 3.5, { align: 'right' });
        doc.setFillColor(230, 234, 242);
        doc.roundedRect(rX, by + 5, barMaxW, 3.5, 1, 1, 'F');
        if (filledW > 0) {
            doc.setFillColor(...c.color);
            doc.roundedRect(rX, by + 5, filledW, 3.5, 1, 1, 'F');
        }
    });

    y = Math.max(imgY + imgH + 8, barY0 + clases.length * 11 + 6);

    // Diagnóstico Médico
    if (a.diagnostico_medico) {
        doc.setFillColor(248, 250, 255);
        doc.setDrawColor(210, 220, 240);
        const diagLines = doc.splitTextToSize(a.diagnostico_medico, W - 32);
        const diagH = diagLines.length * 4.5 + 14;
        doc.roundedRect(10, y, W - 20, diagH, 2, 2, 'FD');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(26, 86, 219);
        doc.text('DIAGNÓSTICO Y OBSERVACIONES CLÍNICAS', 16, y + 7);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor(30, 30, 40);
        doc.text(diagLines, 16, y + 14);
        y += diagH + 8;
    }

    y += 4;
    doc.setFillColor(255, 251, 235);
    doc.setDrawColor(253, 230, 138);
    doc.roundedRect(10, y, W - 20, 14, 2, 2, 'FD');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7.5);
    doc.setTextColor(146, 64, 14);
    doc.text('⚠  RESULTADO REFERENCIAL', 16, y + 6);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7);
    doc.setTextColor(120, 80, 20);
    doc.text('Este análisis fue generado por un modelo de inteligencia artificial. No constituye diagnóstico definitivo.', 16, y + 11);

    y += 20;
    const leyenda = 'Resultado generado por modelo de inteligencia artificial con fines de apoyo diagnóstico. La decisión clínica final corresponde al médico tratante.';
    doc.setFillColor(241, 245, 249);
    doc.setDrawColor(203, 213, 225);
    const leyendaLines = doc.splitTextToSize(leyenda, W - 32);
    const leyendaH = leyendaLines.length * 4.5 + 8;
    doc.roundedRect(10, y, W - 20, leyendaH, 2, 2, 'FD');
    doc.setFont('helvetica', 'italic');
    doc.setFontSize(8);
    doc.setTextColor(71, 85, 105);
    doc.text(leyendaLines, 16, y + 7);

    doc.setFillColor(26, 86, 219);
    doc.rect(0, pageH - 12, W, 12, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7);
    doc.text('RetinAI — Sistema de análisis retinal asistido por IA', 14, pageH - 5);
    doc.text('Generado: ' + new Date().toLocaleString('es-PE'), W - 14, pageH - 5, { align: 'right' });

    const fechaFile = new Date().toISOString().slice(0, 10);
    doc.save('RetinAI_Reporte_' + a.id + '_' + fechaFile + '.pdf');
}

function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'toast ' + (type || '');
    t.style.display = 'flex';
    setTimeout(() => t.style.display = 'none', 3000);
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

</body>
</html>
