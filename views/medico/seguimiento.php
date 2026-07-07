<?php
require_once __DIR__ . '/../../config/session_guard.php';
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

$_page = 'seguimiento.php';

$analisisModel = new AnalisisModel();
$casosCriticos = $analisisModel->getSeguimientoCasosCriticos($user['id'], 50);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RetinAI — Seguimiento de Alertas</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<style>
.critical-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.summary-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
}
.summary-value {
    font-family: "DM Mono", monospace;
    font-size: 24px;
    font-weight: 700;
    color: var(--danger);
}
.summary-label {
    font-size: 12px;
    color: var(--text3);
}
.critical-list {
    display: grid;
    gap: 12px;
}
.critical-case {
    border: 1px solid #fecaca;
    border-left: 4px solid var(--danger);
    border-radius: var(--radius);
    background: #fff7f7;
    padding: 16px;
}
.critical-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}
.critical-main {
    min-width: 0;
}
.critical-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 6px;
}
.critical-meta {
    font-size: 13px;
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
.diagnosis-note {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #fecaca;
    font-size: 12px;
    color: var(--text2);
}
@media (max-width: 900px) {
    .critical-summary {
        grid-template-columns: 1fr;
    }
    .critical-row {
        flex-direction: column;
    }
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
            <h1 class="page-title">Seguimiento de casos críticos o alertas</h1>
            <p class="page-sub">Priorice los análisis con alerta de anomalía y acceda rápidamente al historial o reporte PDF.</p>
        </div>

        <?php
            $totalAlertas = count($casosCriticos);
            $conDiagnostico = 0;
            $sumaProbabilidad = 0;
            foreach ($casosCriticos as $casoResumen) {
                if (!empty($casoResumen['diagnostico_medico'])) {
                    $conDiagnostico++;
                }
                $sumaProbabilidad += (float)$casoResumen['probabilidad_principal'];
            }
            $promedio = 0;
            if ($totalAlertas > 0) {
                $promedio = $sumaProbabilidad / $totalAlertas;
            }
        ?>
        <div class="critical-summary">
            <div class="summary-box">
                <div class="summary-value"><?= $totalAlertas ?></div>
                <div class="summary-label">Casos con alerta</div>
            </div>
            <div class="summary-box">
                <div class="summary-value"><?= number_format($promedio, 1) ?>%</div>
                <div class="summary-label">Confianza promedio</div>
            </div>
            <div class="summary-box">
                <div class="summary-value"><?= $conDiagnostico ?></div>
                <div class="summary-label">Con diagnóstico médico</div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">
                Alertas priorizadas
                <?php if ($totalAlertas > 0): ?>
                    <span class="badge badge-pending" style="margin-left:auto"><?= $totalAlertas ?> pendientes</span>
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
                        $fecha = $caso['fecha_analisis'] ? date('d/m/Y H:i', strtotime($caso['fecha_analisis'])) : 'Sin fecha';
                        $historialUrl = $base . 'views/medico/pacientes.php?q=' . urlencode((string)$caso['codigo_paciente']) . '&open=' . (int)$caso['id_paciente'];
                    ?>
                    <article class="critical-case">
                        <div class="critical-row">
                            <div class="critical-main">
                                <div class="critical-title">
                                    Paciente #<?= htmlspecialchars($codigo) ?> · <?= htmlspecialchars($resultado) ?>
                                    <span class="critical-score"><?= $probabilidad ?>%</span>
                                </div>
                                <div class="critical-meta">
                                    DNI: <?= htmlspecialchars($dni) ?> · Fecha: <?= htmlspecialchars($fecha) ?>
                                </div>
                            </div>
                            <div class="critical-actions">
                                <?php if (!empty($caso['codigo_paciente']) && !empty($caso['id_paciente'])): ?>
                                    <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($historialUrl, ENT_QUOTES) ?>">Ver historial</a>
                                <?php endif; ?>
                                <button class="btn btn-ghost btn-sm" type="button" onclick="descargarPDF(<?= (int)$caso['id'] ?>)">PDF</button>
                            </div>
                        </div>

                        <?php if (!empty($caso['diagnostico_medico'])): ?>
                            <div class="diagnosis-note">Este análisis ya cuenta con diagnóstico médico registrado.</div>
                        <?php else: ?>
                            <div class="diagnosis-note">Pendiente de revisión diagnóstica del médico.</div>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<div id="toast" class="toast" style="display:none"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="<?= $base ?>assets/js/retinai-pdf-report.js"></script>
</body>
</html>
