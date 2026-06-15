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

$_page = 'pacientes.php';

$analisisModel = new AnalisisModel();
$historial = $analisisModel->obtenerHistorialMedico($user['id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RetinAI — Historial de Pacientes</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Historial de pacientes</h1>
            <p class="page-sub">Consulte el historial de análisis realizados.</p>
        </div>
        <div class="card mb-16">
            <div class="card-title">Buscar paciente (Filtro local)</div>
            <div class="flex gap-8">
                <input class="form-input-d" style="flex:1" type="text" id="hist-dni" placeholder="DNI (8 dígitos) o código de historial" onkeyup="filterHistory()">
            </div>
        </div>
        <div class="card" id="history-container">
            <div class="card-title">Análisis recientes</div>
            <?php if (empty($historial)): ?>
                <p class="text-muted">No hay análisis registrados.</p>
            <?php else: ?>
                <?php foreach ($historial as $item): 
                    $color = 'var(--success)';
                    $badge = 'badge-active';
                    $badgeText = 'Normal';
                    if ($item['alerta_anomalia']) {
                        $color = 'var(--danger)';
                        $badge = 'badge-pending';
                        $badgeText = 'Alerta';
                    }
                    $paciente_info = $item['codigo_paciente'] ? "Paciente #" . htmlspecialchars($item['codigo_paciente']) : "Paciente No Registrado";
                    $search_text = strtolower($item['dni'] . ' ' . $item['codigo_paciente']);
                    $fecha = date('d M Y H:i', strtotime($item['fecha_analisis']));
                    $prob = number_format($item['probabilidad_principal'], 1) . '%';
                    $resultado = htmlspecialchars(ucfirst($item['resultado_principal']));
                ?>
                <div class="history-item" data-search="<?= htmlspecialchars($search_text) ?>">
                    <div class="history-dot" style="background:<?= $color ?>"></div>
                    <div style="flex:1">
                        <p style="font-size:13px;font-weight:600;color:var(--text)"><?= $paciente_info ?></p>
                        <p class="text-sm text-muted"><?= $resultado ?> · <?= $prob ?> · <?= $fecha ?></p>
                    </div>
                    <span class="badge <?= $badge ?>"><?= $badgeText ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function filterHistory() {
    const term = document.getElementById('hist-dni').value.toLowerCase();
    const items = document.querySelectorAll('.history-item');
    items.forEach(item => {
        if (item.getAttribute('data-search').includes(term)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>
</body>
</html>
