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

$_page = 'dashboard.php';

require_once __DIR__ . '/../../models/AnalisisModel.php';
$analisisModel = new AnalisisModel();
$id_medico = $user['id'];

// Obtener datos
$kpis = $analisisModel->getKPIsMedico($id_medico);
$dist = $analisisModel->getDistribucionResultados($id_medico);
$actividad = $analisisModel->getActividadUltimos7Dias($id_medico);
$recientes = $analisisModel->getAnalisisRecientes($id_medico, 5);
$criticos = $analisisModel->getCasosCriticosRecientes($id_medico, 5);

// Preparar para JS
$js_dist = json_encode($dist);
$js_actividad = json_encode($actividad);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RetinAI — Dashboard Médico</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Bienvenido, <?= htmlspecialchars($user['nombre']) ?></h1>
            <p class="page-sub">Panel principal del médico oftalmólogo.</p>
        </div>
        <!-- ── KPIs ── -->
        <div class="grid-3" style="gap:16px; margin-top:20px;">
            <div class="card text-center" style="padding:20px;">
                <h3 style="font-size: 28px; color: var(--primary); margin-bottom: 4px;"><?= $kpis['total_pacientes'] ?: 0 ?></h3>
                <p class="text-muted" style="font-size: 13px;">Pacientes Atendidos</p>
            </div>
            <div class="card text-center" style="padding:20px;">
                <h3 style="font-size: 28px; color: var(--success); margin-bottom: 4px;"><?= $kpis['total_analisis'] ?: 0 ?></h3>
                <p class="text-muted" style="font-size: 13px;">Análisis Realizados</p>
            </div>
            <div class="card text-center" style="padding:20px;">
                <h3 style="font-size: 28px; color: var(--danger); margin-bottom: 4px;"><?= $kpis['total_alertas'] ?: 0 ?></h3>
                <p class="text-muted" style="font-size: 13px;">Casos con Alerta</p>
            </div>
        </div>

        <!-- ── GRÁFICOS ── -->
        <div class="grid-2" style="gap:16px; margin-top:16px;">
            <div class="card">
                <div class="card-title">Distribución de Resultados</div>
                <div style="height: 250px; position: relative; display: flex; justify-content: center;">
                    <canvas id="chartDist"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-title">Actividad (Últimos 7 días)</div>
                <div style="height: 250px; position: relative;">
                    <canvas id="chartActividad"></canvas>
                </div>
            </div>
        </div>

        <!-- ── TABLAS ── -->
        <div class="grid-2" style="gap:16px; margin-top:16px;">
            <!-- Últimos Análisis -->
            <div class="card" style="padding: 0;">
                <div class="card-title" style="padding: 16px 16px 0 16px;">Últimos Análisis</div>
                <div class="table-responsive" style="padding: 16px;">
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border); text-align: left;">
                                <th style="padding: 8px;">Fecha</th>
                                <th style="padding: 8px;">Paciente</th>
                                <th style="padding: 8px;">Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recientes)): ?>
                                <tr><td colspan="3" class="text-muted" style="padding: 8px;">No hay análisis recientes.</td></tr>
                            <?php else: ?>
                                <?php foreach($recientes as $r): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 8px; color: var(--text2);"><?= date('d/m/Y H:i', strtotime($r['fecha_analisis'])) ?></td>
                                    <td style="padding: 8px;">DNI: <?= $r['dni'] ?: 'N/D' ?></td>
                                    <td style="padding: 8px;">
                                        <?php if($r['alerta_anomalia']): ?>
                                            <span style="color: var(--danger); font-weight: 600;">⚠️ <?= ucfirst($r['resultado_principal']) ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--success); font-weight: 600;">✓ <?= ucfirst($r['resultado_principal']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="padding: 0 16px 16px; text-align: right;">
                    <a href="<?= $base ?>views/medico/pacientes.php" class="btn btn-ghost btn-sm">Ver historial completo →</a>
                </div>
            </div>

            <!-- Casos Críticos -->
            <div class="card" style="padding: 0;">
                <div class="card-title" style="padding: 16px 16px 0 16px; color: var(--danger);">Alertas Prioritarias</div>
                <div class="table-responsive" style="padding: 16px;">
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border); text-align: left;">
                                <th style="padding: 8px;">Fecha</th>
                                <th style="padding: 8px;">Paciente</th>
                                <th style="padding: 8px;">Confianza</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($criticos)): ?>
                                <tr><td colspan="3" class="text-muted" style="padding: 8px;">No hay alertas críticas recientes.</td></tr>
                            <?php else: ?>
                                <?php foreach($criticos as $c): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 8px; color: var(--text2);"><?= date('d/m/Y', strtotime($c['fecha_analisis'])) ?></td>
                                    <td style="padding: 8px;">DNI: <?= $c['dni'] ?: 'N/D' ?></td>
                                    <td style="padding: 8px; font-weight: bold; color: var(--danger);">
                                        <?= ucfirst($c['resultado_principal']) ?> (<?= number_format($c['probabilidad_principal'], 1) ?>%)
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="padding: 0 16px 16px; text-align: right;">
                    <a href="<?= $base ?>views/medico/seguimiento.php" class="btn btn-ghost btn-sm">Ver seguimiento de alertas →</a>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rawDist = <?= $js_dist ?>;
    const rawAct  = <?= $js_actividad ?>;

    // Colores para enfermedades
    const mapColors = {
        'normal': '#10B981',
        'diabetes': '#EF4444',
        'glaucoma': '#F59E0B',
        'catarata': '#3B82F6'
    };

    // Preparar Dist
    if (rawDist && rawDist.length > 0) {
        const labels = rawDist.map(d => d.resultado_principal.charAt(0).toUpperCase() + d.resultado_principal.slice(1));
        const data = rawDist.map(d => d.total);
        const bg = rawDist.map(d => mapColors[d.resultado_principal.toLowerCase()] || '#888');

        new Chart(document.getElementById('chartDist'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: bg, borderWidth: 0 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }

    // Preparar Actividad
    if (rawAct && rawAct.length > 0) {
        // Asegurar 7 días
        const today = new Date();
        const datesMap = {};
        for(let i = 6; i >= 0; i--) {
            let d = new Date(today);
            d.setDate(d.getDate() - i);
            const key = d.toISOString().split('T')[0];
            datesMap[key] = 0;
        }

        rawAct.forEach(a => { datesMap[a.fecha] = a.total; });

        const labels = Object.keys(datesMap).map(d => {
            const arr = d.split('-');
            return arr[2] + '/' + arr[1]; // DD/MM
        });
        const data = Object.values(datesMap);

        new Chart(document.getElementById('chartActividad'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Análisis realizados',
                    data: data,
                    backgroundColor: '#3B82F6',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }
});
</script>
</body>
</html>
